<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Banner;
use App\Models\Voucher;
use App\Models\Wishlist;
use App\Services\ContactSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new class extends Component {
    public string $search = '';
    public bool $showPromoModal = false;

    public function appName(): string
    {
        return (string) (config('services.apps.app_name') ?: config('app.name', 'Podo Raos'));
    }

    public function rendering($view): void
    {
        $view->title('Beranda – ' . $this->appName());
    }

    public function mount(): void
    {
        if (session('show_promo_subscribe_modal') && Auth::guard('customer')->check()) {
            $this->showPromoModal = true;
        }
    }

    public function acceptPromoSubscription(): void
    {
        $customer = Auth::guard('customer')->user();
        if ($customer) {
            $customer->update([
                'email_marketing' => true,
                'in_app_marketing' => true,
                'unsubscribed_from_promos' => false,
            ]);
        }

        $this->showPromoModal = false;
        $this->dispatch('notify', type: 'success', title: 'Berlangganan Promo Aktif', message: 'Terima kasih! Anda akan menerima update diskon dan voucher spesial dari ' . $this->appName() . '.');
    }

    public function declinePromoSubscription(): void
    {
        $customer = Auth::guard('customer')->user();
        if ($customer) {
            $customer->update([
                'email_marketing' => false,
                'in_app_marketing' => false,
            ]);
        }

        $this->showPromoModal = false;
    }

    public function searchProducts(): void
    {
        $search = trim($this->search);

        if ($search === '') {
            $this->redirectRoute('store.products', navigate: true);
            return;
        }

        $this->redirectRoute(
            'store.products',
            [
                'search' => $search,
            ],
            navigate: true,
        );
    }

    #[Computed]
    public function banners()
    {
        return Banner::query()->active()->with('voucher')->orderBy('sort_order')->latest()->limit(5)->get();
    }

    #[Computed]
    public function activeVouchers()
    {
        return Voucher::query()->validForUse()->latest()->limit(4)->get();
    }

    #[Computed]
    public function categories()
    {
        return Category::query()
            ->where('is_active', true)
            ->withCount(['products' => fn($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->limit(12)
            ->get();
    }

    #[Computed]
    public function bestSellers()
    {
        $bestSellers = Product::query()
            ->with(['category', 'primaryImage', 'variants'])
            ->where('is_active', true)
            ->withSum(
                [
                    'orderItems as sold_count' => function ($query) {
                        $query->whereHas('order', function ($q) {
                            $q->where('payment_status', 'paid');
                        });
                    },
                ],
                'qty',
            )
            ->orderByDesc('sold_count')
            ->limit(6)
            ->get();

        // If no products have recorded sales yet, fallback to featured active products
        if ($bestSellers->isEmpty() || $bestSellers->every(fn($p) => ($p->sold_count ?? 0) == 0)) {
            return Product::query()
                ->with(['category', 'primaryImage', 'variants'])
                ->where('is_active', true)
                ->latest()
                ->limit(6)
                ->get();
        }

        return $bestSellers;
    }

    #[Computed]
    public function latestProducts()
    {
        return Product::query()
            ->with(['category', 'primaryImage', 'variants'])
            ->where('is_active', true)
            ->latest()
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function featuredReviews()
    {
        try {
            return ProductReview::query()
                ->approved()
                ->where('rating', '>=', 4)
                ->with(['product.primaryImage', 'customer', 'images', 'reply'])
                ->latest()
                ->limit(6)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function lowestPrice($product): float
    {
        $variantPrice = $product->variants->where('is_active', true)->min('price');

        return (float) ($variantPrice ?: $product->price);
    }

    public function customerName(): string
    {
        return Auth::guard('customer')->user()?->name ?? 'Tamu';
    }

    #[Computed]
    public function wishlistedProductIds(): array
    {
        $customer = Auth::guard('customer')->user();
        if (!$customer) {
            return [];
        }

        try {
            return $customer->wishlists()->pluck('product_id')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    #[Computed]
    public function wishlistCount(): int
    {
        $customer = Auth::guard('customer')->user();
        if (!$customer) {
            return 0;
        }

        try {
            return (int) $customer->wishlists()->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    #[Computed]
    public function unreadNotificationCount(): int
    {
        $customer = Auth::guard('customer')->user();
        if (!$customer) {
            return 0;
        }

        try {
            return (int) $customer->unreadNotifications()->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function csTitle(): string
    {
        return config('services.contact.homepage_title', 'Masih Bingung Memilih Produk?');
    }

    public function csSubtitle(): string
    {
        return config('services.contact.homepage_subtitle', 'Butuh hampers khusus atau tanya stok?');
    }

    public function csDescription(): string
    {
        return config('services.contact.homepage_description', 'Konsultasikan kebutuhan oleh-oleh khas Garut atau pesanan dalam jumlah besar langsung dengan Customer Service kami.');
    }

    public function csButtonText(): string
    {
        return config('services.contact.homepage_button_text', 'Hubungi CS via WhatsApp');
    }

    public function isCsAvailable(): bool
    {
        return app(ContactSettings::class)->isAvailable();
    }

    public function csWhatsAppUrl(): ?string
    {
        $defaultMsg = 'Halo Admin ' . $this->appName() . '';
        $message = config('services.contact.homepage_message', $defaultMsg);

        return app(ContactSettings::class)->getInquiryUrl($message);
    }

    public function toggleWishlist(int $productId): void
    {
        $customer = Auth::guard('customer')->user();
        if (!$customer) {
            $this->redirectRoute('customer.login', navigate: true);
            return;
        }

        $wishlist = Wishlist::where('customer_id', $customer->id)->where('product_id', $productId)->first();

        if ($wishlist) {
            $wishlist->delete();
            $this->dispatch('notify', type: 'info', title: 'Dihapus dari Wishlist', message: 'Produk telah dihapus dari wishlist kamu.');
        } else {
            Wishlist::create([
                'customer_id' => $customer->id,
                'product_id' => $productId,
            ]);
            $this->dispatch('notify', type: 'success', title: 'Berhasil Disimpan', message: 'Produk berhasil disimpan ke wishlist.');
        }

        unset($this->wishlistedProductIds);
        unset($this->wishlistCount);

        $this->dispatch('wishlist-updated');
    }
};
?>

<div class="min-h-screen bg-base-200">
    {{-- Header --}}
    <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur-md">
        <div class="space-y-3 p-4">
            <div class="flex items-center justify-between gap-3">
                @if (Auth::guard('customer')->check())
                    <div class="min-w-0">
                        <span class="inline-flex items-center gap-1.5 text-xs text-base-content/60 font-medium">
                            <span class="inline-block h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Selamat berbelanja
                        </span>
                        <h1
                            class="text-lg font-black tracking-tight text-base-content truncate max-w-[200px] sm:max-w-xs">
                            {{ Auth::guard('customer')->user()->name }}
                        </h1>
                    </div>
                @else
                    <div class="min-w-0">
                        <p class="text-xs text-base-content/60 font-medium">Selamat datang di</p>
                        <h1 class="text-lg font-black tracking-tight text-primary flex items-center gap-1.5">
                            {{ $this->appName() }}
                        </h1>
                    </div>
                @endif

                <div class="flex items-center gap-1 shrink-0">
                    @if (!Auth::guard('customer')->check())
                        <a href="{{ route('customer.login') }}" wire:navigate
                            class="btn btn-xs btn-outline btn-primary rounded-full px-2.5 font-bold mr-1">
                            Masuk
                        </a>
                    @endif

                    <a href="{{ route('store.notifications') }}" wire:navigate
                        class="btn btn-circle btn-ghost btn-sm relative text-base-content/80 hover:text-primary transition"
                        title="Notifikasi" aria-label="Notifikasi">
                        <x-heroicon-o-bell class="h-5 w-5" />
                        @if ($this->unreadNotificationCount > 0)
                            <span
                                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[9px] font-black text-primary-content ring-2 ring-base-100">
                                {{ $this->unreadNotificationCount > 99 ? '99+' : $this->unreadNotificationCount }}
                            </span>
                        @endif
                    </a>

                    <a href="{{ route('store.wishlist') }}" wire:navigate
                        class="btn btn-circle btn-ghost btn-sm relative text-base-content/80 hover:text-rose-500 transition"
                        title="Wishlist" aria-label="Wishlist">
                        <x-heroicon-o-heart class="h-5 w-5" />
                        @if ($this->wishlistCount > 0)
                            <span
                                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] font-black text-white ring-2 ring-base-100">
                                {{ $this->wishlistCount > 99 ? '99+' : $this->wishlistCount }}
                            </span>
                        @endif
                    </a>

                    <x-store.theme-toggle />
                </div>
            </div>

            {{-- Functional Search Bar --}}
            <form wire:submit="searchProducts" class="relative">
                <div
                    class="group relative flex h-11 w-full items-center rounded-full border border-base-300 bg-base-100 p-1 pl-3.5 shadow-xs transition-all duration-200 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 hover:border-base-content/30">
                    <x-heroicon-o-magnifying-glass
                        class="h-5 w-5 shrink-0 text-base-content/40 transition group-focus-within:text-primary" />

                    <input type="text" wire:model="search"
                        class="w-full min-w-0 border-0 bg-transparent px-3 text-xs sm:text-sm text-base-content outline-none placeholder:text-base-content/40 focus:border-0 focus:outline-none focus:ring-0"
                        placeholder="Cari produk..." autocomplete="off" />

                    @if ($search)
                        <button type="button" wire:click="$set('search', '')"
                            class="btn btn-ghost btn-xs btn-circle shrink-0 text-base-content/40 hover:text-base-content mr-1"
                            aria-label="Hapus pencarian">
                            <x-heroicon-o-x-mark class="h-4 w-4" />
                        </button>
                    @endif

                    <button type="submit"
                        class="btn btn-primary h-9 min-h-[36px] rounded-full px-4 text-xs font-bold text-white shadow-xs transition hover:brightness-105 active:scale-95 shrink-0 border-none flex items-center justify-center">
                        Cari
                    </button>
                </div>
            </form>
        </div>
    </header>

    <main class="space-y-6 p-4">
        {{-- Hero Banner / Campaign Carousel --}}
        @if ($this->banners->isNotEmpty())
            @if ($this->banners->count() > 1)
                {{-- Multi-banner Carousel with Alpine.js --}}
                <section x-data="{
                    active: 0,
                    total: {{ $this->banners->count() }},
                    timer: null,
                    next() {
                        this.active = (this.active + 1) % this.total;
                        this.scrollToActive();
                    },
                    prev() {
                        this.active = (this.active - 1 + this.total) % this.total;
                        this.scrollToActive();
                    },
                    goTo(idx) {
                        this.active = idx;
                        this.scrollToActive();
                    },
                    scrollToActive() {
                        const slider = this.$refs.slider;
                        if (slider) {
                            const card = slider.children[this.active];
                            if (card) {
                                card.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                            }
                        }
                    },
                    startAuto() {
                        this.stopAuto();
                        this.timer = setInterval(() => { this.next(); }, 5000);
                    },
                    stopAuto() {
                        if (this.timer) clearInterval(this.timer);
                    }
                }" x-init="startAuto()" @mouseenter="stopAuto()"
                    @mouseleave="startAuto()" class="relative">
                    <div x-ref="slider"
                        class="flex gap-4 overflow-x-auto snap-x snap-mandatory pb-1 scroll-smooth [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        @foreach ($this->banners as $idx => $banner)
                            <div class="w-full shrink-0 snap-center">
                                <a href="{{ $banner->button_url ?: route('store.products') }}" wire:navigate
                                    class="relative block min-h-[190px] sm:min-h-[220px] w-full overflow-hidden rounded-[2rem] bg-gradient-to-br from-violet-950 via-purple-900 to-indigo-950 p-5 shadow-md group">
                                    @if ($banner->image)
                                        <img src="{{ Storage::disk('public')->url($banner->image) }}"
                                            alt="{{ $banner->title }}"
                                            class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                                        <div
                                            class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/45 to-transparent">
                                        </div>
                                    @else
                                        <div
                                            class="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-primary/20 blur-xl">
                                        </div>
                                        <div
                                            class="absolute -bottom-10 right-8 h-32 w-32 rounded-full bg-violet-600/20 blur-lg">
                                        </div>
                                    @endif

                                    <div class="relative z-10 max-w-[85%] flex flex-col justify-between h-full">
                                        <div>
                                            @if ($banner->badge)
                                                <div class="badge badge-warning badge-sm font-bold mb-2 shadow-xs">
                                                    {{ $banner->badge }}
                                                </div>
                                            @endif

                                            <h2
                                                class="text-xl sm:text-2xl font-black leading-tight text-white drop-shadow-sm">
                                                {{ $banner->title }}
                                            </h2>

                                            @if ($banner->subtitle)
                                                <p
                                                    class="mt-1 text-xs sm:text-sm leading-relaxed text-white/80 line-clamp-2">
                                                    {{ $banner->subtitle }}
                                                </p>
                                            @endif
                                        </div>

                                        <div class="mt-4 flex flex-wrap items-center gap-2">
                                            @if ($banner->voucher)
                                                <div x-data="{ copied: false }"
                                                    class="inline-flex items-center gap-1.5 rounded-xl bg-black/60 px-2.5 py-1 text-xs backdrop-blur-md border border-white/20 shadow-xs"
                                                    x-on:click.stop.prevent>
                                                    <span class="text-white/70 text-[10px]">KODE:</span>
                                                    <span
                                                        class="font-mono font-black text-amber-300 text-xs">{{ $banner->voucher->code }}</span>
                                                    <button type="button"
                                                        x-on:click="navigator.clipboard.writeText('{{ $banner->voucher->code }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                        class="ml-1 text-[10px] font-bold text-white hover:text-amber-300 transition">
                                                        <span x-show="!copied">SALIN</span>
                                                        <span x-show="copied" x-cloak
                                                            class="text-emerald-400">TERSALIN!</span>
                                                    </button>
                                                </div>
                                            @endif

                                            @if ($banner->button_text)
                                                <div class="btn btn-warning btn-xs rounded-full font-bold shadow-sm">
                                                    {{ $banner->button_text }}
                                                    <x-heroicon-o-arrow-right class="h-3.5 w-3.5" />
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>

                    {{-- Carousel Dots --}}
                    <div class="mt-2.5 flex justify-center items-center gap-1.5">
                        @foreach ($this->banners as $idx => $banner)
                            <button type="button" @click="goTo({{ $idx }})"
                                class="h-1.5 rounded-full transition-all duration-300"
                                :class="active === {{ $idx }} ? 'w-5 bg-primary' :
                                    'w-1.5 bg-base-300 hover:bg-base-content/40'"
                                aria-label="Go to banner {{ $idx + 1 }}"></button>
                        @endforeach
                    </div>
                </section>
            @else
                {{-- Single Banner (No Carousel arrows/dots as required) --}}
                @php $banner = $this->banners->first(); @endphp
                <section>
                    <a href="{{ $banner->button_url ?: route('store.products') }}" wire:navigate
                        class="relative block min-h-[190px] sm:min-h-[220px] w-full overflow-hidden rounded-[2rem] bg-gradient-to-br from-violet-950 via-purple-900 to-indigo-950 p-5 shadow-md group">
                        @if ($banner->image)
                            <img src="{{ Storage::disk('public')->url($banner->image) }}" alt="{{ $banner->title }}"
                                class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/45 to-transparent">
                            </div>
                        @else
                            <div class="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-primary/20 blur-xl"></div>
                            <div class="absolute -bottom-10 right-8 h-32 w-32 rounded-full bg-violet-600/20 blur-lg">
                            </div>
                        @endif

                        <div class="relative z-10 max-w-[85%] flex flex-col justify-between h-full">
                            <div>
                                @if ($banner->badge)
                                    <div class="badge badge-warning badge-sm font-bold mb-2 shadow-xs">
                                        {{ $banner->badge }}
                                    </div>
                                @endif

                                <h2 class="text-xl sm:text-2xl font-black leading-tight text-white drop-shadow-sm">
                                    {{ $banner->title }}
                                </h2>

                                @if ($banner->subtitle)
                                    <p class="mt-1 text-xs sm:text-sm leading-relaxed text-white/80 line-clamp-2">
                                        {{ $banner->subtitle }}
                                    </p>
                                @endif
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                @if ($banner->voucher)
                                    <div x-data="{ copied: false }"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-black/60 px-2.5 py-1 text-xs backdrop-blur-md border border-white/20 shadow-xs"
                                        x-on:click.stop.prevent>
                                        <span class="text-white/70 text-[10px]">KODE:</span>
                                        <span
                                            class="font-mono font-black text-amber-300 text-xs">{{ $banner->voucher->code }}</span>
                                        <button type="button"
                                            x-on:click="navigator.clipboard.writeText('{{ $banner->voucher->code }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="ml-1 text-[10px] font-bold text-white hover:text-amber-300 transition">
                                            <span x-show="!copied">SALIN</span>
                                            <span x-show="copied" x-cloak class="text-emerald-400">TERSALIN!</span>
                                        </button>
                                    </div>
                                @endif

                                @if ($banner->button_text)
                                    <div class="btn btn-warning btn-xs rounded-full font-bold shadow-sm">
                                        {{ $banner->button_text }}
                                        <x-heroicon-o-arrow-right class="h-3.5 w-3.5" />
                                    </div>
                                @endif
                            </div>
                        </div>
                    </a>
                </section>
            @endif
        @else
            {{-- Brand Default Fallback Banner --}}
            <section
                class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-violet-950 via-purple-900 to-indigo-950 p-5 shadow-md">
                <div class="relative z-10 max-w-[80%]">
                    <div class="badge badge-warning badge-sm font-bold mb-2">100% Asli Garut</div>

                    <h2 class="text-xl sm:text-2xl font-black leading-tight text-white">
                        Oleh-Oleh Khas Garut Paling Autentik
                    </h2>

                    <p class="mt-1.5 text-xs sm:text-sm leading-relaxed text-white/80">
                        Dodol legit, wajit ketan, keripik renyah, dan hampers spesial dikirim segar ke rumah Anda.
                    </p>

                    <a href="{{ route('store.products') }}" wire:navigate
                        class="btn btn-warning btn-xs sm:btn-sm mt-4 rounded-full font-bold shadow-sm">
                        Belanja Sekarang
                        <x-heroicon-o-arrow-right class="h-3.5 w-3.5" />
                    </a>
                </div>

                <div class="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-primary/20 blur-xl"></div>
                <div class="absolute -bottom-10 right-8 h-32 w-32 rounded-full bg-violet-600/20 blur-lg"></div>
            </section>
        @endif

        {{-- Voucher dan Promo Section --}}
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <x-heroicon-o-ticket class="h-4 w-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-black leading-none">Klaim Voucher Belanja</h3>
                        <p class="text-[11px] text-base-content/60 mt-0.5">Hemat belanja dengan potongan diskon & promo
                            ongkir</p>
                    </div>
                </div>

                <a href="{{ route('store.promos') }}" wire:navigate
                    class="text-xs font-bold text-primary hover:underline shrink-0">
                    Semua Promo
                </a>
            </div>

            @if ($this->activeVouchers->isNotEmpty())
                <div
                    class="flex gap-3 overflow-x-auto pb-1 scroll-smooth [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($this->activeVouchers as $voucher)
                        <div x-data="{ copied: false }"
                            class="relative min-w-[240px] sm:min-w-[260px] shrink-0 rounded-2xl bg-base-100 p-3.5 shadow-sm border border-base-200/80 flex flex-col justify-between">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <span class="badge badge-primary badge-sm font-black tracking-wide">
                                        {{ $voucher->formattedDiscount() }}
                                    </span>
                                    <h4 class="mt-1.5 text-xs font-black line-clamp-1 text-base-content">
                                        {{ $voucher->name }}</h4>
                                </div>
                                <div
                                    class="font-mono text-xs font-black px-2 py-0.5 rounded-lg bg-base-200 text-primary border border-primary/20 shrink-0">
                                    {{ $voucher->code }}
                                </div>
                            </div>

                            <div
                                class="mt-2.5 pt-2 border-t border-dashed border-base-200 flex items-center justify-between text-[11px]">
                                <div class="text-base-content/60">
                                    <span>Min. {{ 'Rp ' . number_format($voucher->min_spend, 0, ',', '.') }}</span>
                                    @if ($voucher->expires_at)
                                        <span class="block text-[10px] text-base-content/40">s/d
                                            {{ $voucher->expires_at->translatedFormat('d M Y') }}</span>
                                    @endif
                                </div>

                                <button type="button"
                                    x-on:click="navigator.clipboard.writeText('{{ $voucher->code }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="btn btn-xs rounded-xl font-bold transition"
                                    :class="copied ? 'btn-success text-white' : 'btn-outline btn-primary'">
                                    <span x-show="!copied">Salin Kode</span>
                                    <span x-show="copied" x-cloak>Tersalin!</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div
                    class="flex items-center justify-between p-3.5 bg-gradient-to-r from-primary/10 via-base-100 to-warning/10 rounded-2xl border border-primary/20 shadow-xs">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-xl bg-primary text-primary-content flex items-center justify-center shrink-0">
                            <x-heroicon-o-ticket class="h-5 w-5" />
                        </div>
                        <div>
                            <h4 class="text-sm font-bold">Klaim Voucher Belanja</h4>
                            <p class="text-xs text-base-content/60">Lihat semua penawaran dan voucher diskon aktif</p>
                        </div>
                    </div>
                    <a href="{{ route('store.promos') }}" wire:navigate
                        class="btn btn-xs sm:btn-sm btn-primary rounded-xl font-bold shrink-0">
                        Lihat Promo
                    </a>
                </div>
            @endif
        </section>

        {{-- Category Section (Perbaikan nama tidak terpotong & tampilan proporsional) --}}
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-black">Kategori</h3>
                    <p class="text-xs text-base-content/60">Temukan produk dari berbagai kategori</p>
                </div>
                <a href="{{ route('store.categories') }}" wire:navigate
                    class="text-xs font-bold text-primary hover:underline">
                    Lihat Semua
                </a>
            </div>

            <div
                class="flex gap-3 overflow-x-auto pb-2 scroll-smooth [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @forelse ($this->categories as $category)
                    <a href="{{ route('store.category.products', $category->slug) }}" wire:navigate
                        class="group w-[80px] sm:w-[92px] shrink-0 flex flex-col items-center text-center transition-transform active:scale-95">
                        <div
                            class="relative flex h-[68px] w-[68px] sm:h-20 sm:w-20 items-center justify-center overflow-hidden rounded-2xl bg-base-100 shadow-xs ring-1 ring-base-300/80 transition group-hover:ring-primary group-hover:shadow-md">
                            @if ($category->image)
                                <img src="{{ Storage::disk('public')->url($category->image) }}"
                                    alt="{{ $category->name }}"
                                    class="h-full w-full object-cover transition duration-300 group-hover:scale-110">
                            @else
                                <div
                                    class="flex flex-col items-center justify-center text-base-content/40 group-hover:text-primary transition">
                                    <x-heroicon-o-squares-2x2 class="h-7 w-7" />
                                </div>
                            @endif
                        </div>

                        <h4
                            class="mt-2 text-xs font-bold leading-tight text-base-content/90 line-clamp-2 px-0.5 group-hover:text-primary transition">
                            {{ $category->name }}
                        </h4>

                        @if ($category->products_count > 0)
                            <span class="text-[10px] text-base-content/40 mt-0.5">
                                {{ $category->products_count }} item
                            </span>
                        @endif
                    </a>
                @empty
                    <div class="w-full rounded-2xl bg-base-100 p-4 text-center text-xs text-base-content/60">
                        Belum ada kategori aktif.
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Produk Terlaris – Best Seller Section (Berdasarkan data transaksi valid/paid) --}}
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    {{-- <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500">
                        <x-heroicon-s-fire class="h-4 w-4" />
                    </div> --}}
                    <div>
                        <h3 class="text-base font-black">Produk Terlaris</h3>
                        <p class="text-xs text-base-content/60">Paling banyak dipesan</p>
                    </div>
                </div>

                <a href="{{ route('store.products') }}" wire:navigate
                    class="text-xs font-bold text-primary hover:underline">
                    Lihat Semua
                </a>
            </div>

            <div class="grid grid-cols-2 gap-3.5">
                @forelse ($this->bestSellers as $product)
                    <a href="{{ route('store.product.detail', $product->slug) }}" wire:navigate
                        class="card group overflow-hidden rounded-3xl bg-base-100 shadow-xs border border-base-200 transition-all duration-200 hover:shadow-md hover:border-primary/30 active:scale-[0.98]">
                        <figure class="relative aspect-square w-full bg-base-200 overflow-hidden">
                            @if ($product->primaryImage?->image)
                                <img src="{{ Storage::disk('public')->url($product->primaryImage->image) }}"
                                    alt="{{ $product->name }}"
                                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-base-content/30">
                                    <x-heroicon-o-photo class="h-10 w-10" />
                                </div>
                            @endif

                            {{-- Badge Terlaris / Varian --}}
                            <div class="absolute left-2 top-2 z-10 flex flex-col gap-1 items-start">
                                <span
                                    class="badge badge-warning badge-xs font-black py-2 px-2 shadow-xs flex items-center gap-1 text-[10px]">
                                    <x-heroicon-s-fire class="h-3 w-3 shrink-0" />
                                    Terlaris
                                </span>
                                @if ($product->variants->count() > 0)
                                    <span
                                        class="badge badge-neutral badge-xs font-bold py-1.5 px-2 text-[9px] bg-black/60 text-white backdrop-blur-xs border-none">
                                        {{ $product->variants->count() }} varian
                                    </span>
                                @endif
                            </div>

                            {{-- Wishlist Button --}}
                            <button type="button" wire:click.stop.prevent="toggleWishlist({{ $product->id }})"
                                class="btn btn-circle btn-xs absolute top-2 right-2 z-20 bg-base-100/90 shadow-sm backdrop-blur-xs transition hover:bg-base-100 active:scale-90"
                                aria-label="Simpan ke wishlist">
                                @if (in_array($product->id, $this->wishlistedProductIds))
                                    <x-heroicon-s-heart class="h-3.5 w-3.5 text-rose-500" />
                                @else
                                    <x-heroicon-o-heart class="h-3.5 w-3.5 text-base-content/60 hover:text-rose-500" />
                                @endif
                            </button>
                        </figure>

                        <div class="p-3 flex flex-col justify-between grow">
                            <div>
                                <span
                                    class="text-[10px] font-bold text-base-content/50 uppercase tracking-wider block">
                                    {{ $product->category?->name ?? $this->appName() }}
                                </span>

                                <h4
                                    class="mt-0.5 line-clamp-2 min-h-[2.5rem] text-xs sm:text-sm font-bold leading-snug text-base-content group-hover:text-primary transition">
                                    {{ $product->name }}
                                </h4>
                            </div>

                            <div class="mt-2.5 pt-2 border-t border-base-200/60">
                                <div class="flex items-baseline justify-between gap-1">
                                    <div>
                                        <p class="text-[10px] text-base-content/50">Mulai dari</p>
                                        <p class="text-sm font-black text-primary">
                                            Rp {{ number_format($this->lowestPrice($product), 0, ',', '.') }}
                                        </p>
                                    </div>

                                    @if (($product->sold_count ?? 0) > 0)
                                        <span class="text-[10px] text-base-content/60 font-medium whitespace-nowrap">
                                            {{ $product->sold_count }} terjual
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="col-span-2 rounded-3xl bg-base-100 p-6 text-center text-xs text-base-content/60">
                        Belum ada data produk terlaris saat ini.
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Produk Terbaru Section --}}
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    {{-- <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <x-heroicon-o-sparkles class="h-4 w-4" />
                    </div> --}}
                    <div>
                        <h3 class="text-base font-black">Produk Terbaru</h3>
                        <p class="text-xs text-base-content/60">Koleksi produk paling fresh</p>
                    </div>
                </div>

                <a href="{{ route('store.products') }}" wire:navigate
                    class="text-xs font-bold text-primary hover:underline">
                    Lihat Semua
                </a>
            </div>

            <div class="grid grid-cols-2 gap-3.5">
                @forelse ($this->latestProducts as $product)
                    <a href="{{ route('store.product.detail', $product->slug) }}" wire:navigate
                        class="card group overflow-hidden rounded-3xl bg-base-100 shadow-xs border border-base-200 transition-all duration-200 hover:shadow-md hover:border-primary/30 active:scale-[0.98]">
                        <figure class="relative aspect-square w-full bg-base-200 overflow-hidden">
                            @if ($product->primaryImage?->image)
                                <img src="{{ Storage::disk('public')->url($product->primaryImage->image) }}"
                                    alt="{{ $product->name }}"
                                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-base-content/30">
                                    <x-heroicon-o-photo class="h-10 w-10" />
                                </div>
                            @endif

                            {{-- Badges --}}
                            <div class="absolute left-2 top-2 z-10 flex flex-col gap-1 items-start">
                                <span class="badge badge-primary badge-xs font-bold py-1.5 px-2 text-[9px] shadow-xs">
                                    Baru
                                </span>
                                @if ($product->variants->count() > 0)
                                    <span
                                        class="badge badge-neutral badge-xs font-bold py-1.5 px-2 text-[9px] bg-black/60 text-white backdrop-blur-xs border-none">
                                        {{ $product->variants->count() }} varian
                                    </span>
                                @endif
                            </div>

                            {{-- Wishlist Button --}}
                            <button type="button" wire:click.stop.prevent="toggleWishlist({{ $product->id }})"
                                class="btn btn-circle btn-xs absolute top-2 right-2 z-20 bg-base-100/90 shadow-sm backdrop-blur-xs transition hover:bg-base-100 active:scale-90"
                                aria-label="Simpan ke wishlist">
                                @if (in_array($product->id, $this->wishlistedProductIds))
                                    <x-heroicon-s-heart class="h-3.5 w-3.5 text-rose-500" />
                                @else
                                    <x-heroicon-o-heart class="h-3.5 w-3.5 text-base-content/60 hover:text-rose-500" />
                                @endif
                            </button>
                        </figure>

                        <div class="p-3 flex flex-col justify-between grow">
                            <div>
                                <span
                                    class="text-[10px] font-bold text-base-content/50 uppercase tracking-wider block">
                                    {{ $product->category?->name ?? $this->appName() }}
                                </span>

                                <h4
                                    class="mt-0.5 line-clamp-2 min-h-[2.5rem] text-xs sm:text-sm font-bold leading-snug text-base-content group-hover:text-primary transition">
                                    {{ $product->name }}
                                </h4>
                            </div>

                            <div class="mt-2.5 pt-2 border-t border-base-200/60">
                                <div class="flex items-baseline justify-between gap-1">
                                    <div>
                                        <p class="text-[10px] text-base-content/50">Mulai dari</p>
                                        <p class="text-sm font-black text-primary">
                                            Rp {{ number_format($this->lowestPrice($product), 0, ',', '.') }}
                                        </p>
                                    </div>

                                    @if ($product->available_stock <= 5 && $product->available_stock > 0)
                                        <span
                                            class="badge badge-error badge-outline badge-xs text-[9px] font-bold py-1 px-1.5">
                                            Sisa {{ $product->available_stock }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="col-span-2 rounded-3xl bg-base-100 p-6 text-center text-xs text-base-content/60">
                        Belum ada produk aktif saat ini.
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Apa Kata Pelanggan Kami? (Testimoni & Review Terverifikasi) --}}
        @if ($this->featuredReviews->isNotEmpty())
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <h3 class="text-base font-black text-base-content">Apa Kata Pelanggan Kami?</h3>
                            <span class="badge badge-warning badge-xs gap-1 font-bold">
                                <x-heroicon-s-star class="h-3 w-3 fill-current" /> 4-5★
                            </span>
                        </div>
                        <p class="text-xs text-base-content/60 mt-0.5">Ulasan jujur dari pembeli terverifikasi
                            {{ $this->appName() }}</p>
                    </div>
                </div>

                <div class="flex gap-3 overflow-x-auto pb-2 scrollbar-none snap-x snap-mandatory">
                    @foreach ($this->featuredReviews as $review)
                        <div
                            class="w-72 shrink-0 snap-start rounded-[2rem] bg-base-100 p-4 border border-base-200/90 shadow-2xs flex flex-col justify-between">
                            <div>
                                {{-- Stars & Date --}}
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center text-amber-400">
                                        @for ($i = 1; $i <= 5; $i++)
                                            @if ($i <= $review->rating)
                                                <x-heroicon-s-star class="h-3.5 w-3.5 fill-current" />
                                            @else
                                                <x-heroicon-s-star class="h-3.5 w-3.5 text-base-content/20" />
                                            @endif
                                        @endfor
                                    </div>
                                    <span class="text-[10px] text-base-content/50">
                                        {{ $review->created_at->format('d M Y') }}
                                    </span>
                                </div>

                                {{-- Review Quote --}}
                                <p class="text-xs text-base-content/80 line-clamp-3 leading-relaxed italic">
                                    "{{ $review->review }}"
                                </p>

                                {{-- Customer Photo Preview if any --}}
                                @if ($review->images->isNotEmpty())
                                    <div class="flex gap-1.5 mt-2.5">
                                        @foreach ($review->images->take(3) as $img)
                                            <div
                                                class="h-10 w-10 rounded-xl overflow-hidden border border-base-200 shrink-0">
                                                <img src="{{ $img->url }}" alt="Foto ulasan"
                                                    class="h-full w-full object-cover" />
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="mt-3 pt-3 border-t border-base-200/80">
                                {{-- Customer Info --}}
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-1.5">
                                        <div
                                            class="h-5 w-5 rounded-full bg-primary/10 text-primary text-[10px] font-bold flex items-center justify-center uppercase">
                                            {{ substr($review->customer?->name ?? 'P', 0, 1) }}
                                        </div>
                                        <span class="text-[11px] font-bold text-base-content">
                                            {{ $review->customer_masked_name }}
                                        </span>
                                    </div>
                                    @if ($review->is_verified_purchase)
                                        <span
                                            class="badge badge-success badge-xs gap-0.5 text-[9px] font-bold py-0.5 px-1.5">
                                            <x-heroicon-s-check-badge class="h-2.5 w-2.5" /> Terverifikasi
                                        </span>
                                    @endif
                                </div>

                                {{-- Reviewed Product Card Link --}}
                                @if ($review->product)
                                    <a href="{{ route('store.product.detail', $review->product->slug) }}"
                                        wire:navigate
                                        class="flex items-center gap-2 p-1.5 rounded-xl bg-base-200/60 hover:bg-base-200 transition group">
                                        <div class="h-8 w-8 rounded-lg overflow-hidden bg-base-300 shrink-0">
                                            @if ($review->product->primaryImage?->image)
                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($review->product->primaryImage->image) }}"
                                                    alt="{{ $review->product->name }}"
                                                    class="h-full w-full object-cover" />
                                            @else
                                                <div class="h-full w-full flex items-center justify-center">
                                                    <x-heroicon-o-photo class="h-4 w-4 text-base-content/30" />
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="text-[11px] font-bold text-base-content truncate group-hover:text-primary transition">
                                                {{ $review->product->name }}
                                            </p>
                                        </div>
                                        <x-heroicon-o-chevron-right class="h-3 w-3 text-base-content/40 shrink-0" />
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Bantuan & Customer Service CTA (WhatsApp Langsung Terhubung ke Database / .env) --}}
        <section
            class="rounded-[2rem] bg-gradient-to-br from-base-100 to-base-200/90 p-4 sm:p-5 shadow-xs border border-base-200">
            <div class="flex items-center gap-3 mb-2">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-500">
                    <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2m.01 1.67c2.2 0 4.26.86 5.82 2.42a8.225 8.225 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.196 8.196 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24m4.52 11.66c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.02-1.25-.75-.67-1.26-1.5-1.41-1.75-.14-.25-.02-.39.11-.51.11-.11.25-.29.38-.44.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.44-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1s.9 2.43 1.03 2.6c.12.17 1.77 2.7 4.29 3.79.6.26 1.07.41 1.44.53.6.19 1.15.16 1.58.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.1-.23-.17-.48-.29" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm sm:text-base font-black text-base-content">{{ $this->csTitle() }}</h3>
                    <p class="text-xs text-base-content/60">{{ $this->csSubtitle() }}</p>
                </div>
            </div>

            <p class="text-xs text-base-content/70 leading-relaxed mb-3.5">
                {{ $this->csDescription() }}
            </p>

            @if ($this->isCsAvailable())
                <a href="{{ $this->csWhatsAppUrl() }}" target="_blank" rel="noopener noreferrer"
                    style="background-color: #25D366 !important; color: #ffffff !important;"
                    class="btn w-full rounded-2xl border-none shadow-xs flex items-center justify-center gap-2 font-bold text-xs sm:text-sm py-2.5 transition hover:brightness-95 active:scale-[0.99] text-white">
                    <svg class="h-4 w-4 shrink-0 text-white"
                        style="fill: #ffffff !important; color: #ffffff !important;" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path
                            d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2m.01 1.67c2.2 0 4.26.86 5.82 2.42a8.225 8.225 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.196 8.196 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24m4.52 11.66c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.02-1.25-.75-.67-1.26-1.5-1.41-1.75-.14-.25-.02-.39.11-.51.11-.11.25-.29.38-.44.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.44-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1s.9 2.43 1.03 2.6c.12.17 1.77 2.7 4.29 3.79.6.26 1.07.41 1.44.53.6.19 1.15.16 1.58.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.1-.23-.17-.48-.29" />
                    </svg>
                    <span style="color: #ffffff !important;"
                        class="text-white font-bold">{{ $this->csButtonText() }}</span>
                </a>
            @else
                <div class="rounded-xl bg-base-200 p-2.5 text-xs text-base-content/60 flex items-center gap-2">
                    <x-heroicon-o-information-circle class="h-4 w-4 shrink-0 text-base-content/40" />
                    <span>Layanan Customer Service WhatsApp saat ini sedang offline.</span>
                </div>
            @endif
        </section>
    </main>

    {{-- Modal Prompt Subscription Promo Saat Baru Login/Register --}}
    @if ($showPromoModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-base-100 rounded-3xl p-6 max-w-sm w-full shadow-2xl border border-base-200 text-center">
                <div
                    class="w-16 h-16 bg-gradient-to-tr from-primary to-warning text-white rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-md">
                    <x-heroicon-o-ticket class="h-8 w-8" />
                </div>
                <h3 class="text-lg font-black text-base-content">Mau Info Promo & Voucher?</h3>
                <p class="text-xs text-base-content/70 mt-2 leading-relaxed">
                    Dapatkan kabar promo terbaru, voucher diskon spesial, dan potongan ongkir langsung melalui email dan
                    notifikasi akun Anda!
                </p>
                <div class="mt-6 space-y-2">
                    <button type="button" wire:click="acceptPromoSubscription"
                        class="btn btn-primary w-full rounded-2xl font-bold">
                        Ya, Aktifkan Promo!
                    </button>
                    <button type="button" wire:click="declinePromoSubscription"
                        class="btn btn-ghost btn-sm text-base-content/60 w-full rounded-xl">
                        Nanti Saja
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
