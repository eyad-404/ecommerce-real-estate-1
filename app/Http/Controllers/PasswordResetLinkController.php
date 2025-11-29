<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use App\Services\Logger;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        $logger = Logger::getInstance();

    try {
        $logger->info('Forgot-password page accessed', [
            'accessed_by' => auth()->id() ?? null,
        ]);
    } catch (\Exception $e) {
        $logger->error('Forgot-password page logging failed', [
            'error' => $e->getMessage(),
        ]);
    }
        
        return view('myauth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $logger = Logger::getInstance();

    $request->validate([
        'email' => ['required', 'email'],
    ]);

    try {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status == Password::RESET_LINK_SENT) {
            $logger->info('Password reset link sent', [
                'email' => $request->email,
                'sent_by' => auth()->id() ?? null,
            ]);

            return back()->with('status', __($status));
        } else {
            $logger->warning('Password reset link failed', [
                'email' => $request->email,
                'status' => $status,
            ]);

            return back()->withInput($request->only('email'))
                         ->withErrors(['email' => __($status)]);
        }
    } catch (\Exception $e) {
        $logger->error('Password reset link exception', [
            'email' => $request->email,
            'error' => $e->getMessage(),
        ]);

        return back()->withInput($request->only('email'))
                     ->withErrors(['email' => 'Something went wrong: '.$e->getMessage()]);
    }
}
}
