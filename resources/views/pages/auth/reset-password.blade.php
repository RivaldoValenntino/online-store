<?php

use App\Models\Customer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.auth')] #[Title('Reset Password')] class extends Component {
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public ?string $resetError = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    protected function rules(): array
    {
        return [
            'token' => ['required'],
            'email' => ['required', 'email', 'exists:customers,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
            'password_confirmation' => ['required'],
        ];
    }

    protected function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.exists' => 'Email tidak terdaftar.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal 8 karakter.',
            'password_confirmation.required' => 'Konfirmasi password wajib diisi.',
        ];
    }

    public function updated(string $property): void
    {
        $this->resetError = null;

        $this->validateOnly($property);

        if ($property === 'password' && filled($this->password_confirmation)) {
            $this->validateOnly('password_confirmation');
        }
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

    public function resetPassword(): void
    {
        $this->resetError = null;

        $this->validate();

        $status = Password::broker('customers')->reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (Customer $customer, string $password) {
                $customer
                    ->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])
                    ->save();

                event(new PasswordReset($customer));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->resetError = match ($status) {
                Password::INVALID_TOKEN => 'Link reset password tidak valid atau sudah kedaluwarsa.',
                Password::INVALID_USER => 'Email tidak ditemukan.',
                default => __($status),
            };

            return;
        }

        session()->flash('flash.type', 'success');
        session()->flash('flash.title', 'Password berhasil direset');
        session()->flash('flash.message', 'Silakan login menggunakan password baru kamu.');

        $this->redirectRoute('customer.login', navigate: true);
    }
};
?>

<div class="min-h-screen bg-base-200 p-4">
    {{-- Fullscreen Loading --}}
    <div wire:loading.flex wire:target="resetPassword"
        class="fixed inset-0 z-[9999] items-center justify-center bg-base-100/70 backdrop-blur-sm">
        <div class="flex flex-col items-center gap-3 rounded-3xl bg-base-100 p-6 shadow-xl">
            <span class="loading loading-spinner loading-lg text-primary"></span>

            <p class="text-sm font-semibold text-base-content/70">
                Mereset password...
            </p>
        </div>
    </div>

    {{-- Toast Error --}}
    @if ($resetError)
        <div class="toast toast-top toast-center z-[9998]">
            <div class="alert alert-error alert-soft rounded-2xl shadow-lg">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5" />

                <span class="text-sm">{{ $resetError }}</span>

                <button type="button" wire:click="$set('resetError', null)" class="btn btn-ghost btn-xs btn-circle">
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

        <h1 class="text-2xl font-black">Reset Password</h1>

        <p class="mt-1 text-sm text-base-content/60">
            Buat password baru untuk akun kamu.
        </p>

        <form wire:submit="resetPassword" novalidate class="mt-6 space-y-3">
            {{-- Email --}}
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

            {{-- Password --}}
            <div>
                <div class="mb-1">
                    <span class="text-sm font-medium">Password Baru</span>
                </div>

                <div class="relative" x-data="{ show: false }">
                    <input :type="show ? 'text' : 'password'" wire:model.live.debounce.500ms="password"
                        placeholder="Minimal 8 karakter" class="{{ $this->inputClass('password') }} pr-11">

                    <button type="button" @click="show = !show" tabindex="-1"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-base-content/50 hover:text-base-content">
                        <x-heroicon-o-eye class="h-5 w-5" x-show="!show" x-cloak />
                        <x-heroicon-o-eye-slash class="h-5 w-5" x-show="show" x-cloak />
                    </button>
                </div>

                <div class="mt-1 min-h-[16px]">
                    @error('password')
                        <span class="block text-[11px] leading-none text-error">
                            {{ $message }}
                        </span>
                    @enderror
                </div>
            </div>

            {{-- Password Confirmation --}}
            <div>
                <div class="mb-1">
                    <span class="text-sm font-medium">Konfirmasi Password</span>
                </div>

                <div class="relative" x-data="{ show: false }">
                    <input :type="show ? 'text' : 'password'" wire:model.live.debounce.500ms="password_confirmation"
                        placeholder="Ulangi password baru"
                        class="{{ $this->inputClass('password_confirmation') }} pr-11">

                    <button type="button" @click="show = !show" tabindex="-1"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-base-content/50 hover:text-base-content">
                        <x-heroicon-o-eye class="h-5 w-5" x-show="!show" x-cloak />
                        <x-heroicon-o-eye-slash class="h-5 w-5" x-show="show" x-cloak />
                    </button>
                </div>

                <div class="mt-1 min-h-[16px]">
                    @error('password_confirmation')
                        <span class="block text-[11px] leading-none text-error">
                            {{ $message }}
                        </span>
                    @enderror
                </div>
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="resetPassword"
                class="btn btn-primary w-full rounded-full mt-4">
                <span wire:loading.remove wire:target="resetPassword">
                    Reset Password
                </span>

                <span wire:loading wire:target="resetPassword" class="flex items-center gap-2">
                    <span class="loading loading-spinner loading-sm"></span>
                    Memproses...
                </span>
            </button>
        </form>

        <p class="mt-5 text-center text-sm">
            Sudah ingat password?
            <a href="{{ route('customer.login') }}" wire:navigate class="font-bold text-primary">
                Masuk
            </a>
        </p>
    </div>
</div>
