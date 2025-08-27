<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('driver.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required'],
        ]);

        // web-guard
        if (Auth::guard('web')->attempt($credentials, true)) {
            $user = Auth::user();

            if (! $user->hasRole('driver')) {
                Auth::logout();
                return back()->withErrors(['email' => 'У цього користувача немає ролі "driver".']);
            }

            $request->session()->regenerate();
            return redirect()->intended(route('driver.scan'));
        }

        return back()->withErrors(['email' => 'Невірні облікові дані.']);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('driver.login');
    }
}
