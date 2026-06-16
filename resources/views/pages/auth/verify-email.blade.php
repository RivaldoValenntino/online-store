<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.auth')] #[Title('Verifikasi Email')] class extends Component {
    public function mount(): void
    {
        $customer = Auth::guard('customer')->user();

        if ($customer && $customer->hasVerifiedEmail()) {
            $this->redirectRoute('store.home', navigate: true);
        }
    }
};
?>

<div class="min-h-screen bg-base-200 p-4">
    <div class="pt-6">
        <div class="rounded-[2rem] bg-base-100 p-5 shadow-sm">
            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                <x-heroicon-o-envelope class="h-8 w-8" />
            </div>

            <h1 class="text-2xl font-black">Verifikasi Email</h1>
            <p class="mt-2 text-sm text-base-content/60">
                @if (session('status') === 'verification-link-sent')
                    Link verifikasi sudah dikirim ke email kamu. Klik link tersebut untuk mengaktifkan akun.
                @else
                    Untuk melanjutkan checkout, kamu perlu memverifikasi email terlebih dahulu.
                    Klik tombol di bawah ini untuk mengirim link verifikasi.
                @endif
            </p>

            @if (session('status') === 'verification-link-sent')
                <div class="alert alert-success mt-4 rounded-2xl">
                    <x-heroicon-o-check-circle class="h-5 w-5" />
                    <span>Link verifikasi berhasil dikirim.</span>
                </div>
            @endif


            <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
                @csrf

                <button class="btn btn-primary w-full rounded-full">
                    {{ session('status') === 'verification-link-sent' ? 'Kirim Ulang Email Verifikasi' : 'Kirim Email Verifikasi' }}
                </button>
            </form>
            <a href="{{ route('store.home') }}" wire:navigate class="btn btn-outline mt-3 w-full rounded-full">
                Kembali ke Beranda
            </a>
            {{-- <form method="POST" action="{{ route('customer.logout') }}" class="mt-3">
                @csrf
                <button class="btn btn-ghost w-full rounded-full">
                    Logout
                </button>
            </form> --}}
        </div>
    </div>
</div>
