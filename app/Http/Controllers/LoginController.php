<?php

namespace App\Http\Controllers;

use App\Actions\AuthenticateCenterUser;
use App\Http\Requests\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __invoke(): View
    {
        return view('auth.login');
    }

    public function authenticate(LoginRequest $request, AuthenticateCenterUser $authenticate): RedirectResponse
    {
        $user = $authenticate($request->string('identifier')->toString(), $request->string('password')->toString());

        if (! $user) {
            return back()->withInput($request->safe()->only('identifier'))->withErrors(['identifier' => 'بيانات الدخول غير صحيحة أو الحساب غير نشط.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'تم تسجيل الخروج.');
    }
}
