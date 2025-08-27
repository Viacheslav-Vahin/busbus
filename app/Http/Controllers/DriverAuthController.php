<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverAuthController extends Controller
{
    public function show()
    {
        // Якщо вже залогінений як водій — одразу на сканер
        if (Auth::check() && Auth::user()->hasRole('driver')) {
            return redirect()->route('driver.scan');
        }

        return view('driver.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
        ]);

        $cred = $request->only('email', 'password');

        if (!Auth::attempt($cred, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Невірний логін або пароль'])
                ->withInput($request->except('password'));
        }

        $user = Auth::user();

        if (!$user->hasRole('driver')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'Цей акаунт не має ролі водія.'])
                ->withInput($request->except('password'));
        }

        $request->session()->regenerate();

        return redirect()->intended(route('driver.scan'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('driver.login');
    }
}
