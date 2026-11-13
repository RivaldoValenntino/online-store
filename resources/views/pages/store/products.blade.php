<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Produk')] class extends Component {
    use WithPagination;

    #[Url(as: 'search', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: 'recommendation')]
    public string $sort = 'recommendation';

    #[Url(as: 'category', except: '')]
    public string $category = '';

    #[Url(as: 'min_price', except: null)]
    public ?string $price_min = null;

    #[Url(as: 'max_price', except: null)]
    public ?string $price_max = null;

    #[Url(as: 'stock', except: 'all')]
    public string $stock_status = 'all';

    #[Url(as: 'rating', except: 'all')]
    public string $rating_min = 'all';

    // Modal state
    public bool $showSortModal = false;
    public bool $showFilterModal = false;

    // Temporary inputs inside Filter Modal
    public string $temp_category = '';
    public ?string $temp_price_min = null;
    public ?string $temp_price_max = null;
    public string $temp_stock_status = 'all';
    public string $temp_rating_min = 'all';

    public function mount(): void
    {
        $this->syncTempFilters();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function openSortModal(): void
    {
        $this->showSortModal = true;
    }

    public function closeSortModal(): void
    {
        $this->showSortModal = false;
    }

    public function selectSort(string $sortKey): void
    {
        if (array_key_exists($sortKey, $this->sortOptions())) {
            $this->sort = $sortKey;
            $this->resetPage();
        }
        $this->showSortModal = false;
    }

    public function openFilterModal(): void
    {
        $this->syncTempFilters();
        $this->showFilterModal = true;
    }

    public function closeFilterModal(): void
    {
        $this->showFilterModal = false;
    }

    public function syncTempFilters(): void
    {
        $this->temp_category = $this->category;
        $this->temp_price_min = $this->price_min;
        $this->temp_price_max = $this->price_max;
        $this->temp_stock_status = $this->stock_status ?: 'all';
        $this->temp_rating_min = $this->rating_min ?: 'all';
    }

    public function applyFilters(): void
    {
        $this->category = $this->temp_category;
        $this->price_min = (filled($this->temp_price_min) && is_numeric($this->temp_price_min) && (float) $this->temp_price_min >= 0)
            ? (string) ((int) $this->temp_price_min)
            : null;
        $this->price_max = (filled($this->temp_price_max) && is_numeric($this->temp_price_max) && (float) $this->temp_price_max >= 0)
            ? (string) ((int) $this->temp_price_max)
            : null;
        $this->stock_status = in_array($this->temp_stock_status, ['all', 'in_stock', 'out_of_stock'], true)
            ? $this->temp_stock_status
            : 'all';
        $this->rating_min = in_array((string) $this->temp_rating_min, ['all', '3', '4'], true)
            ? (string) $this->temp_rating_min
            : 'all';

        $this->showFilterModal = false;
        $this->resetPage();
    }

    public function resetFilterInputs(): void
    {
        $this->temp_category = '';
        $this->temp_price_min = null;
        $this->temp_price_max = null;
        $this->temp_stock_status = 'all';
        $this->temp_rating_min = 'all';
    }

    public function resetFilters(): void
    {
        $this->category = '';
        $this->price_min = null;
        $this->price_max = null;
        $this->stock_status = 'all';
        $this->rating_min = 'all';
        $this->syncTempFilters();
        $this->resetPage();
    }

    public function resetAll(): void
    {
        $this->search = '';
        $this->sort = 'recommendation';
        $this->resetFilters();
    }

    public function removeFilter(string $type): void
    {
        match ($type) {
            'category' => $this->category = '',
            'price' => [
                $this->price_min = null,
                $this->price_max = null,
            ],
            'stock' => $this->stock_status = 'all',
            'rating' => $this->rating_min = 'all',
            default => null,
        };

        $this->syncTempFilters();
        $this->resetPage();
    }

    #[Computed]
    public function sortOptions(): array
    {
        return [
            'recommendation' => 'Rekomendasi',
            'latest' => 'Terbaru',
            'bestselling' => 'Terlaris',
            'price_asc' => 'Harga Terendah',
            'price_desc' => 'Harga Tertinggi',
            'rating_desc' => 'Rating Tertinggi',
        ];
    }

    #[Computed]
    public function categories()
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function activeCategoryName(): ?string
    {
        if (blank($this->category)) {
            return null;
        }

        $cat = $this->categories->first(function ($c) {
            return (string) $c->id === (string) $this->category || $c->slug === $this->category;
        });

        return $cat?->name ?? (string) $this->category;
    }

    #[Computed]
    public function activeFiltersCount(): int
    {
        $count = 0;

        if (filled($this->category)) {
            $count++;
        }

        if ((filled($this->price_min) && is_numeric($this->price_min)) || (filled($this->price_max) && is_numeric($this->price_max))) {
            $count++;
        }

        if ($this->stock_status && $this->stock_status !== 'all') {
            $count++;
        }

        if ($this->rating_min && $this->rating_min !== 'all') {
            $count++;
        }

        return $count;
    }

    #[Computed]
    public function products()
    {
        $query = Product::query()
            ->select('products.*')
            ->with([
                'category',
                'primaryImage',
                'variants' => fn($v) => $v->where('is_active', true),
            ])
            ->where('is_active', true);

        // Pencarian (Search)
        if (trim($this->search) !== '') {
            $search = trim($this->search);
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.description', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($catQuery) use ($search) {
                        $catQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('variants', function ($varQuery) use ($search) {
                        $varQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        // Filter Kategori
        if (filled($this->category)) {
            if (is_numeric($this->category)) {
                $query->where('products.category_id', (int) $this->category);
            } else {
                $query->whereHas('category', fn($q) => $q->where('slug', $this->category));
            }
        }

        // Filter Rentang Harga (Min & Max)
        if (filled($this->price_min) && is_numeric($this->price_min)) {
            $min = (float) $this->price_min;
            $query->where(function ($q) use ($min) {
                $q->where('products.price', '>=', $min)
                    ->orWhereHas('variants', fn($v) => $v->where('is_active', true)->where('price', '>=', $min));
            });
        }

        if (filled($this->price_max) && is_numeric($this->price_max)) {
            $max = (float) $this->price_max;
            $query->where(function ($q) use ($max) {
                $q->where(function ($standalone) use ($max) {
                    $standalone->whereDoesntHave('variants')
                        ->where('products.price', '<=', $max);
                })->orWhereHas('variants', fn($v) => $v->where('is_active', true)->where('price', '<=', $max));
            });
        }

        // Filter Ketersediaan Stok
        if ($this->stock_status === 'in_stock') {
            $query->where(function ($q) {
                $q->whereHas('variants', function ($v) {
                    $v->where('is_active', true)
                        ->whereRaw('(stock - COALESCE(reserved_stock, 0)) > 0');
                })->orWhere(function ($p) {
                    $p->whereDoesntHave('variants')
                        ->whereRaw('(stock - COALESCE(reserved_stock, 0)) > 0');
                });
            });
        } elseif ($this->stock_status === 'out_of_stock') {
            $query->where(function ($q) {
                $q->where(function ($vGroup) {
                    $vGroup->whereHas('variants')
                        ->whereDoesntHave('variants', function ($v) {
                            $v->where('is_active', true)
                                ->whereRaw('(stock - COALESCE(reserved_stock, 0)) > 0');
                        });
                })->orWhere(function ($p) {
                    $p->whereDoesntHave('variants')
                        ->whereRaw('(stock - COALESCE(reserved_stock, 0)) <= 0');
                });
            });
        }

        // Filter Rating Minimal
        if ($this->rating_min === '3' || $this->rating_min === 3) {
            $query->where('products.rating_avg', '>=', 3.0);
        } elseif ($this->rating_min === '4' || $this->rating_min === 4) {
            $query->where('products.rating_avg', '>=', 4.0);
        }

        // Sortir Produk
        $sort = in_array($this->sort, array_keys($this->sortOptions()), true) ? $this->sort : 'recommendation';

        if ($sort === 'latest') {
            $query->latest('products.id');
        } elseif ($sort === 'bestselling') {
            $query->withSum(
                [
                    'orderItems as sold_count' => function ($q) {
                        $q->whereHas('order', function ($o) {
                            $o->where('payment_status', 'paid');
                        });
                    },
                ],
                'qty'
            )->orderByDesc('sold_count')->latest('products.id');
        } elseif ($sort === 'price_asc') {
            $query->selectRaw('COALESCE((SELECT MIN(price) FROM product_variants WHERE product_variants.product_id = products.id AND product_variants.is_active = 1), products.price) as sorting_price')
                ->orderBy('sorting_price', 'asc')
                ->orderBy('products.id', 'desc');
        } elseif ($sort === 'price_desc') {
            $query->selectRaw('COALESCE((SELECT MAX(price) FROM product_variants WHERE product_variants.product_id = products.id AND product_variants.is_active = 1), products.price) as sorting_price')
                ->orderBy('sorting_price', 'desc')
                ->orderBy('products.id', 'desc');
        } elseif ($sort === 'rating_desc') {
            $query->orderByDesc('products.rating_avg')
                ->orderByDesc('products.reviews_count')
                ->latest('products.id');
        } else {
            // recommendation (default)
            $query->withSum(
                [
                    'orderItems as sold_count' => function ($q) {
                        $q->whereHas('order', function ($o) {
                            $o->where('payment_status', 'paid');
                        });
                    },
                ],
                'qty'
            )
            ->orderByDesc('sold_count')
            ->orderByDesc('products.rating_avg')
            ->latest('products.id');
        }

        return $query->paginate(12);
    }

    public function lowestPrice($product): float
    {
        $activeVariants = $product->variants->where('is_active', true);
        if ($activeVariants->isNotEmpty()) {
            return (float) $activeVariants->min('price');
        }

        return (float) $product->price;
    }

    #[Computed]
    public function wishlistedProductIds(): array
    {
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            return [];
        }

        return $customer->wishlists()->pluck('product_id')->all();
    }

    public function toggleWishlist(int $productId): void
    {
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            $this->redirectRoute('customer.login', navigate: true);
            return;
        }

        $wishlist = Wishlist::where('customer_id', $customer->id)
            ->where('product_id', $productId)
            ->first();

        if ($wishlist) {
            $wishlist->delete();
            $this->dispatch('notify',
                type: 'info',
                title: 'Dihapus dari Wishlist',
                message: 'Produk telah dihapus dari wishlist kamu.'
            );
        } else {
            Wishlist::create([
                'customer_id' => $customer->id,
                'product_id' => $productId,
            ]);
            $this->dispatch('notify',
                type: 'success',
                title: 'Berhasil Disimpan',
                message: 'Produk berhasil disimpan ke wishlist.'
            );
        }

        unset($this->wishlistedProductIds);
        $this->dispatch('wishlist-updated');
    }
};
?>

<div class="min-h-screen bg-base-200 pb-24">
    <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur">
        <div class="space-y-3 p-4">
            {{-- Top Header Row --}}
            <div class="flex items-center gap-3">
                <a href="{{ route('store.home') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                    <x-heroicon-o-chevron-left class="h-5 w-5" />
                </a>

                <div class="min-w-0 flex-1">
                    <h1 class="text-lg font-black tracking-tight text-base-content">Semua Produk</h1>
                    <p class="text-xs text-base-content/50">
                        {{ $this->products->total() }} produk ditemukan
                    </p>
                </div>
            </div>

            {{-- Search Bar --}}
            <div class="flex h-11 items-center gap-2 rounded-full border border-base-300 bg-base-200 px-4 transition
                       focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                <x-heroicon-o-magnifying-glass class="h-5 w-5 shrink-0 text-base-content/40" />

                <input type="text" wire:model.live.debounce.400ms="search" class="grow border-0 bg-transparent text-sm outline-none placeholder:text-base-content/40
                           focus:border-0 focus:outline-none focus:ring-0" placeholder="Cari produk..." />

                @if ($search)
                <button type="button" wire:click="clearSearch" class="btn btn-ghost btn-xs btn-circle" title="Hapus pencarian">
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                </button>
                @endif
            </div>

            {{-- Action Buttons: Urutkan & Filter --}}
            <div class="flex items-center gap-2 pt-0.5">
                {{-- Tombol Urutkan --}}
                <button type="button" wire:click="openSortModal"
                    class="btn btn-sm grow rounded-full border border-base-300 bg-base-100 hover:bg-base-200 text-xs font-bold gap-1.5 shadow-2xs">
                    <x-heroicon-o-arrows-up-down class="h-4 w-4 text-primary shrink-0" />
                    <span class="truncate">Urutkan: {{ $this->sortOptions[$sort] ?? 'Rekomendasi' }}</span>
                    <x-heroicon-o-chevron-down class="h-3 w-3 opacity-60 ml-auto shrink-0" />
                </button>

                {{-- Tombol Filter --}}
                <button type="button" wire:click="openFilterModal"
                    class="btn btn-sm rounded-full border border-base-300 text-xs font-bold gap-1.5 shadow-2xs {{ $this->activeFiltersCount > 0 ? 'btn-primary text-primary-content border-primary' : 'bg-base-100 hover:bg-base-200 text-base-content' }}">
                    <x-heroicon-o-adjustments-horizontal class="h-4 w-4 shrink-0" />
                    <span>Filter</span>
                    @if ($this->activeFiltersCount > 0)
                        <span class="badge badge-xs bg-base-100 text-primary border-none font-black px-1.5">
                            {{ $this->activeFiltersCount }}
                        </span>
                    @endif
                </button>
            </div>

            {{-- Active Filter Chips --}}
            @if ($this->activeFiltersCount > 0)
                <div class="flex flex-wrap items-center gap-1.5 pt-1 border-t border-base-200">
                    <span class="text-[11px] font-bold text-base-content/50 mr-0.5">Filter aktif:</span>

                    {{-- Chip Kategori --}}
                    @if (filled($this->category))
                        <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 text-primary border border-primary/20 text-[11px] font-bold px-2.5 py-1">
                            <span class="truncate max-w-[120px]">{{ $this->activeCategoryName }}</span>
                            <button type="button" wire:click="removeFilter('category')" class="hover:text-primary/70 ml-0.5" title="Hapus filter kategori">
                                <x-heroicon-o-x-mark class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif

                    {{-- Chip Rentang Harga --}}
                    @if (filled($this->price_min) || filled($this->price_max))
                        <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 text-primary border border-primary/20 text-[11px] font-bold px-2.5 py-1">
                            <span>
                                @if (filled($this->price_min) && filled($this->price_max))
                                    Rp {{ number_format((float)$this->price_min, 0, ',', '.') }} - {{ number_format((float)$this->price_max, 0, ',', '.') }}
                                @elseif (filled($this->price_min))
                                    &ge; Rp {{ number_format((float)$this->price_min, 0, ',', '.') }}
                                @else
                                    &le; Rp {{ number_format((float)$this->price_max, 0, ',', '.') }}
                                @endif
                            </span>
                            <button type="button" wire:click="removeFilter('price')" class="hover:text-primary/70 ml-0.5" title="Hapus filter harga">
                                <x-heroicon-o-x-mark class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif

                    {{-- Chip Stok --}}
                    @if ($this->stock_status && $this->stock_status !== 'all')
                        <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 text-primary border border-primary/20 text-[11px] font-bold px-2.5 py-1">
                            <span>{{ $this->stock_status === 'in_stock' ? 'Tersedia' : 'Habis' }}</span>
                            <button type="button" wire:click="removeFilter('stock')" class="hover:text-primary/70 ml-0.5" title="Hapus filter stok">
                                <x-heroicon-o-x-mark class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif

                    {{-- Chip Rating --}}
                    @if ($this->rating_min && $this->rating_min !== 'all')
                        <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 text-primary border border-primary/20 text-[11px] font-bold px-2.5 py-1">
                            <span class="flex items-center gap-0.5">
                                <x-heroicon-s-star class="h-3 w-3 fill-current text-amber-500" />
                                {{ $this->rating_min }}+ Bintang
                            </span>
                            <button type="button" wire:click="removeFilter('rating')" class="hover:text-primary/70 ml-0.5" title="Hapus filter rating">
                                <x-heroicon-o-x-mark class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif

                    {{-- Reset Semua Chip --}}
                    <button type="button" wire:click="resetFilters" class="text-[11px] font-bold text-error hover:underline ml-1">
                        Reset Filter
                    </button>
                </div>
            @endif
        </div>
    </header>

    <main class="p-4">
        @if ($this->products->isEmpty())
        <div class="rounded-[2.5rem] bg-base-100 p-8 text-center shadow-xs border border-base-200">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-[2rem] bg-primary/10 text-primary">
                <x-heroicon-o-magnifying-glass class="h-10 w-10" />
            </div>

            <h2 class="mt-4 text-lg font-black text-base-content">Produk tidak ditemukan</h2>
            <p class="mt-2 text-xs text-base-content/60 max-w-xs mx-auto leading-relaxed">
                Tidak ada produk yang cocok dengan kombinasi pencarian atau filter yang Anda pilih.
            </p>

            <div class="mt-5 flex justify-center">
                <button type="button" wire:click="resetAll" class="btn btn-sm btn-primary rounded-full px-5 font-bold">
                    Reset Filter & Pencarian
                </button>
            </div>
        </div>
        @else
        <div class="grid grid-cols-2 gap-3">
            @foreach ($this->products as $product)
            <a href="{{ route('store.product.detail', $product->slug) }}" wire:navigate
                class="card overflow-hidden rounded-3xl bg-base-100 shadow-sm transition active:scale-[0.98] border border-base-200/80 hover:shadow-md flex flex-col justify-between">
                <figure class="relative aspect-square w-full bg-base-300">
                    @if ($product->primaryImage?->image)
                    <img src="{{ Storage::disk('public')->url($product->primaryImage->image) }}"
                        alt="{{ $product->name }}" class="h-full w-full object-cover">
                    @else
                    <div class="flex h-full w-full items-center justify-center">
                        <x-heroicon-o-photo class="h-10 w-10 text-base-content/30" />
                    </div>
                    @endif

                    @if ($product->variants->count() > 0)
                    <div class="badge badge-warning badge-xs font-bold absolute left-2 top-2" style="position: absolute; top: 8px; left: 8px;">
                        {{ $product->variants->count() }} varian
                    </div>
                    @endif

                    @if ($product->available_stock <= 0)
                    <div class="badge badge-neutral badge-xs font-bold absolute left-2 bottom-2 opacity-90" style="position: absolute; bottom: 8px; left: 8px;">
                        Stok Habis
                    </div>
                    @endif

                    <button type="button" wire:click.stop.prevent="toggleWishlist({{ $product->id }})"
                        class="btn btn-circle btn-xs absolute top-2 right-2 z-10 bg-base-100/90 shadow transition hover:bg-base-100 active:scale-90"
                        style="position: absolute; top: 8px; right: 8px;"
                        aria-label="Wishlist">
                        @if (in_array($product->id, $this->wishlistedProductIds))
                            <x-heroicon-s-heart class="h-3.5 w-3.5 text-rose-500" />
                        @else
                            <x-heroicon-o-heart class="h-3.5 w-3.5 text-base-content/60" />
                        @endif
                    </button>
                </figure>

                <div class="p-3 flex flex-col justify-between grow">
                    <div>
                        <div class="flex items-center justify-between gap-1">
                            <p class="truncate text-[11px] text-base-content/50">
                                {{ $product->category?->name ?? 'Lainnya' }}
                            </p>

                            @if ($product->rating_avg > 0)
                                <div class="flex items-center gap-0.5 text-[10px] text-amber-500 font-bold shrink-0">
                                    <x-heroicon-s-star class="h-3 w-3 fill-current" />
                                    <span>{{ number_format((float)$product->rating_avg, 1) }}</span>
                                    @if ($product->reviews_count > 0)
                                        <span class="text-base-content/40 text-[9px]">({{ $product->reviews_count }})</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <h4 class="mt-1 line-clamp-2 min-h-9 text-xs sm:text-sm font-bold leading-snug text-base-content">
                            {{ $product->name }}
                        </h4>
                    </div>

                    <div class="mt-2.5 pt-2 border-t border-base-200/80">
                        <p class="text-[10px] text-base-content/50">Mulai dari</p>
                        <p class="font-black text-sm sm:text-base text-primary">
                            Rp {{ number_format($this->lowestPrice($product), 0, ',', '.') }}
                        </p>
                    </div>
                </div>
            </a>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if ($this->products->hasPages())
            <div class="mt-6 rounded-[2rem] bg-base-100 p-3 shadow-xs border border-base-200 flex justify-center">
                {{ $this->products->links() }}
            </div>
        @endif
        @endif
    </main>

    {{-- Bottom Sheet / Modal Urutkan (Sort Modal) --}}
    @if ($showSortModal)
        <div class="modal modal-bottom sm:modal-middle modal-open z-50">
            <div class="modal-box rounded-t-[2.5rem] sm:rounded-[2rem] max-w-md w-full bg-base-100 p-5 shadow-2xl border border-base-200">
                <div class="flex items-center justify-between pb-3 border-b border-base-200">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <x-heroicon-o-arrows-up-down class="h-4 w-4" />
                        </div>
                        <h3 class="text-base font-black text-base-content">Urutkan Produk</h3>
                    </div>
                    <button type="button" wire:click="closeSortModal" class="btn btn-circle btn-xs btn-ghost">
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                    </button>
                </div>

                <div class="py-3 space-y-1">
                    @foreach ($this->sortOptions as $key => $label)
                        <button type="button" wire:click="selectSort('{{ $key }}')"
                            class="w-full flex items-center justify-between p-3 rounded-2xl transition text-left {{ $sort === $key ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-base-200 text-base-content' }}">
                            <span class="text-sm">{{ $label }}</span>
                            @if ($sort === $key)
                                <x-heroicon-s-check-circle class="h-5 w-5 text-primary shrink-0" />
                            @else
                                <div class="h-4 w-4 rounded-full border border-base-300"></div>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="modal-backdrop bg-black/60 backdrop-blur-xs" wire:click="closeSortModal"></div>
        </div>
    @endif

    {{-- Bottom Sheet / Modal Filter --}}
    @if ($showFilterModal)
        <div class="modal modal-bottom sm:modal-middle modal-open z-50">
            <div class="modal-box rounded-t-[2.5rem] sm:rounded-[2rem] max-w-md w-full bg-base-100 p-5 shadow-2xl border border-base-200 max-h-[85vh] flex flex-col">
                {{-- Modal Header --}}
                <div class="flex items-center justify-between pb-3 border-b border-base-200 shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <x-heroicon-o-adjustments-horizontal class="h-4 w-4" />
                        </div>
                        <h3 class="text-base font-black text-base-content">Filter Produk</h3>
                    </div>
                    <button type="button" wire:click="closeFilterModal" class="btn btn-circle btn-xs btn-ghost">
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                    </button>
                </div>

                {{-- Modal Content (Scrollable) --}}
                <div class="py-4 space-y-5 overflow-y-auto grow pr-1 scrollbar-thin">
                    {{-- 1. Filter Kategori --}}
                    <div class="space-y-2">
                        <label class="text-xs font-black uppercase tracking-wider text-base-content/70">Kategori</label>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" wire:click="$set('temp_category', '')"
                                class="btn btn-xs rounded-full font-bold {{ blank($temp_category) ? 'btn-primary' : 'btn-outline border-base-300 text-base-content/80' }}">
                                Semua Kategori
                            </button>
                            @foreach ($this->categories as $cat)
                                <button type="button" wire:click="$set('temp_category', '{{ $cat->slug }}')"
                                    class="btn btn-xs rounded-full font-bold {{ $temp_category == $cat->slug || $temp_category == $cat->id ? 'btn-primary' : 'btn-outline border-base-300 text-base-content/80' }}">
                                    {{ $cat->name }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- 2. Filter Rentang Harga --}}
                    <div class="space-y-2">
                        <label class="text-xs font-black uppercase tracking-wider text-base-content/70">Rentang Harga (Rp)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <span class="text-[10px] text-base-content/50 block mb-1">Harga Minimum</span>
                                <input type="number" wire:model.defer="temp_price_min" placeholder="0" min="0" step="1000"
                                    class="input input-sm w-full rounded-xl border border-base-300 bg-base-200 text-xs focus:border-primary focus:outline-none" />
                            </div>
                            <div>
                                <span class="text-[10px] text-base-content/50 block mb-1">Harga Maksimum</span>
                                <input type="number" wire:model.defer="temp_price_max" placeholder="Tanpa Batas" min="0" step="1000"
                                    class="input input-sm w-full rounded-xl border border-base-300 bg-base-200 text-xs focus:border-primary focus:outline-none" />
                            </div>
                        </div>
                    </div>

                    {{-- 3. Filter Ketersediaan Stok --}}
                    <div class="space-y-2">
                        <label class="text-xs font-black uppercase tracking-wider text-base-content/70">Ketersediaan Stok</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" wire:click="$set('temp_stock_status', 'all')"
                                class="btn btn-sm rounded-2xl font-bold {{ $temp_stock_status === 'all' ? 'btn-primary' : 'btn-outline border-base-300 text-base-content/80' }}">
                                Semua
                            </button>
                            <button type="button" wire:click="$set('temp_stock_status', 'in_stock')"
                                class="btn btn-sm rounded-2xl font-bold {{ $temp_stock_status === 'in_stock' ? 'btn-primary' : 'btn-outline border-base-300 text-base-content/80' }}">
                                Tersedia
                            </button>
                            <button type="button" wire:click="$set('temp_stock_status', 'out_of_stock')"
                                class="btn btn-sm rounded-2xl font-bold {{ $temp_stock_status === 'out_of_stock' ? 'btn-primary' : 'btn-outline border-base-300 text-base-content/80' }}">
                                Habis
                            </button>
                        </div>
                    </div>

                    {{-- 4. Filter Rating Minimal --}}
                    <div class="space-y-2">
                        <label class="text-xs font-black uppercase tracking-wider text-base-content/70">Rating Minimal</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" wire:click="$set('temp_rating_min', 'all')"
                                class="btn btn-sm rounded-2xl font-bold {{ $temp_rating_min === 'all' ? 'btn-primary' : 'btn-outline border-base-300 text-base-content/80' }}">
                                Semua
                            </button>
                            <button type="button" wire:click="$set('temp_rating_min', '3')"
                                class="btn btn-sm rounded-2xl font-bold gap-1 {{ $temp_rating_min === '3' ? 'btn-primary' : 'btn-outline border-base-300 text-base-content/80' }}">
                                <x-heroicon-s-star class="h-3.5 w-3.5 fill-current text-amber-500" />
                                3+
                            </button>
                            <button type="button" wire:click="$set('temp_rating_min', '4')"
                                class="btn btn-sm rounded-2xl font-bold gap-1 {{ $temp_rating_min === '4' ? 'btn-primary' : 'btn-outline border-base-300 text-base-content/80' }}">
                                <x-heroicon-s-star class="h-3.5 w-3.5 fill-current text-amber-500" />
                                4+
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Modal Actions (Sticky Footer) --}}
                <div class="pt-3 border-t border-base-200 grid grid-cols-2 gap-2 shrink-0">
                    <button type="button" wire:click="resetFilterInputs" class="btn btn-sm btn-ghost rounded-full font-bold">
                        Reset Filter
                    </button>
                    <button type="button" wire:click="applyFilters" class="btn btn-sm btn-primary rounded-full font-bold">
                        Terapkan
                    </button>
                </div>
            </div>
            <div class="modal-backdrop bg-black/60 backdrop-blur-xs" wire:click="closeFilterModal"></div>
        </div>
    @endif
</div>
