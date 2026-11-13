<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.auth')] #[Title('Login')] class extends Component {
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public ?string $loginError = null;

    protected function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ];
    }

    protected function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ];
    }

    public function updated(string $property): void
    {
        $this->loginError = null;

        $this->validateOnly($property);
    }

    public function inputClass(string $field): string
    {
        $base = 'input input-bordered rounded-2xl w-full bg-base-100 transition
            focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20';

        if ($this->getErrorBag()->has($field)) {
            return $base . ' input-error border-error focus:border-error focus:ring-error/20';
        }

        return $base;
    }

    public function login(): void
    {
        $this->loginError = null;

        $this->validate();

        $success = Auth::guard('customer')->attempt(
            [
                'email' => $this->email,
                'password' => $this->password,
                'is_active' => true,
            ],
            $this->remember,
        );

        if (!$success) {
            $this->loginError = 'Email atau password salah.';
            return;
        }

        session()->regenerate();

        $customer = Auth::guard('customer')->user();

        // Tampilkan prompt ajakan berlangganan promo jika belum pernah memilih
        if (is_null($customer->email_marketing) && is_null($customer->in_app_marketing)) {
            session()->flash('show_promo_subscribe_modal', true);
        }

        session()->flash('flash.type', 'success');
        session()->flash('flash.title', 'Selamat datang kembali');
        session()->flash('flash.message', 'Halo ' . $customer->name . ', kamu berhasil masuk ke akun.');

        $this->redirectRoute('store.home', navigate: true);
    }
};
?>

<div class="min-h-screen bg-base-200 p-4">
    {{-- Fullscreen Loading --}}
    <div wire:loading.flex wire:target="login"
        class="fixed inset-0 z-[9999] items-center justify-center bg-base-100/70 backdrop-blur-sm">
        <div class="flex flex-col items-center gap-3 rounded-3xl bg-base-100 p-6 shadow-xl">
            <span class="loading loading-spinner loading-lg text-primary"></span>
            <p class="text-sm font-semibold text-base-content/70">
                Loading...
            </p>
        </div>
    </div>

    {{-- Toast Error --}}
    @if ($loginError)
        <div class="toast toast-top toast-center z-[9998]">
            <div class="alert alert-error alert-soft rounded-2xl shadow-lg">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                <span class="text-sm">{{ $loginError }}</span>

                <button type="button" wire:click="$set('loginError', null)" class="btn btn-ghost btn-xs btn-circle">
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                </button>
            </div>
        </div>
    @endif

    <div class="rounded-[2rem] bg-base-100 p-5 shadow-sm">
        <div class="mb-5">
            <a href="{{ route('store.home') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>
        </div>

        <h1 class="text-2xl font-black">Masuk</h1>

        <p class="mt-1 text-sm text-base-content/60">
            Login untuk lanjut belanja dan checkout.
        </p>

        <form wire:submit="login" novalidate class="mt-6 space-y-3">
            {{-- Email --}}
            <div>
                <div class="mb-1">
                    <span class="text-sm font-medium">Email</span>
                </div>

                <input type="email" wire:model.live.debounce.700ms="email" placeholder="nama@email.com"
                    class="{{ $this->inputClass('email') }}">

                <div class="mt-1 min-h-[16px]">
                    @error('email')
                        <span class="block text-[11px] leading-none text-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- Password --}}
            <div>
                <div class="mb-1 flex items-center justify-between">
                    <span class="text-sm font-medium">Password</span>

                    <a href="{{ route('password.request') }}" wire:navigate class="text-xs font-bold text-primary">
                        Lupa password?
                    </a>
                </div>

                <div class="relative" x-data="{ show: false }">
                    <input :type="show ? 'text' : 'password'" wire:model.live.debounce.500ms="password"
                        placeholder="••••••••" class="{{ $this->inputClass('password') }} pr-11">

                    <button type="button" @click="show = !show" tabindex="-1"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-base-content/50 hover:text-base-content">
                        <x-heroicon-o-eye class="h-5 w-5" x-show="!show" x-cloak />
                        <x-heroicon-o-eye-slash class="h-5 w-5" x-show="show" x-cloak />
                    </button>
                </div>

                <div class="mt-1 min-h-[16px]">
                    @error('password')
                        <span class="block text-[11px] leading-none text-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- Remember --}}
            <label class="mt-1 flex cursor-pointer items-center gap-3 text-sm">
                <input type="checkbox" wire:model="remember" class="checkbox checkbox-primary checkbox-sm">
                <span>Ingat saya</span>
            </label>

            <button type="submit" wire:loading.attr="disabled" wire:target="login"
                class="btn btn-primary w-full rounded-full mt-4">
                <span wire:loading.remove wire:target="login">
                    Masuk
                </span>

                <span wire:loading wire:target="login" class="flex items-center gap-2">
                    <span class="loading loading-spinner loading-sm"></span>
                    Memproses...
                </span>
            </button>
        </form>

        <div class="divider my-4 text-xs text-base-content/50">atau</div>

        <a href="{{ route('auth.google') }}"
            class="btn btn-outline w-full rounded-full flex items-center justify-center gap-3 border-base-300 hover:bg-base-200 hover:border-base-300 text-base-content">
            <svg class="h-5 w-5" viewBox="0 0 24 24">
                <path fill="#4285F4"
                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                <path fill="#34A853"
                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                <path fill="#FBBC05"
                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                <path fill="#EA4335"
                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
            </svg>
            <span class="font-semibold text-sm">Masuk dengan Google</span>
        </a>

        <p class="mt-5 text-center text-sm">
            Belum punya akun?
            <a href="{{ route('customer.register') }}" wire:navigate class="font-bold text-primary">
                Daftar
            </a>
        </p>
        <div class="modal-backdrop" wire:click="$set('showLoginRequired', false)"></div>
    </div>
</div>
