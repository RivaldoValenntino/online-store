<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.auth')] #[Title('Lupa Password')] class extends Component {
    public string $email = '';

    public ?string $forgotError = null;
    public ?string $forgotSuccess = null;

    protected function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:customers,email'],
        ];
    }

    protected function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.exists' => 'Email tidak terdaftar.',
        ];
    }

    public function updated(string $property): void
    {
        $this->forgotError = null;
        $this->forgotSuccess = null;

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

    public function sendResetLink(): void
    {
        $this->forgotError = null;
        $this->forgotSuccess = null;

        $this->validate();

        $status = Password::broker('customers')->sendResetLink([
            'email' => $this->email,
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            $this->forgotError = match ($status) {
                Password::INVALID_USER => 'Email tidak terdaftar.',
                default => 'Gagal mengirim link reset password. Coba lagi nanti.',
            };

            return;
        }

        $this->forgotSuccess = 'Link reset password sudah dikirim ke email kamu.';
    }
};
?>

<div class="min-h-screen bg-base-200 p-4">
    {{-- Fullscreen Loading --}}
    <div wire:loading.flex wire:target="sendResetLink"
        class="fixed inset-0 z-[9999] items-center justify-center bg-base-100/70 backdrop-blur-sm">
        <div class="flex flex-col items-center gap-3 rounded-3xl bg-base-100 p-6 shadow-xl">
            <span class="loading loading-spinner loading-lg text-primary"></span>

            <p class="text-sm font-semibold text-base-content/70">
                Mengirim link reset...
            </p>
        </div>
    </div>

    {{-- Toast Error --}}
    @if ($forgotError)
        <div x-data="{ show: true }" x-init="setTimeout(() => {
            show = false
            $wire.set('forgotError', null)
        }, 3000)" x-show="show" x-transition
            class="toast toast-top toast-center z-[9998]">
            <div class="alert alert-error alert-soft rounded-2xl shadow-lg">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5" />

                <span class="text-sm">{{ $forgotError }}</span>

                <button type="button" wire:click="$set('forgotError', null)" class="btn btn-ghost btn-xs btn-circle">
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                </button>
            </div>
        </div>
    @endif

    {{-- Toast Success --}}
    @if ($forgotSuccess)
        <div x-data="{ show: true }" x-init="setTimeout(() => {
            show = false
            $wire.set('forgotSuccess', null)
        }, 3500)" x-show="show" x-transition
            class="toast toast-top toast-center z-[9998]">
            <div class="alert alert-success alert-soft rounded-2xl shadow-lg">
                <x-heroicon-o-check-circle class="h-5 w-5" />

                <span class="text-sm">{{ $forgotSuccess }}</span>

                <button type="button" wire:click="$set('forgotSuccess', null)" class="btn btn-ghost btn-xs btn-circle">
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                </button>
            </div>
        </div>
    @endif

    <div class="rounded-[2rem] bg-base-100 p-5 shadow-sm">
        <div class="mb-5">
            <a href="{{ route('customer.login') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>
        </div>

        <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
            <x-heroicon-o-key class="h-8 w-8" />
        </div>

        <h1 class="text-2xl font-black">Lupa Password</h1>

        <p class="mt-1 text-sm text-base-content/60">
            Masukkan email akun kamu. Kami akan kirim link untuk membuat password baru.
        </p>

        <form wire:submit="sendResetLink" novalidate class="mt-6 space-y-3">
            <div>
                <div class="mb-1">
                    <span class="text-sm font-medium">Email</span>
                </div>

                <input type="email" wire:model.live.debounce.700ms="email" placeholder="nama@email.com"
                    class="{{ $this->inputClass('email') }}">

                <div class="mt-1 min-h-[16px]">
                    @error('email')
                        <span class="block text-[11px] leading-none text-error">
                            {{ $message }}
                        </span>
                    @enderror
                </div>
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="sendResetLink"
                class="btn btn-primary w-full rounded-full mt-4">
                <span wire:loading.remove wire:target="sendResetLink" class="flex items-center gap-2">
                    <x-heroicon-o-paper-airplane class="h-5 w-5" />
                    Kirim Link Reset
                </span>

                <span wire:loading wire:target="sendResetLink" class="flex items-center gap-2">
                    <span class="loading loading-spinner loading-sm"></span>
                    Mengirim...
                </span>
            </button>
        </form>

        <p class="mt-5 text-center text-sm">
            Ingat password?
            <a href="{{ route('customer.login') }}" wire:navigate class="font-bold text-primary">
                Masuk
            </a>
        </p>
    </div>
</div>
