<?php

use App\Models\Banner;
use App\Models\Voucher;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public function rendering($view): void
    {
        $appName = (string) (config('services.apps.app_name') ?: config('app.name', 'Podo Raos'));
        $view->title('Promo & Voucher – ' . $appName);
    }
    #[Computed]
    public function banners()
    {
        return Banner::query()->active()->with('voucher')->orderBy('sort_order')->get();
    }

    #[Computed]
    public function vouchers()
    {
        return Voucher::query()->active()->latest()->get();
    }
};
?>

<div class="max-w-6xl mx-auto px-4 py-6 md:py-10">
    <!-- Header Section -->
    <div class="mb-8 text-center max-w-2xl mx-auto">
        <span
            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V4a2 2 0 10-2 2h2m0 13a2 2 0 112-2H8a2 2 0 102 2z" />
            </svg>
            Promo Spesial {{ config('services.apps.app_name') }}
        </span>
        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-base-content">Klaim Voucher & Diskon Menarik
        </h1>
        <p class="text-sm md:text-base text-base-content/70 mt-2">
            Gunakan kode voucher saat checkout untuk menikmati potongan harga spesial dan gratis ongkir untuk produk
            favorit Anda!
        </p>
    </div>

    <!-- Active Promo Banners Carousel/Grid -->
    {{-- @if ($this->banners->isNotEmpty())
        <div class="mb-10">
            <div class="carousel w-full rounded-2xl shadow-sm border border-base-200 overflow-hidden bg-base-100">
                @foreach ($this->banners as $index => $banner)
                    <div id="promo-slide-{{ $banner->id }}"
                        class="carousel-item relative w-full flex flex-col md:flex-row items-center justify-between p-6 md:p-8 bg-gradient-to-r from-primary/5 via-base-100 to-secondary/5">
                        <div class="max-w-xl z-10">
                            @if ($banner->badge)
                                <span class="badge badge-primary font-bold text-xs mb-3">{{ $banner->badge }}</span>
                            @endif
                            <h2 class="text-xl md:text-2xl font-bold mb-2">{{ $banner->title }}</h2>
                            @if ($banner->subtitle)
                                <p class="text-sm text-base-content/70 mb-4">{{ $banner->subtitle }}</p>
                            @endif

                            <div class="flex flex-wrap items-center gap-3">
                                @if ($banner->voucher)
                                    <div x-data="{ copied: false }"
                                        class="inline-flex items-center gap-2 bg-base-200/80 px-3 py-1.5 rounded-xl border border-dashed border-primary">
                                        <span class="text-xs text-base-content/70">Kode:</span>
                                        <span
                                            class="font-mono font-bold text-primary">{{ $banner->voucher->code }}</span>
                                        <button type="button"
                                            x-on:click="navigator.clipboard.writeText('{{ $banner->voucher->code }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="btn btn-xs btn-ghost text-primary">
                                            <span x-show="!copied">Salin</span>
                                            <span x-show="copied" x-cloak
                                                class="text-success font-semibold">Tersalin!</span>
                                        </button>
                                    </div>
                                @endif

                                @if ($banner->button_text && $banner->button_url)
                                    <a href="{{ $banner->button_url }}" wire:navigate
                                        class="btn btn-sm btn-primary rounded-xl">
                                        {{ $banner->button_text }}
                                    </a>
                                @endif
                            </div>
                        </div>

                        @if ($banner->image)
                            <div class="mt-4 md:mt-0 max-h-48 flex-shrink-0">
                                <img src="{{ Storage::url($banner->image) }}" alt="{{ $banner->title }}"
                                    class="max-h-44 object-contain rounded-xl" />
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif --}}

    <!-- Voucher Cards Grid -->
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg md:text-xl font-bold flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
            </svg>
            Daftar Voucher Tersedia
        </h2>
        <span class="text-xs text-base-content/60">{{ $this->vouchers->count() }} voucher aktif</span>
    </div>

    @if ($this->vouchers->isEmpty())
        <div class="text-center py-12 bg-base-100 rounded-2xl border border-dashed border-base-300 p-8">
            <div
                class="w-16 h-16 bg-base-200 rounded-full flex items-center justify-center mx-auto mb-4 text-base-content/50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
            </div>
            <h3 class="font-bold text-base">Belum Ada Voucher Promo</h3>
            <p class="text-sm text-base-content/70 mt-1">Nantikan promo menarik berikutnya dari
                {{ config('services.apps.app_name') ?: config('app.name', 'Podo Raos') }}!</p>
            <a href="{{ route('store.products') }}" wire:navigate class="btn btn-primary btn-sm rounded-xl mt-4">
                Jelajahi Produk
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($this->vouchers as $voucher)
                <div
                    class="bg-base-100 rounded-2xl border border-base-200 p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden flex flex-col justify-between">
                    <!-- Top Ribbon / Type -->
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex items-center gap-2">
                            <span
                                class="badge badge-sm font-semibold
                                {{ $voucher->type === 'percentage' ? 'badge-primary' : '' }}
                                {{ $voucher->type === 'fixed' ? 'badge-success text-white' : '' }}
                                {{ $voucher->type === 'free_shipping' ? 'badge-info text-white' : '' }}
                                {{ $voucher->type === 'shipping_discount' ? 'badge-warning text-white' : '' }}">
                                {{ $voucher->formattedDiscount() }}
                            </span>
                            @if ($voucher->target_audience === 'new_users')
                                <span class="badge badge-sm badge-outline badge-secondary">Pengguna Baru</span>
                            @endif
                        </div>

                        <!-- Quota Badge -->
                        @if (!is_null($voucher->total_quota))
                            <span class="text-[11px] text-base-content/60 font-medium">
                                Sisa {{ max(0, $voucher->total_quota - $voucher->total_used) }} kuota
                            </span>
                        @endif
                    </div>

                    <!-- Voucher Name & Description -->
                    <div class="mb-4">
                        <h3 class="font-bold text-base text-base-content mb-1">{{ $voucher->name }}</h3>
                        @if ($voucher->description)
                            <p class="text-xs text-base-content/70 line-clamp-2">{{ $voucher->description }}</p>
                        @endif
                    </div>

                    <!-- Terms & Details -->
                    <div class="bg-base-200/50 rounded-xl p-3 text-xs space-y-1 mb-4 text-base-content/80">
                        <div class="flex justify-between">
                            <span>Min. Belanja:</span>
                            <span class="font-semibold">
                                {{ $voucher->min_spend > 0 ? 'Rp ' . number_format($voucher->min_spend, 0, ',', '.') : 'Tanpa Minimum' }}
                            </span>
                        </div>
                        @if ($voucher->max_discount)
                            <div class="flex justify-between">
                                <span>Maks. Diskon:</span>
                                <span class="font-semibold">Rp
                                    {{ number_format($voucher->max_discount, 0, ',', '.') }}</span>
                            </div>
                        @endif
                        @if ($voucher->expires_at)
                            <div class="flex justify-between text-warning">
                                <span>Berlaku s/d:</span>
                                <span class="font-semibold">{{ $voucher->expires_at->format('d M Y') }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Code & Copy Button -->
                    <div x-data="{ copied: false }" class="flex items-center gap-2 pt-2 border-t border-base-200">
                        <div
                            class="flex-1 bg-primary/5 border border-dashed border-primary/40 rounded-xl px-3 py-2 flex items-center justify-between">
                            <span
                                class="font-mono font-bold text-sm tracking-wider text-primary">{{ $voucher->code }}</span>
                            <button type="button"
                                x-on:click="navigator.clipboard.writeText('{{ $voucher->code }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="text-xs font-semibold text-primary hover:underline">
                                <span x-show="!copied">Salin</span>
                                <span x-show="copied" x-cloak class="text-success font-bold">Tersalin!</span>
                            </button>
                        </div>
                        <a href="{{ route('store.products') }}" wire:navigate
                            class="btn btn-sm btn-primary rounded-xl">
                            Pakai
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
