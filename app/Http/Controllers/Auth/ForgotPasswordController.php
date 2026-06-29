<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Send the reset link regardless of whether the email exists — no enumeration.
        Password::sendResetLink($request->only('email'));

        return back()->with(
            'status',
            __('If that address is registered, you\'ll receive a password reset link shortly.')
        );
    }
}
