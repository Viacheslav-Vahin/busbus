<?php
// app/Http/Controllers/DriverMobileAuthController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DriverMobileAuthController extends Controller
{
    public function login(Request $r)
    {
        $data = $r->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        /** @var User $user */
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['ok'=>false,'error'=>'bad_credentials'], 422);
        }
        if (! $user->hasRole('driver')) {
            return response()->json(['ok'=>false,'error'=>'forbidden'], 403);
        }
        $token = $user->createToken('driver-mobile')->plainTextToken;
        return response()->json(['ok'=>true, 'token'=>$token, 'user'=>[
            'id'=>$user->id, 'name'=>$user->name, 'email'=>$user->email,
        ]]);
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()?->delete();
        return response()->json(['ok'=>true]);
    }
}
