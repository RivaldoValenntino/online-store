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

                <input type="password" wire:model.live.debounce.500ms="password" placeholder="••••••••"
                    class="{{ $this->inputClass('password') }}">

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

        <p class="mt-5 text-center text-sm">
            Belum punya akun?
            <a href="{{ route('customer.register') }}" wire:navigate class="font-bold text-primary">
                Daftar
            </a>
        </p>
        <div class="modal-backdrop" wire:click="$set('showLoginRequired', false)"></div>
    </div>
</div>
