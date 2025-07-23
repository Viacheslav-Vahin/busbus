<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use WayForPay\SDK\Domain\Product;
use WayForPay\SDK\Credential\AccountSecretCredential;
use WayForPay\SDK\Request\InvoiceRequest;
use WayForPay\SDK\Wizard\InvoiceWizard;
use WayForPay\SDK\Domain\Client;
use WayForPay\SDK\Collection\ProductCollection;
use WayForPay\SDK\Response\Response;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'bus_id' => 'required|exists:buses,id',
            'date' => 'required|date',
            'selected_seat' => 'required|integer',
            'seat_number' => 'required|integer',
        ]);

        $existingBooking = Booking::where('bus_id', $validatedData['bus_id'])
            ->where('seat_number', $validatedData['selected_seat'])
            ->whereDate('date', $validatedData['date'])
            ->first();

        if ($existingBooking) {
            return back()->withErrors(['selected_seat' => 'Це місце вже заброньовано. Будь ласка, виберіть інше.']);
        }

        Booking::create($validatedData);

        return redirect()->route('booking.index')->with('success', 'Бронювання створено успішно!');
    }

    public function getBusInfo($tripId, Request $request)
    {
        $date = $request->query('date');
        $bus = Bus::findOrFail($tripId);
        $data = $bus->seat_layout;
        $bookedSeats = Booking::where('bus_id', $bus->id)
            ->where('date', $date)
            ->pluck('selected_seat')
            ->toArray();

        return response()->json([
            'bus' => [
                'id' => $bus->id,
                'seat_layout' => $data,
                'name' => $bus->name,
            ],
            'booked_seats' => $bookedSeats,
        ]);
    }

    public function bookSeat(Request $request)
    {
        $data = $request->all();

        $user = Auth::user();
        if (!$user) {
            $user = User::where('email', $data['email'])->first();
            if (!$user) {
                $user = User::create([
                    'name' => $data['name'],
                    'surname' => $data['surname'] ?? '',
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? '',
                    'password' => Hash::make($data['password']),
                ]);
                Auth::login($user);
            } else {
                if (!Hash::check($data['password'], $user->password)) {
                    return response()->json(['error' => 'Пароль невірний'], 422);
                }
                Auth::login($user);
            }
        }

        // Перевіряємо, чи місця ще доступні
        foreach ($data['seats'] as $seat) {
            $alreadyBooked = Booking::where('bus_id', $data['trip_id'])
                ->where('date', $data['date'])
                ->where('selected_seat', $seat)
                ->exists();
            if ($alreadyBooked) {
                return response()->json(['error' => "Місце $seat вже зайняте!"], 422);
            }
        }

        // Розрахунок суми (за всі місця)
        $bus = Bus::findOrFail($data['trip_id']);
        $seatLayout = collect($bus->seat_layout);
        $sum = 0;
        foreach ($data['seats'] as $seat) {
            $seatData = $seatLayout->firstWhere('number', (string)$seat);
            $sum += intval($seatData['price'] ?? 0);
        }

        // ГЕНЕРУЄМО інвойс для LiqPay/WayForPay
        $orderId = uniqid('maxbus_', true);

        // Зберігаємо бронь зі статусом "очікує оплати"
        foreach ($data['seats'] as $seat) {
            Booking::create([
                'trip_id' => $bus->trip_id ?? 1,
                'route_id' => $bus->route_id,
                'bus_id' => $bus->id,
                'selected_seat' => $seat,
                'seat_number' => $seat,
                'date' => $data['date'],
                'user_id' => $user->id,
                'price' => $seatLayout->firstWhere('number', (string)$seat)['price'] ?? 0,
                'status' => 'pending',
                'order_id' => $orderId
            ]);
        }

        $client = new Client(
            trim($user->name . ' ' . $user->surname),
            $user->email,
            $user->phone ?? ''
        );

        $products = new ProductCollection([
            new Product('Квиток на автобус', count($data['seats']), $sum)
        ]);

        $credential = new AccountSecretCredential(
            env('WAYFORPAY_MERCHANT_LOGIN'),
            env('WAYFORPAY_MERCHANT_SECRET')
        );

        $wizard = new InvoiceWizard($credential);

        $wizard
            ->setMerchantDomainName(config('app.url'))
            ->setOrderReference($orderId)
            ->setOrderDate(new \DateTime())
            ->setAmount($sum)
            ->setCurrency('UAH')
            ->setProducts($products)
            ->setClient($client);

        $request = $wizard->getRequest();

        $parsedUrl = parse_url(config('app.url'));
        $merchantDomainName = $parsedUrl['host'] ?? config('app.url');

        $fields = [
            'merchantAccount' => env('WAYFORPAY_MERCHANT_LOGIN'),
            'merchantAuthType' => 'SimpleSignature',
            'merchantDomainName' => $merchantDomainName,
            'merchantSignature' => env('WAYFORPAY_MERCHANT_SECRET'),
            'orderReference' => $orderId,
            'orderDate' => time(),
            'amount' => $sum,
            'currency' => 'UAH',
            'orderTimeout' => '49000',
            'productName' => ['Квиток на автобус'],
            'productPrice' => [$sum],
            'productCount' => [count($data['seats'])],
            'clientFirstName' => $user->name,
            'clientLastName' => $user->surname,
            'clientEmail' => $user->email,
            'clientPhone' => $user->phone ?? '',
            'defaultPaymentSystem' => 'card',
        ];

//        dump($fields);


        $form = '<form id="wayforpay" action="https://secure.wayforpay.com/pay" method="POST">';
        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $form .= '<input type="hidden" name="' . htmlspecialchars($key) . '[]" value="' . htmlspecialchars($v) . '">';
                }
            } else {
                $form .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
            }
        }
        $form .= '<button type="submit">Сплатити</button></form>';
        $form .= '<script>document.getElementById("wayforpay").submit();</script>';


        return response()->json([
            'payment_form' => $form,
            'order_id' => $orderId,
            'success' => true,
            'ticket_preview' => [
                'route'   => $bus->description ?? '',
                'date'    => $data['date'],
                'seats'   => $data['seats'],
                'price'   => $sum,
                'name'    => $user->name,
                'surname' => $user->surname,
                'bus'     => $bus->name,
            ]
        ]);
    }
}
