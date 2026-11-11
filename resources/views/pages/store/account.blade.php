<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Account')] class extends Component {
    public function getCustomerProperty()
    {
        return Auth::guard('customer')->user();
    }

    public function getWishlistCountProperty(): int
    {
        return $this->customer ? (int) $this->customer->wishlists()->count() : 0;
    }

    public function getUnreadNotificationCountProperty(): int
    {
        return $this->customer ? (int) $this->customer->unreadNotifications()->count() : 0;
    }
};
?>

<div class="min-h-screen bg-base-200 pb-24">
    <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur">
        <div class="flex items-center gap-3 p-4">
            <a href="{{ route('store.home') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>

            <div>
                <h1 class="text-lg font-black">Akun Saya</h1>
                <p class="text-xs text-base-content/50">Kelola akun dan aktivitas belanja</p>
            </div>
        </div>
    </header>

    <main class="p-4">
        @auth('customer')
            <section class="rounded-[2rem] bg-base-100 p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-3xl bg-primary/10 text-primary">
                        @if ($this->customer->avatar)
                            <img src="{{ $this->customer->avatar }}" alt="{{ $this->customer->name }}"
                                class="h-full w-full object-cover" referrerpolicy="no-referrer">
                        @else
                            <x-heroicon-o-user class="h-9 w-9" />
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-xl font-black">
                            {{ $this->customer->name }}
                        </h2>

                        <p class="truncate text-sm text-base-content/60">
                            {{ $this->customer->email }}
                        </p>

                        <p class="truncate text-sm text-base-content/60">
                            {{ $this->customer->phone ?? '-' }}
                        </p>
                        <div class="mt-2">
                            @if ($this->customer->hasVerifiedEmail())
                                <span class="badge badge-success badge-sm gap-1">
                                    <x-heroicon-o-check-circle class="h-3 w-3" />
                                    Email terverifikasi
                                </span>
                            @else
                                <span class="badge badge-warning badge-sm gap-1">
                                    <x-heroicon-o-exclamation-triangle class="h-3 w-3" />
                                    Belum terverifikasi
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                @unless ($this->customer->hasVerifiedEmail())
                    <div class="alert alert-warning mt-5 rounded-2xl">
                        <x-heroicon-o-envelope class="h-5 w-5" />
                        <div>
                            <p class="text-sm font-bold">Email belum diverifikasi</p>
                            <p class="text-xs">Verifikasi email agar bisa checkout.</p>
                        </div>
                    </div>

                    <a href="{{ route('verification.notice') }}" wire:navigate
                        class="btn btn-warning btn-sm mt-3 w-full rounded-full">
                        Buka Halaman Verifikasi
                    </a>
                @endunless
            </section>

            <section class="mt-4 space-y-3">
                <a href="{{ route('store.orders') }}" wire:navigate
                    class="flex items-center gap-3 rounded-3xl bg-base-100 p-4 shadow-sm">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                        <x-heroicon-o-shopping-bag class="h-5 w-5" />
                    </div>
                    <div class="flex-1">
                        <p class="font-bold">Pesanan Saya</p>
                        <p class="text-xs text-base-content/50">Lihat riwayat dan status pesanan</p>
                    </div>
                    <x-heroicon-o-chevron-right class="h-5 w-5 text-base-content/40" />
                </a>

                <a href="{{ route('store.notifications') }}" wire:navigate
                    class="flex items-center gap-3 rounded-3xl bg-base-100 p-4 shadow-sm">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-100 text-blue-600">
                        <x-heroicon-o-bell class="h-5 w-5" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <p class="font-bold">Notifikasi</p>
                            @if ($this->unreadNotificationCount > 0)
                                <span class="badge badge-primary badge-sm text-primary-content font-bold">
                                    {{ $this->unreadNotificationCount }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-base-content/50">Pemberitahuan status pesanan</p>
                    </div>
                    <x-heroicon-o-chevron-right class="h-5 w-5 text-base-content/40" />
                </a>

                <a href="{{ route('store.wishlist') }}" wire:navigate
                    class="flex items-center gap-3 rounded-3xl bg-base-100 p-4 shadow-sm">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-100 text-rose-600">
                        <x-heroicon-o-heart class="h-5 w-5" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <p class="font-bold">Wishlist Saya</p>
                            @if ($this->wishlistCount > 0)
                                <span class="badge badge-error badge-sm text-white font-bold">
                                    {{ $this->wishlistCount }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-base-content/50">Produk yang kamu simpan</p>
                    </div>
                    <x-heroicon-o-chevron-right class="h-5 w-5 text-base-content/40" />
                </a>

                <a href="{{ route('store.addresses') }}" wire:navigate
                    class="flex items-center justify-between rounded-2xl bg-base-100 p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <x-heroicon-o-map-pin class="h-5 w-5" />
                        </div>

                        <div>
                            <p class="font-bold">Alamat Saya</p>
                            <p class="text-xs text-base-content/50">Kelola alamat pengiriman</p>
                        </div>
                    </div>

                    <x-heroicon-o-chevron-right class="h-5 w-5 text-base-content/40" />
                </a>

                <a href="{{ route('store.cart') }}" wire:navigate
                    class="flex items-center gap-3 rounded-3xl bg-base-100 p-4 shadow-sm">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-green-100 text-green-700">
                        <x-heroicon-o-shopping-cart class="h-5 w-5" />
                    </div>
                    <div class="flex-1">
                        <p class="font-bold">Keranjang</p>
                        <p class="text-xs text-base-content/50">Lanjutkan belanja kamu</p>
                    </div>
                    <x-heroicon-o-chevron-right class="h-5 w-5 text-base-content/40" />
                </a>
            </section>

            <div class="mt-5">
                <button type="button" onclick="logout_modal.showModal()"
                    class="btn btn-error btn-outline w-full rounded-full">
                    <x-heroicon-o-arrow-left-on-rectangle class="h-5 w-5" />
                    Logout
                </button>

                <dialog id="logout_modal" class="modal">
                    <div class="modal-box rounded-[2rem]">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-error/10 text-error">
                                <x-heroicon-o-arrow-left-on-rectangle class="h-6 w-6" />
                            </div>

                            <div>
                                <h3 class="text-lg font-black">Keluar dari akun?</h3>
                                <p class="mt-1 text-sm text-base-content/60">
                                    Kamu harus login kembali untuk checkout, melihat pesanan, dan mengelola alamat.
                                </p>
                            </div>
                        </div>

                        <div class="modal-action grid grid-cols-2 gap-3">
                            <form method="dialog">
                                <button class="btn btn-ghost w-full rounded-full">
                                    Batal
                                </button>
                            </form>

                            <form method="POST" action="{{ route('customer.logout') }}">
                                @csrf

                                <button class="btn btn-error w-full rounded-full">
                                    Ya, Logout
                                </button>
                            </form>
                        </div>
                    </div>

                    <form method="dialog" class="modal-backdrop">
                        <button>close</button>
                    </form>
                </dialog>
            </div>
        @else
            <section class="rounded-[2rem] bg-base-100 p-5 text-center shadow-sm">
                <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-[2rem] bg-primary/10 text-primary">
                    <x-heroicon-o-user-circle class="h-12 w-12" />
                </div>

                <h2 class="mt-4 text-2xl font-black">Belum Login</h2>

                <p class="mt-2 text-sm text-base-content/60">
                    Masuk atau buat akun untuk checkout, menyimpan alamat, dan melihat riwayat pesanan.
                </p>

                <div class="mt-6 grid grid-cols-2 gap-3">
                    <a href="{{ route('customer.login') }}" wire:navigate class="btn btn-primary rounded-full">
                        Login
                    </a>

                    <a href="{{ route('customer.register') }}" wire:navigate class="btn btn-outline rounded-full">
                        Buat Akun
                    </a>
                </div>
            </section>

            <section class="mt-4 rounded-[2rem] bg-amber-50 p-4 text-amber-900">
                <div class="flex gap-3">
                    <x-heroicon-o-sparkles class="h-6 w-6 shrink-0" />
                    <div>
                        <p class="font-bold">Kenapa perlu akun?</p>
                        <p class="mt-1 text-sm">
                            Akun dipakai untuk menyimpan alamat, tracking pesanan, dan proses pembayaran lebih aman.
                        </p>
                    </div>
                </div>
            </section>
        @endauth
    </main>
</div>
