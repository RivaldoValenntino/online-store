<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.auth')] #[Title('Register')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';

    public ?string $registerError = null;
    public ?string $registerSuccess = null;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', Password::min(8)],
            'password_confirmation' => ['required', 'same:password'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.max' => 'Nama maksimal 255 karakter.',

            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.unique' => 'Email sudah terdaftar.',

            'phone.required' => 'Nomor HP wajib diisi.',
            'phone.max' => 'Nomor HP maksimal 30 karakter.',

            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',

            'password_confirmation.required' => 'Konfirmasi password wajib diisi.',
            'password_confirmation.same' => 'Konfirmasi password tidak cocok.',
        ];
    }

    public function updated(string $property): void
    {
        $this->registerError = null;
        $this->registerSuccess = null;

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

    public function register(): void
    {
        $this->registerError = null;
        $this->registerSuccess = null;

        $validated = $this->validate();

        try {
            $customer = Customer::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'is_active' => true,
            ]);

            $customer->sendEmailVerificationNotification();

            Auth::guard('customer')->login($customer);

            session()->regenerate();
            session()->flash('flash.type', 'success');
            session()->flash('flash.title', 'Akun berhasil dibuat');
            session()->flash('flash.message', 'Kami sudah mengirim link verifikasi ke email kamu.');
            $this->redirectRoute('verification.notice', navigate: true);
        } catch (\Throwable $e) {
            report($e);

            $this->registerError = 'Pendaftaran gagal diproses. Coba beberapa saat lagi.';
        }
    }
};
?>
<div class="min-h-screen bg-base-200 p-4">
    {{-- Fullscreen Loading --}}
    <div wire:loading.flex wire:target="register"
        class="fixed inset-0 z-[9999] items-center justify-center bg-base-100/70 backdrop-blur-sm">
        <div class="flex flex-col items-center gap-3 rounded-3xl bg-base-100 p-6 shadow-xl">
            <span class="loading loading-spinner loading-lg text-primary"></span>
            <p class="text-sm font-semibold text-base-content/70">
                Membuat akun...
            </p>
        </div>
    </div>
    
     @if($registerError)
        <div class="alert alert-error alert-soft mt-4 rounded-2xl">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5" />

            <div>
                <p class="text-sm font-bold">Gagal membuat akun</p>
                <p class="text-xs">{{ $registerError }}</p>
            </div>

            <button type="button" wire:click="$set('registerError', null)" class="btn btn-ghost btn-xs btn-circle ml-auto">
                <x-heroicon-o-x-mark class="h-4 w-4" />
            </button>
        </div>
    @endif
    <div class="rounded-[2rem] bg-base-100 p-5 shadow-sm">
        <div class="mb-5">
            <a href="{{ route('store.home') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>
        </div>
        <h1 class="text-2xl font-black">Buat Akun</h1>

        <p class="mt-1 text-sm text-base-content/60">
            Daftar untuk belanja lebih mudah.
        </p>

        <form wire:submit="register" novalidate class="mt-6 space-y-1">
            {{-- Nama --}}
            <div>
                <div class="mb-1">
                    <span class="text-sm font-medium">Nama</span>
                </div>

                <input type="text" wire:model.live.debounce.500ms="name" placeholder="Nama lengkap"
                    class="{{ $this->inputClass('name') }}">

                <div class="mt-1 min-h-[16px]">
                    @error('name')
                    <span class="block text-[11px] leading-none text-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

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

            {{-- No HP --}}
            <div>
                <div class="mb-1">
                    <span class="text-sm font-medium">No HP</span>
                </div>

                <input type="text" wire:model.live.debounce.500ms="phone" placeholder="08xxxxxxxxxx"
                    class="{{ $this->inputClass('phone') }}">

                <div class="mt-1 min-h-[16px]">
                    @error('phone')
                    <span class="block text-[11px] leading-none text-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- Password --}}
            <div>
                <div class="mb-1">
                    <span class="text-sm font-medium">Password</span>
                </div>

                <input type="password" wire:model.live.debounce.500ms="password" placeholder="Minimal 8 karakter"
                    class="{{ $this->inputClass('password') }}">

                <div class="mt-1 min-h-[16px]">
                    @error('password')
                    <span class="block text-[11px] leading-none text-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- Konfirmasi Password --}}
            <div>
                <div class="mb-1">
                    <span class="text-sm font-medium">Konfirmasi Password</span>
                </div>

                <input type="password" wire:model.live.debounce.500ms="password_confirmation"
                    placeholder="Ulangi password" class="{{ $this->inputClass('password_confirmation') }}">

                <div class="mt-1 min-h-[16px]">
                    @error('password_confirmation')
                    <span class="block text-[11px] leading-none text-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="register"
                class="btn btn-primary w-full rounded-full mt-2">
                <span wire:loading.remove wire:target="register">
                    Daftar
                </span>

                <span wire:loading wire:target="register" class="flex items-center gap-2">
                    <span class="loading loading-spinner loading-sm"></span>
                    Memproses...
                </span>
            </button>
        </form>

        <p class="mt-5 text-center text-sm">
            Sudah punya akun?
            <a href="{{ route('customer.login') }}" wire:navigate class="font-bold text-primary">
                Masuk
            </a>
        </p>
    </div>
</div>