<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google and authenticate.
     */
    public function callback(): RedirectResponse
    {
        try {
            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::error('Google OAuth Login Error: ' . $e->getMessage());

            return redirect()
                ->route('customer.login')
                ->with('flash.type', 'error')
                ->with('flash.title', 'Gagal Masuk')
                ->with('flash.message', 'Gagal melakukan autentikasi dengan akun Google. Silakan coba kembali.');
        }

        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();
        $name = $googleUser->getName() ?: ($googleUser->getNickname() ?: 'Customer');
        $avatar = $googleUser->getAvatar();

        if (empty($email)) {
            return redirect()
                ->route('customer.login')
                ->with('flash.type', 'error')
                ->with('flash.title', 'Gagal Masuk')
                ->with('flash.message', 'Akun Google Anda tidak memiliki alamat email publik.');
        }

        // Cari customer berdasarkan google_id terlebih dahulu
        $customer = Customer::where('google_id', $googleId)->first();

        if (! $customer) {
            // Jika belum ada google_id yang cocok, cari berdasarkan email
            $customer = Customer::where('email', $email)->first();

            if ($customer) {
                // Tautkan google_id ke akun yang sudah ada
                $customer->google_id = $googleId;
                if (empty($customer->avatar)) {
                    $customer->avatar = $avatar;
                }
                if (empty($customer->email_verified_at)) {
                    $customer->email_verified_at = now();
                }
                $customer->save();
            } else {
                // Buat customer baru
                $customer = Customer::create([
                    'name' => $name,
                    'email' => $email,
                    'google_id' => $googleId,
                    'avatar' => $avatar,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'password' => null,
                ]);
            }
        } else {
            // Update avatar jika ada perubahan atau belum ada
            if ($avatar && $customer->avatar !== $avatar) {
                $customer->avatar = $avatar;
            }
            if (empty($customer->email_verified_at)) {
                $customer->email_verified_at = now();
            }
            $customer->save();
        }

        // Periksa apakah akun customer aktif
        if (! $customer->is_active) {
            return redirect()
                ->route('customer.login')
                ->with('flash.type', 'error')
                ->with('flash.title', 'Akun Nonaktif')
                ->with('flash.message', 'Akun kamu sedang dinonaktifkan. Silakan hubungi customer service.');
        }

        Auth::guard('customer')->login($customer, remember: true);

        request()->session()->regenerate();

        session()->flash('flash.type', 'success');
        session()->flash('flash.title', 'Selamat datang kembali');
        session()->flash('flash.message', 'Halo ' . $customer->name . ', kamu berhasil masuk dengan akun Google.');

        return redirect()->intended(route('store.home'));
    }
}
