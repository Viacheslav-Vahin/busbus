<?php
// app/Http/Controllers/CabinetController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Storage;

class CabinetController extends Controller
{
    public function index() { return redirect()->route('cabinet.orders'); }

    public function orders(Request $r)
    {
        $orders = Booking::with('trip.route')
            ->where('user_id', $r->user()->id)
            ->latest('date')->paginate(20);

        return view('cabinet.orders', compact('orders'));
    }

    public function ticket(Booking $booking)
    {
        $this->authorize('view', $booking); // policy: only owner
        $path = $booking->ticket_pdf_path;
        abort_unless($path && Storage::exists($path), 404);
        return response()->download(Storage::path($path));
    }

    public function profile() { return view('cabinet.profile'); }

    public function updateProfile(Request $r)
    {
        $data = $r->validate([
            'name'=>'required|string|max:120',
            'surname'=>'nullable|string|max:120',
            'phone'=>'nullable|string|max:32',
            'password'=>'nullable|string|min:6|confirmed',
        ]);
        $u = $r->user();
        if (!empty($data['password'])) $u->password = bcrypt($data['password']);
        unset($data['password']);
        $u->fill($data)->save();
        return back()->with('ok','Збережено');
    }
}
