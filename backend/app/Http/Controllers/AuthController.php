<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Handle administrator login.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower(trim($validated['email']));

        // Limit login attempts per email and IP address.
        $throttleKey = Str::transliterate(
            $email . '|' . $request->ip()
        );

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => 'Terlalu banyak percobaan login. Silakan coba lagi.',
                'retry_after' => $seconds,
            ], 429)->header('Retry-After', (string) $seconds);
        }

        // Validate administrator credentials.
        $credentials = [
            'email' => $email,
            'password' => $validated['password'],
        ];

        if (! Auth::guard('web')->attempt($credentials)) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => 'Email atau password tidak sesuai.',
            ], 422);
        }

        // Prevent session fixation.
        $request->session()->regenerate();

        // Reset login attempts after successful authentication.
        RateLimiter::clear($throttleKey);

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => Auth::guard('web')->user()->only([
                'id',
                'name',
                'email',
            ]),
        ]);
    }

    /**
     * Handle administrator logout.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
}
