<?php
// app/Http/Controllers/BookingController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bus;
use App\Models\Booking;
use App\Models\User;
use App\Models\SeatHold;
use App\Models\Currency;
use App\Models\PromoCode;
use App\Models\PriceRule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    /**
     * Генерація підпису для WayForPay
     */
//    protected function generateWayForPaySignature(array $data, string $secret): string
    public static function generateWayForPaySignature(array $data, string $secret): string
    {
        $fields = [
            $data['merchantAccount'],
            $data['merchantDomainName'],
            $data['orderReference'],
            $data['orderDate'],
            $data['amount'],
            $data['currency'],
        ];

        foreach ($data['productName'] as $pn)   { $fields[] = $pn; }
        foreach ($data['productCount'] as $pc)  { $fields[] = $pc; }
        foreach ($data['productPrice'] as $pp)  { $fields[] = $pp; }

        return hash_hmac('md5', implode(';', $fields), $secret);
    }

    /**
     * (Адмін) Створення базового бронювання
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'route_id'      => 'required|exists:routes,id',
            'bus_id'        => 'required|exists:buses,id',
            'date'          => 'required|date',
            'selected_seat' => 'required|integer',
            'seat_number'   => 'required|integer',
        ]);

        $exists = Booking::where('bus_id', $validatedData['bus_id'])
            ->where('seat_number', $validatedData['selected_seat'])
            ->whereDate('date', $validatedData['date'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['selected_seat' => 'Це місце вже заброньовано.']);
        }

        Booking::create($validatedData);

        return redirect()->route('booking.index')->with('success', 'Бронювання створено успішно!');
    }

    /**
     * Публічний API: інфо про автобус + зайняті місця на дату
     */
    public function getBusInfo($tripId, Request $request)
    {
        $date = $request->query('date');
        $bus  = Bus::findOrFail((int) $tripId);

        $bookedSeats = Booking::where('bus_id', $bus->id)
            ->whereDate('date', $date)
            ->pluck('seat_number')
            ->map(fn ($n) => (string) $n)
            ->toArray();

        return response()->json([
            'bus' => [
                'id'          => $bus->id,
                'seat_layout' => $bus->seat_layout,
                'name'        => $bus->name,
            ],
            'booked_seats' => $bookedSeats,
        ]);
    }

    public function checkPromo(Request $r)
    {
        $code = (string) $r->query('code');
        $sumUah = (float) $r->query('subtotal_uah', 0);

        if ($code === '' || $sumUah <= 0) {
            return response()->json(['ok' => false, 'discount_uah' => 0]);
        }

        $promo = PromoCode::active()->where('code', PromoCode::normalize($code))->first();
        if (!$promo || ($promo->min_amount && $sumUah < (float) $promo->min_amount)) {
            return response()->json(['ok' => false, 'discount_uah' => 0]);
        }

        $discount = $promo->type === 'percent'
            ? round($sumUah * ((float)$promo->value)/100, 2)
            : min(round((float)$promo->value, 2), $sumUah);

        return response()->json([
            'ok' => true,
            'discount_uah' => $discount,
            'type' => $promo->type,
            'value' => $promo->value,
            'min_amount' => $promo->min_amount,
        ]);
    }


    /**
     * Публічний API: бронювання (hold + без сусіда + валюта + промокод)
     */
    public function bookSeat(Request $request)
    {
        $data = $request->all();

        // 0) Валідація
        $request->validate([
            'trip_id'       => ['required','integer','exists:buses,id'], // trip_id == bus_id
            'date'          => ['required','date'],
            'seats'         => ['required','array','min:1'],
            'seats.*'       => ['integer'],
            'hold_token'    => ['nullable','string','max:64'],
            'solo'          => ['nullable','boolean'],
            'phone'         => ['required','string','regex:/^\+?[0-9\s\-()]{8,20}$/'],
            'phone_alt'     => ['nullable','string','regex:/^\+?[0-9\s\-()]{8,20}$/'],

            'currency_code' => ['nullable','string','max:3'],
            'promo_code'    => ['nullable','string','max:64'],

            'passengers'                 => ['nullable','array','min:1'],
            'passengers.*.seat'          => ['required','integer'],
            'passengers.*.first_name'    => ['required','string','max:255'],
            'passengers.*.last_name'     => ['required','string','max:255'],
            'passengers.*.doc_number'    => ['nullable','string','max:255'],
            'passengers.*.category'      => ['nullable','in:adult,child'],
            'passengers.*.extras'        => ['nullable','array'],
            'passengers.*.extras.*'      => ['string'],
        ]);

        // 1) Авторизація/реєстрація/логін
        $user = Auth::user();
        if (!$user) {
            $user = User::where('email', $data['email'] ?? '')->first();
            if (!$user) {
                $user = User::create([
                    'name'     => $data['name'] ?? '',
                    'surname'  => $data['surname'] ?? '',
                    'email'    => $data['email'] ?? '',
                    'phone'    => $data['phone'] ?? '',
                    'password' => Hash::make($data['password'] ?? 'changeme'),
                ]);
                Auth::login($user);
            } else {
                if (!isset($data['password']) || !Hash::check($data['password'], $user->password)) {
                    return response()->json(['error' => 'Пароль невірний'], 422);
                }
                Auth::login($user);
            }
        }

        $busId     = (int) $data['trip_id'];
        $date      = $data['date'];
        $requested = array_values(array_unique(array_map('intval', $data['seats'])));
        $holdToken = $data['hold_token'] ?? null;
        $solo      = (bool) ($data['solo'] ?? false);

        // Валюта
        $currencyCode = strtoupper((string)($data['currency_code'] ?? 'UAH'));
        $currency     = Currency::where('code', $currencyCode)->where('is_active', 1)->first();
        if (!$currency) {
            $currency     = Currency::where('code', 'UAH')->first();
            $currencyCode = 'UAH';
        }
        $fx = (float) ($currency->rate ?? 1.0); // множник UAH->currency

        // 2) Очистка прострочених hold, базові конфлікти
        SeatHold::where('expires_at', '<=', now())->delete();

        $bookedConflict = Booking::where('bus_id', $busId)
            ->whereDate('date', $date)
            ->whereIn('seat_number', $requested)
            ->exists();
        if ($bookedConflict) {
            return response()->json(['error' => 'Деякі місця вже заброньовані'], 422);
        }

        $heldByOthers = SeatHold::where('bus_id', $busId)
            ->whereDate('date', $date)
            ->where('expires_at', '>', now())
            ->when($holdToken, fn ($q) => $q->where('token', '!=', $holdToken))
            ->whereIn('seat_number', $requested)
            ->exists();
        if ($heldByOthers) {
            return response()->json(['error' => 'Деякі місця утримуються іншим користувачем'], 422);
        }

        if ($holdToken) {
            $heldByToken = SeatHold::where('bus_id', $busId)
                ->whereDate('date', $date)
                ->where('expires_at', '>', now())
                ->where('token', $holdToken)
                ->pluck('seat_number')
                ->map(fn ($n) => (int) $n)
                ->toArray();

            $diff = array_diff($requested, $heldByToken);
            if (!empty($diff)) {
                return response()->json(['error' => 'hold_mismatch', 'not_held' => array_values($diff)], 422);
            }
        }

        // 3) Layout мапа number->row/column (якщо знадобиться для сусіда)
        $bus        = Bus::findOrFail($busId);
        $layoutRaw  = is_string($bus->seat_layout) ? json_decode($bus->seat_layout, true) : $bus->seat_layout;
        $layoutArr  = is_array($layoutRaw) ? $layoutRaw : [];

        $byNumber = [];
        foreach ($layoutArr as $item) {
            if (($item['type'] ?? '') === 'seat' && isset($item['number'])) {
                $num = (int) $item['number'];
                $byNumber[$num] = [
                    'row'    => (int)($item['row'] ?? -1),
                    'column' => (int)($item['column'] ?? -1),
                    'price'  => (float)($item['price'] ?? 0),
                ];
            }
        }

        // 4) Опція "без сусіда"
        $finalSeats = $requested;
        if ($solo && count($requested) === 1) {
            $n = (int) $requested[0];
            $r = $byNumber[$n]['row']    ?? null;
            $c = $byNumber[$n]['column'] ?? null;

            if ($r !== null && $c !== null) {
                $candidates = [$c - 1, $c + 1];

                $bookedSet = Booking::where('bus_id', $busId)
                    ->whereDate('date', $date)
                    ->pluck('seat_number')->map(fn ($x) => (int) $x)->toArray();

                $heldSet = SeatHold::where('bus_id', $busId)
                    ->whereDate('date', $date)
                    ->where('expires_at', '>', now())
                    ->pluck('seat_number')->map(fn ($x) => (int) $x)->toArray();

                $neighbor = null;
                foreach ($byNumber as $num => $info) {
                    if ($info['row'] === $r && in_array($info['column'], $candidates, true)) {
                        $neighbor = (int) $num;
                        break;
                    }
                }

                if ($neighbor && !in_array($neighbor, $bookedSet, true) && !in_array($neighbor, $heldSet, true)) {
                    $finalSeats[] = $neighbor;
                } else {
                    return response()->json(['error' => 'Немає вільного сусіднього місця для опції «без сусіда»'], 422);
                }
            }
        }

        // 5) Розрахунок у UAH (єдиний прохід)
        $extrasPrices = (array) config('booking.extras_prices', []); // coffee=>30, ...
        $extrasMap    = (array) config('booking.extras_map', []);    // coffee=>"1", ...

        $childPct = (float) config('booking.child_discount_pct', 10);
        $soloPct  = (float) config('booking.solo_discount_pct', 20);

        // map вхідних пасажирів
        $passengersInput = [];
        foreach (($data['passengers'] ?? []) as $p) {
            $seatNum = (int) ($p['seat'] ?? 0);
            if ($seatNum <= 0) continue;
            $passengersInput[$seatNum] = [
                'category'   => $p['category']   ?? 'adult',
                'extras'     => array_values($p['extras'] ?? []),
                'first_name' => $p['first_name'] ?? null,
                'last_name'  => $p['last_name']  ?? null,
                'doc_number' => $p['doc_number'] ?? null,
            ];
        }

        // Функція визначення базової ціни для сидіння (UAH) через PriceRule
        $resolveSeatPriceUah = function (int $seat) use ($bus, $date): float {
            $q = PriceRule::query()->where('is_active', 1)
                ->where(function ($qq) use ($date) {
                    $dow = \Carbon\Carbon::parse($date)->format('D'); // Mon/Tue...
                    $qq->whereNull('days_of_week')->orWhereJsonContains('days_of_week', $dow);
                })
                ->where(function ($qq) { $now = now(); $qq->whereNull('starts_at')->orWhere('starts_at', '<=', $now); })
                ->where(function ($qq) { $now = now(); $qq->whereNull('ends_at')->orWhere('ends_at', '>=', $now); })
                ->orderByDesc('priority');

            $rules = $q->where(function ($r) use ($bus, $seat) {
                $r->where(fn ($w) => $w->where('scope_type', 'seat_number')->where('seat_number', $seat))
                    ->orWhere(fn ($w) => $w->where('scope_type', 'trip')->where('scope_id', $bus->trip_id ?? 0))
                    ->orWhere(fn ($w) => $w->where('scope_type', 'bus')->where('scope_id', $bus->id))
                    ->orWhere(fn ($w) => $w->where('scope_type', 'route')->where('scope_id', $bus->route_id));
            })->get();

            if ($rules->count()) return (float) $rules->first()->amount_uah;

            // фолбек — з seat_layout
            foreach ((array) $bus->seat_layout as $item) {
                if (($item['type'] ?? '') === 'seat' && (int) $item['number'] === $seat) {
                    return (float) ($item['price'] ?? 0);
                }
            }
            return 0.0;
        };

        $toCreate = [];
        foreach ($finalSeats as $num) {
            $num      = (int) $num;
            $baseUah  = $resolveSeatPriceUah($num);

            $cat      = $passengersInput[$num]['category'] ?? 'adult';
            $seatUah  = $cat === 'child' ? round($baseUah * (1 - $childPct / 100), 2) : $baseUah;

            $extrasKeys  = (array) ($passengersInput[$num]['extras'] ?? []);
            $extrasIds   = array_values(array_map(fn ($k) => (string) ($extrasMap[$k] ?? $k), $extrasKeys));
            $extrasUah   = 0.0;
            foreach ($extrasKeys as $k) {
                $extrasUah += (float) ($extrasPrices[$k] ?? 0);
            }

            $toCreate[] = [
                'number'      => $num,
                'category'    => $cat,
                'extras_keys' => $extrasKeys,
                'extras_ids'  => $extrasIds,
                'seat_uah'    => $seatUah,
                'extras_uah'  => round($extrasUah, 2),
                'first_name'  => $passengersInput[$num]['first_name'] ?? ($user->name ?? ''),
                'last_name'   => $passengersInput[$num]['last_name']  ?? ($user->surname ?? ''),
                'doc_number'  => $passengersInput[$num]['doc_number'] ?? null,
            ];
        }

        // SOLO: -% на дешевше місце
        if ($solo && count($toCreate) === 2) {
            $i = $toCreate[0]['seat_uah'] <= $toCreate[1]['seat_uah'] ? 0 : 1;
            $toCreate[$i]['seat_uah'] = round($toCreate[$i]['seat_uah'] * (1 - $soloPct / 100), 2);
        }

        $subtotalUah = round(array_sum(array_map(fn ($r) => $r['seat_uah'] + $r['extras_uah'], $toCreate)), 2);

        // 5.1 Промокод
        $promoCodeRaw = $data['promo_code'] ?? null;
        $discountUah  = 0.0;
        if ($promoCodeRaw) {
            $promo = PromoCode::active()->where('code', PromoCode::normalize($promoCodeRaw))->first();
            if ($promo && (!$promo->min_amount || $subtotalUah >= (float) $promo->min_amount)) {
                $discountUah = $promo->type === 'percent'
                    ? round($subtotalUah * ((float) $promo->value) / 100, 2)
                    : min(round((float) $promo->value, 2), $subtotalUah);
            }
        }

        $totalUah = max($subtotalUah - $discountUah, 0.0);
        $convert  = fn (float $uah) => round($uah * $fx, 2);

        // 6) Перевірка гонки перед створенням
        $conflict = Booking::where('bus_id', $busId)
            ->whereDate('date', $date)
            ->whereIn('seat_number', array_column($toCreate, 'number'))
            ->exists();
        if ($conflict) {
            return response()->json(['error' => 'Конкурентне бронювання: місця щойно стали недоступними'], 409);
        }

        // 7) Створення бронювань
        $orderId = (string) Str::uuid();

        $rawPassengers = $request->input('passengers', []);
        if (is_string($rawPassengers)) $rawPassengers = json_decode($rawPassengers, true) ?: [];
        $perSeat = collect($rawPassengers)->keyBy(fn ($p) => (int) ($p['seat'] ?? 0));

        $discountAssigned = 0.0;
        $lastIdx = count($toCreate) - 1;

        foreach ($toCreate as $idx => $seat) {
            $p = $perSeat->get($seat['number'], []);

            $lineUah = $seat['seat_uah'] + $seat['extras_uah'];
            $shareUah = $subtotalUah > 0 ? round($discountUah * ($lineUah / $subtotalUah), 2) : 0.0;
            if ($idx === $lastIdx) $shareUah = round($discountUah - $discountAssigned, 2);
            $discountAssigned += $shareUah;
            $lineAfterUah = max($lineUah - $shareUah, 0.0);

            $passengerPayload = [[
                'first_name'   => $p['first_name']   ?? $seat['first_name'],
                'last_name'    => $p['last_name']    ?? $seat['last_name'],
                'doc_number'   => $p['doc_number']   ?? $seat['doc_number'],
                'category'     => $p['category']     ?? $seat['category'],
                'email'        => $p['email']        ?? ($data['email'] ?? $user->email ?? null),
                'phone_number' => $p['phone_number'] ?? ($data['phone'] ?? $user->phone ?? null),
                'note'         => $p['note']         ?? null,
            ]];

            Booking::create([
                'trip_id'       => $bus->trip_id ?? 1,
                'route_id'      => $bus->route_id,
                'bus_id'        => $bus->id,
                'selected_seat' => $seat['number'],
                'seat_number'   => $seat['number'],
                'date'          => $date,
                'user_id'       => $user->id,

                'status'        => 'pending',
                'order_id'      => $orderId,

                'passengers'          => $passengerPayload,
                'additional_services' => [
                    'ids'   => $seat['extras_ids'],
                    'meta'  => [
                        'phone_alt' => $request->input('phone_alt'),
                        'comment'   => $request->input('comment'),
                    ],
                ],

                'currency_code'   => $currencyCode,
                'fx_rate'         => $fx,
                'price'           => $convert($lineAfterUah), // у вибраній валюті
                'price_uah'       => $lineAfterUah,          // фіксована сума в UAH
                'discount_amount' => $shareUah,
                'promo_code'      => $promoCodeRaw ? PromoCode::normalize($promoCodeRaw) : null,
                'pricing'         => [
                    'seat_uah'   => $seat['seat_uah'],
                    'extras_uah' => $seat['extras_uah'],
                ],
            ]);
        }

        // ---- миттєві нотифікації покупцю (НЕ квиток, а підтвердження бронювання/інвойс) ----
        try {
            $routeName = $bus->description ?? ($bus->name ?? 'Рейс');
            $sumUah    = number_format($totalUah, 2, '.', ' ');
            $link      = url('/payment/return'); // або свій "Мої замовлення"

            $msg = "✅ Бронювання створено\n"
                . "Рейс: {$routeName}\n"
                . "Дата: {$date}\n"
                . "Місця: ".implode(', ', array_column($toCreate, 'number'))."\n"
                . "Сума до оплати: {$sumUah} UAH\n"
                . "Order ID: {$orderId}\n"
                . "Посилання: {$link}";

            // Viber (якщо сервіс є в проєкті)
//            if (!empty($data['phone']) && class_exists(\App\Services\ViberSender::class)) {
//                \App\Services\ViberSender::sendInvoice($data['phone'], $msg);
//            }
//
//            // Telegram (якщо є ідентифікатор — з фронта або з профілю)
//            if (!empty($data['telegram']) && class_exists(\App\Services\TelegramSender::class)) {
//                \App\Services\TelegramSender::sendInvoice($data['telegram'], $msg);
//            }

            // Email простим текстом (без окремого Mailable)
            if (!empty($user->email)) {
                \Illuminate\Support\Facades\Mail::raw($msg, function ($m) use ($user) {
                    $m->to($user->email)->subject('Бронювання створено');
                });
            }
        } catch (\Throwable $e) {
            Log::warning('Booking notify failed', ['e' => $e->getMessage(), 'order' => $orderId]);
        }

        // 8) Звільняємо hold цього токена
        if ($holdToken) {
            SeatHold::where('token', $holdToken)->delete();
        }

        // 9) WayForPay — 1 товар на всю суму (в UAH)
        $merchantDomainName = parse_url(config('app.url'), PHP_URL_HOST) ?? config('app.url');
        $orderDate = time();

        $productName  = ['Квитки на автобус'];
        $productCount = [1];
        $productPrice = [number_format($totalUah, 2, '.', '')];
        $serviceUrl = env('WAYFORPAY_WEBHOOK_URL', route('payment.wfp.webhook')); // /api/...

        $fields = [
            'merchantAccount'      => env('WAYFORPAY_MERCHANT_LOGIN'),
            'merchantAuthType'     => 'SimpleSignature',
            'merchantDomainName'   => $merchantDomainName,
            'orderReference'       => $orderId,
            'orderDate'            => $orderDate,
            'amount'               => number_format($totalUah, 2, '.', ''),
            'currency'             => 'UAH',
            'orderTimeout'         => '49000',
            'productName'          => $productName,
            'productPrice'         => $productPrice,
            'productCount'         => $productCount,
            'clientFirstName'      => $user->name,
            'clientLastName'       => $user->surname,
            'clientEmail'          => $user->email,
            'clientPhone'          => $user->phone ?? '',
            'defaultPaymentSystem' => 'card',
//            'serviceUrl'           => route('wayforpay.webhook'),
//            'returnUrl'            => url('/payment/return'),
            'serviceUrl' => $serviceUrl, // той єдиний, що ти лишиш
            'returnUrl'  => route('payment.return'),
//            'serviceUrl' => route('payment.wfp.webhook'),     // вебхук
//            'returnUrl'  => url('/api/payment/return').'?order='.$orderId, // щоб сторінка знала що показувати
        ];
        Log::info('WFP-FORM', compact('serviceUrl'));
        $fields['merchantSignature'] = $this->generateWayForPaySignature(
            $fields,
            env('WAYFORPAY_MERCHANT_SECRET')
        );

        $form = '<form id="wayforpay" action="https://secure.wayforpay.com/pay" method="POST" accept-charset="utf-8">';
        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $form .= '<input type="hidden" name="'.htmlspecialchars($key).'[]" value="'.htmlspecialchars((string)$v).'">';
                }
            } else {
                $form .= '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars((string)$value).'">';
            }
        }
        $form .= '<button type="submit">Сплатити</button></form>';
        $form .= '<script>document.getElementById("wayforpay").submit();</script>';

        // 10) Відповідь
        return response()->json([
            'payment_form' => $form,
            'order_id'     => $orderId,
            'success'      => true,
            'ticket_preview' => [
                'route'      => $bus->description ?? '',
                'date'       => $date,
                'seats'      => array_map(fn ($s) => $s['number'], $toCreate),
                'price_uah'  => $totalUah,
                'price'      => $convert($totalUah),
                'currency'   => $currencyCode,
                'name'       => $user->name,
                'surname'    => $user->surname,
                'bus'        => $bus->name,
            ],
        ]);
    }
}
