<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Banner;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Beranda')] class extends Component {
    public string $search = '';

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
        return Banner::query()->active()->orderBy('sort_order')->latest()->limit(5)->get();
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->where('is_active', true)->latest()->limit(8)->get();
    }

    #[Computed]
    public function products()
    {
        return Product::query()
            ->with(['category', 'primaryImage', 'variants'])
            ->where('is_active', true)
            ->latest()
            ->limit(12)
            ->get();
    }

    public function lowestPrice($product): float
    {
        $variantPrice = $product->variants->where('is_active', true)->min('price');

        return (float) ($variantPrice ?: $product->price);
    }

    public function customerName(): string
    {
        return Auth::guard('customer')->user()?->name ?? config('app.name');
    }
};
?>

<div class="min-h-screen bg-base-200">
    {{-- Header --}}
    <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur">
        <div class="space-y-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-base-content/50">Selamat datang</p>
                    <h1 class="text-xl font-bold leading-tight">
                        {{ Auth::guard('customer')->user()->name ?? config('app.name') }}</h1>
                </div>

                <div class="flex items-center gap-1">
                    <x-store.theme-toggle />

                    {{-- <button class="btn btn-circle btn-ghost btn-sm">
                        <x-heroicon-o-bell class="h-5 w-5" />
                    </button> --}}
                </div>
            </div>

            <form wire:submit="searchProducts">
                <div
                    class="flex h-11 items-center gap-2 rounded-full border border-base-300 bg-base-200 px-4 transition
                       focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                    <x-heroicon-o-magnifying-glass class="h-5 w-5 shrink-0 text-base-content/40" />

                    <input type="text" wire:model="search"
                        class="grow border-0 bg-transparent text-sm outline-none placeholder:text-base-content/40
                           focus:border-0 focus:outline-none focus:ring-0"
                        placeholder="Cari dodol, hampers, snack..." />

                    @if ($search)
                        <button type="button" wire:click="$set('search', '')" class="btn btn-ghost btn-xs btn-circle">
                            <x-heroicon-o-x-mark class="h-4 w-4" />
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </header>

    <main class="space-y-6 p-4">
        {{-- Hero --}}
        {{-- <section
            class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-amber-100 via-orange-100 to-yellow-50 p-5 shadow-sm">
            <div class="relative z-10 max-w-[75%]">
                <div class="badge badge-warning badge-sm mb-3">Produk Lokal</div>

                <h2 class="text-2xl font-black leading-tight text-amber-950">
                    Belanja produk UMKM favoritmu
                </h2>

                <p class="mt-2 text-sm leading-relaxed text-amber-900/80">
                    Fresh, homemade, dan siap dikirim langsung ke rumah.
                </p>

                <button class="btn btn-warning btn-sm mt-4 rounded-full">
                    Belanja sekarang
                    <x-heroicon-o-arrow-right class="h-4 w-4" />
                </button>
            </div>

            <div class="absolute -right-8 -top-8 h-36 w-36 rounded-full bg-amber-300/40"></div>
            <div class="absolute -bottom-10 right-8 h-28 w-28 rounded-full bg-orange-300/40"></div>
        </section> --}}
        {{-- Banners --}}
        @if ($this->banners->isNotEmpty())
            <section>
                <div
                    class="flex gap-4 overflow-x-auto pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($this->banners as $banner)
                        <a href="{{ $banner->button_url ?: route('store.products') }}" wire:navigate
                            class="relative min-h-44 w-full shrink-0 overflow-hidden rounded-[2rem] bg-gradient-to-br from-amber-100 via-orange-100 to-yellow-50 p-5 shadow-sm">
                            @if ($banner->image)
                                <img src="{{ Storage::disk('public')->url($banner->image) }}" alt="{{ $banner->title }}"
                                    class="absolute inset-0 h-full w-full object-cover">

                                <div
                                    class="absolute inset-0 bg-gradient-to-r from-black/60 via-black/30 to-transparent">
                                </div>
                            @else
                                <div class="absolute -right-8 -top-8 h-36 w-36 rounded-full bg-amber-300/40"></div>
                                <div class="absolute -bottom-10 right-8 h-28 w-28 rounded-full bg-orange-300/40"></div>
                            @endif

                            <div class="relative z-10 max-w-[75%]">
                                @if ($banner->badge)
                                    <div class="badge badge-warning badge-sm mb-3">
                                        {{ $banner->badge }}
                                    </div>
                                @endif

                                <h2
                                    class="text-2xl font-black leading-tight {{ $banner->image ? 'text-white' : 'text-amber-950' }}">
                                    {{ $banner->title }}
                                </h2>

                                @if ($banner->subtitle)
                                    <p
                                        class="mt-2 text-sm leading-relaxed {{ $banner->image ? 'text-white/80' : 'text-amber-900/80' }}">
                                        {{ $banner->subtitle }}
                                    </p>
                                @endif

                                @if ($banner->button_text)
                                    <div class="btn btn-warning btn-sm mt-4 rounded-full">
                                        {{ $banner->button_text }}
                                        <x-heroicon-o-arrow-right class="h-4 w-4" />
                                    </div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @else
            <section
                class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-amber-100 via-orange-100 to-yellow-50 p-5 shadow-sm">
                <div class="relative z-10 max-w-[75%]">
                    <div class="badge badge-warning badge-sm mb-3">Produk Lokal</div>

                    <h2 class="text-2xl font-black leading-tight text-amber-950">
                        Belanja produk UMKM favoritmu
                    </h2>

                    <p class="mt-2 text-sm leading-relaxed text-amber-900/80">
                        Fresh, homemade, dan siap dikirim langsung ke rumah.
                    </p>

                    <a href="{{ route('store.products') }}" wire:navigate
                        class="btn btn-warning btn-sm mt-4 rounded-full">
                        Belanja sekarang
                        <x-heroicon-o-arrow-right class="h-4 w-4" />
                    </a>
                </div>

                <div class="absolute -right-8 -top-8 h-36 w-36 rounded-full bg-amber-300/40"></div>
                <div class="absolute -bottom-10 right-8 h-28 w-28 rounded-full bg-orange-300/40"></div>
            </section>
        @endif

        {{-- Categories --}}
        <section>
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-bold">Kategori</h3>
                <a href="{{ route('store.categories') }}" wire:navigate class="text-sm font-medium text-primary">Lihat
                    semua</a>
            </div>

            <div
                class="flex gap-3 overflow-x-auto pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @forelse ($this->categories as $category)
                    <a href="{{ route('store.category.products', $category->slug) }}" wire:navigate class="shrink-0">
                        <div
                            class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl bg-base-100 shadow-sm ring-1 ring-base-300">
                            @if ($category->image)
                                <img src="{{ Storage::disk('public')->url($category->image) }}"
                                    alt="{{ $category->name }}" class="h-full w-full object-cover">
                            @else
                                <x-heroicon-o-squares-2x2 class="h-7 w-7 text-base-content/40" />
                            @endif
                        </div>

                        <p class="mt-2 w-16 truncate text-center text-xs font-medium">
                            {{ $category->name }}
                        </p>
                    </a>
                @empty
                    <div class="rounded-2xl bg-base-100 p-4 text-sm text-base-content/60">
                        Belum ada kategori aktif.
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Quick menu --}}
        {{-- <section class="grid grid-cols-3 gap-3">
            <div class="rounded-2xl bg-base-100 p-3 shadow-sm">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                    <x-heroicon-o-truck class="h-5 w-5" />
                </div>
                <p class="text-xs font-semibold">Kirim cepat</p>
            </div>

            <div class="rounded-2xl bg-base-100 p-3 shadow-sm">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-xl bg-green-100 text-green-700">
                    <x-heroicon-o-shield-check class="h-5 w-5" />
                </div>
                <p class="text-xs font-semibold">Aman</p>
            </div>

            <div class="rounded-2xl bg-base-100 p-3 shadow-sm">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-xl bg-blue-100 text-blue-700">
                    <x-heroicon-o-credit-card class="h-5 w-5" />
                </div>
                <p class="text-xs font-semibold">Bayar mudah</p>
            </div>
        </section> --}}

        {{-- Products --}}
        <section>
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-bold">Produk Terbaru</h3>
                <a href="{{ route('store.products') }}" wire:navigate class="text-sm font-medium text-primary">Lihat
                    semua</a>
            </div>

            <div class="grid grid-cols-2 gap-3">
                @forelse ($this->products as $product)
                    <a href="{{ route('store.product.detail', $product->slug) }}" wire:navigate
                        class="card overflow-hidden rounded-3xl bg-base-100 shadow-sm transition active:scale-[0.98]">
                        <figure class="relative aspect-square bg-base-300">
                            @if ($product->primaryImage?->image)
                                <img src="{{ Storage::disk('public')->url($product->primaryImage->image) }}"
                                    alt="{{ $product->name }}" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center">
                                    <x-heroicon-o-photo class="h-10 w-10 text-base-content/30" />
                                </div>
                            @endif

                            @if ($product->variants->count() > 0)
                                <div class="badge badge-warning badge-sm absolute left-2 top-2">
                                    {{ $product->variants->count() }} varian
                                </div>
                            @endif
                        </figure>

                        <div class="p-3">
                            <p class="truncate text-[11px] text-base-content/50">
                                {{ $product->category?->name ?? '-' }}
                            </p>

                            <h4 class="mt-1 line-clamp-2 min-h-10 text-sm font-bold leading-tight">
                                {{ $product->name }}
                            </h4>

                            <div class="mt-2">
                                <p class="text-[11px] text-base-content/50">Mulai dari</p>
                                <p class="font-black text-primary">
                                    Rp {{ number_format($this->lowestPrice($product), 0, ',', '.') }}
                                </p>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="col-span-2 rounded-3xl bg-base-100 p-6 text-center text-sm text-base-content/60">
                        Belum ada produk aktif.
                    </div>
                @endforelse
            </div>
        </section>
    </main>
</div>
