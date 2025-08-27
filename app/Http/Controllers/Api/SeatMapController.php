<?php
////// app/Http/Controllers/Api/SeatMapController.php
////namespace App\Http\Controllers\Api;
////
////use App\Http\Controllers\Controller;
////use App\Models\Trip;
////use App\Models\Bus;
////use App\Models\BusSeat;
////use App\Models\Booking;
////use App\Services\SeatHoldService;
////use App\Services\SeatNeighborService;
////use Illuminate\Http\Request;
////use Illuminate\Support\Str;
////
////class SeatMapController extends Controller
////{
////    public function seats(Trip $trip)
////    {
////        $bus = Bus::findOrFail($trip->bus_id);
////
////        // зайняті: paid/pending + активні hold
////        $occupied = Booking::where('trip_id',$trip->id)
////            ->whereIn('status',['hold','pending','paid'])
////            ->where(function($q){ $q->whereNull('held_until')->orWhere('held_until','>',now()); })
////            ->pluck('seat_number')
////            ->map(fn($n)=>(string)$n)
////            ->toArray();
////
////        // seat_layout уже синханий (exportToSeatLayout)
////        $layout = is_string($bus->seat_layout) ? json_decode($bus->seat_layout,true) : ($bus->seat_layout ?? []);
////
////        return response()->json([
////            'layout' => $layout,
////            'occupied' => $occupied,
////        ]);
////    }
////
////    public function hold(Request $req, Trip $trip, SeatHoldService $holds, SeatNeighborService $neighbors)
////    {
////        $req->validate(['seatNumber'=>'required','solo'=>'boolean','currency'=>'nullable|string']);
////        $seat = (string) $req->seatNumber;
////        $currency = $req->input('currency','UAH');
////
////        $busId = $trip->bus_id;
////        $main = $holds->hold($trip->id, $busId, $seat, $currency);
////
////        if (!$main['ok']) return response()->json(['ok'=>false,'reason'=>'taken'], 409);
////
////        $orderId = $main['order_id'];
////        $resp = ['ok'=>true,'order_id'=>$orderId,'items'=>[
////            ['seat'=>$seat,'token'=>$main['token'],'expires_at'=>$main['expires_at']]
////        ]];
////
////        if ($req->boolean('solo')) {
////            if ($nb = $neighbors->neighborFor($busId, $seat)) {
////                $nbHold = $holds->hold($trip->id, $busId, $nb, $currency);
////                if ($nbHold['ok']) {
////                    // позначимо як solo-companion
////                    Booking::where('hold_token',$nbHold['token'])->update([
////                        'is_solo_companion'=>true,
////                        'order_id'=>$orderId,    // одна група
////                        'discount_pct'=>20.00,   // можна читати з settings
////                    ]);
////                    $resp['items'][] = ['seat'=>$nb,'token'=>$nbHold['token'],'expires_at'=>$nbHold['expires_at'],'solo_companion'=>true];
////                } else {
////                    // сусід уже зайнятий — відпускаємо головний hold
////                    $holds->release($main['token']);
////                    return response()->json(['ok'=>false,'reason'=>'solo_unavailable'], 409);
////                }
////            } else {
////                $holds->release($main['token']);
////                return response()->json(['ok'=>false,'reason'=>'no_neighbor'], 422);
////            }
////        }
////
////        return response()->json($resp);
////    }
////
////    public function prolong(Request $req, SeatHoldService $holds)
////    {
////        $ok = $holds->prolong($req->string('token'));
////        return $ok ? response()->json(['ok'=>true]) : response()->json(['ok'=>false],404);
////    }
////
////    public function release(Request $req, SeatHoldService $holds)
////    {
////        $holds->release($req->string('token'));
////        return response()->json(['ok'=>true]);
////    }
////
////    public function checkout(Request $req)
////    {
////        // тут ти збереш дані пасажирів, перерахуєш ціну (враховуючи discount_pct у companion),
////        // створиш інвойс/посилання на оплату, після успіху -> status=paid, paid_at=now()
////        // і знімеш redis-ключі (release не потрібен, бо booking уже "paid")
////        return response()->json(['ok'=>true,'redirect'=>route('payment.mock')]);
////    }
////}
//
//
//// app/Http/Controllers/Api/SeatMapController.php
//namespace App\Http\Controllers\Api;
//
//use App\Http\Controllers\Controller;
//use App\Models\Booking;
//use App\Models\SeatHold;
//use App\Models\Trip; // або твоя модель Trip
//use Illuminate\Http\Request;
//use Illuminate\Support\Str;
//use Illuminate\Support\Carbon;
//use Illuminate\Support\Facades\DB;
//
//class SeatMapController extends Controller
//{
//    // GET /api/trips/{trip}/seats?date=YYYY-MM-DD
//    public function seats($tripId, Request $r) {
//        $date = $r->query('date');
//        $now = now();
//
//        $booked = Booking::query()
//            ->where('trip_id', $tripId)
//            ->whereDate('date', $date)
//            ->pluck('seat_number') // або масиви — підлаштуй під свою структуру
//            ->toArray();
//
//        $held = SeatHold::query()
//            ->where('trip_id', $tripId)->whereDate('date', $date)
//            ->where('expires_at', '>', $now)
//            ->pluck('seat_number')->toArray();
//
//        return response()->json([
//            'booked_seats' => array_values(array_unique($booked)),
//            'held_seats'   => array_values(array_unique($held)),
//        ]);
//    }
//
//    // POST /api/trips/{trip}/hold
//    public function hold($tripId, Request $r) {
//        $data = $r->validate([
//            'date'  => 'required|date',
//            'seats' => 'array|min:1',
//            'seats.*' => 'string',
//            'token' => 'nullable|string|max:64',
//            'solo'  => 'boolean',
//        ]);
//
//        $date = $data['date'];
//        $seats = $data['seats'] ?? [];
//        $token = $data['token'] ?? Str::random(32);
//        $ttl = 120; // сек
//        $now = now();
//
//        // очищаємо протухлі
//        SeatHold::where('expires_at','<=',$now)->delete();
//
//        // перевірка конфліктів: зайняті або утримані іншими
//        $conflicts = SeatHold::where('trip_id',$tripId)->whereDate('date',$date)
//            ->where('expires_at','>', $now)
//            ->whereNot('token',$token)
//            ->whereIn('seat_number',$seats)
//            ->pluck('seat_number')->toArray();
//
//        $booked = Booking::where('trip_id',$tripId)->whereDate('date',$date)
//            ->whereIn('seat_number',$seats)->pluck('seat_number')->toArray();
//
//        if ($conflicts || $booked) {
//            $heldNow = SeatHold::where('trip_id',$tripId)->whereDate('date',$date)
//                ->where('expires_at','>', $now)->pluck('seat_number')->toArray();
//            return response()->json(['message'=>'conflict','held_seats'=>$heldNow], 409);
//        }
//
//        // оновлюємо набір для цього токена (upsert логіка)
//        DB::transaction(function() use ($tripId,$date,$seats,$token,$now,$ttl){
//            // стираємо попередні місця токена
//            SeatHold::where('trip_id',$tripId)->whereDate('date',$date)->where('token',$token)->delete();
//            // пишемо нові
//            foreach ($seats as $s) {
//                SeatHold::create([
//                    'trip_id'=>$tripId,'date'=>$date,'seat_number'=>$s,
//                    'token'=>$token,'expires_at'=>$now->copy()->addSeconds($ttl),
//                ]);
//            }
//        });
//
//        $heldForOthers = SeatHold::where('trip_id',$tripId)->whereDate('date',$date)
//            ->where('expires_at','>', $now)->whereNot('token',$token)->pluck('seat_number')->toArray();
//
//        return response()->json([
//            'token' => $token,
//            'expires_at' => $now->addSeconds($ttl)->toIso8601String(),
//            'held_seats' => array_values(array_unique($heldForOthers)),
//        ]);
//    }
//
//    // POST /api/hold/prolong
//    public function prolong(Request $r) {
//        $token = $r->input('token');
//        if (!$token) return response()->json([], 400);
//        $ttl = 120; $now = now(); $new = $now->copy()->addSeconds($ttl);
//
//        $updated = SeatHold::where('token',$token)->update(['expires_at'=>$new]);
//        if (!$updated) return response()->json([], 404);
//
//        return response()->json(['expires_at'=>$new->toIso8601String()]);
//    }
//
//    // POST /api/hold/release
//    public function release(Request $r) {
//        $token = $r->input('token');
//        if ($token) SeatHold::where('token',$token)->delete();
//        return response()->noContent();
//    }
//
//    // POST /api/checkout — якщо використовуєш
//    public function checkout(Request $r) { /* ... */ }
//}


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeatHold;
use App\Models\Bus;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class SeatMapController extends Controller
{
    // GET /api/trips/{trip}/seats?date=YYYY-MM-DD
    public function seats($trip, Request $request)
    {
        $bus = Bus::findOrFail((int)$trip);
        $date = $request->query('date');
        $now = now();

        $booked = Booking::where('bus_id', $bus->id)
            ->when($date, fn($q) => $q->whereDate('date', $date))
            ->pluck('seat_number')->map(fn($n) => (string)$n)->toArray();

        $held = SeatHold::where('bus_id', $bus->id)
            ->when($date, fn($q) => $q->whereDate('date', $date))
            ->where('expires_at', '>', $now)
            ->pluck('seat_number')->map(fn($n) => (string)$n)->toArray();

        return response()->json([
            'seat_layout' => $bus->seat_layout,
            // для сумісності з фронтом — обидві назви:
            'booked_seats' => $booked,
            'held_seats' => $held,
            'booked' => $booked,
            'held' => $held,
        ]);
    }

    // POST /api/trips/{trip}/hold
    public function hold(Request $request, $trip /* тут trip = bus_id */)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'seats' => ['required', 'array', 'min:1'],
            'seats.*' => ['integer'],
            'solo' => ['nullable', 'boolean'],
            'token' => ['nullable', 'string', 'max:64'], // ✅ приймаємо токен від фронту
        ]);

        $busId = (int)$trip;
        $date = $data['date'];
        $seats = array_values(array_unique($data['seats']));
        $token = $data['token'] ?? Str::random(40);       // ✅ перевикористовуємо, якщо передали
        $ttl = (int)config('booking.hold_ttl_seconds', 600);
        $now = now();
        $exp = $now->copy()->addSeconds($ttl);

        // прибираємо прострочені
        SeatHold::where('expires_at', '<=', $now)->delete();

        // конфлікт з бронюваннями
        $booked = Booking::where('bus_id', $busId)
            ->whereDate('date', $date)
            ->whereIn('seat_number', $seats)
            ->exists();
        if ($booked) {
            return response()->json(['message' => 'conflict_booked'], 409);
        }

        // конфлікт з hold інших токенів (не рахуємо власний токен)
        $heldByOthers = SeatHold::where('bus_id', $busId)
            ->whereDate('date', $date)
            ->where('expires_at', '>', $now)
            ->where('token', '!=', $token)
            ->whereIn('seat_number', $seats)
            ->exists();
        if ($heldByOthers) {
            $heldNow = SeatHold::where('bus_id', $busId)
                ->whereDate('date', $date)
                ->where('expires_at', '>', $now)
                ->pluck('seat_number')->map(fn($n) => (string)$n)->toArray();
            return response()->json(['message' => 'conflict_held', 'held_seats' => $heldNow], 409);
        }

        // upsert: підміняємо набір місць для цього токена
        DB::transaction(function () use ($busId, $date, $token, $seats, $exp) {
            SeatHold::where('bus_id', $busId)
                ->whereDate('date', $date)
                ->where('token', $token)
                ->delete();

            foreach ($seats as $n) {
                SeatHold::create([
                    'bus_id' => $busId,
                    'date' => $date,
                    'seat_number' => (int)$n,
                    'token' => $token,
                    'expires_at' => $exp,
                ]);
            }
        });

        // список місць, що утримуються іншими (щоб «посіріти» на схемі)
        $heldForOthers = SeatHold::where('bus_id', $busId)
            ->whereDate('date', $date)
            ->where('expires_at', '>', $now)
            ->where('token', '!=', $token)
            ->pluck('seat_number')->map(fn($n) => (string)$n)->toArray();

        return response()->json([
            'token' => $token,
            'expires_at' => $exp->toIso8601String(),
            'ttl_seconds' => $ttl,
            'held_seats' => $heldForOthers,
        ]);
    }

    // POST /api/hold/prolong
    public function prolong(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        $ttl = (int)config('booking.hold_ttl_seconds', 600);
        $new = now()->addSeconds($ttl);

        // (поблажливо) подовжуємо навіть якщо трошки запізнився
        $updated = SeatHold::where('token', $data['token'])
            ->update(['expires_at' => $new]);

        if (!$updated) {
            return response()->json(['message' => 'expired_or_not_found'], 404);
        }

        return response()->json([
            'ok' => true,
            'expires_at' => $new->toIso8601String(),
            'ttl_seconds' => $ttl,
        ]);
    }

    // POST /api/hold/release
    public function release(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        SeatHold::where('token', $data['token'])->delete();
        return response()->json(['ok' => true]);
    }
}

