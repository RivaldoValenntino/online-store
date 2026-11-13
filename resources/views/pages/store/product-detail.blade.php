<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\ProductReviewService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Product Detail')] class extends Component {
    public string $slug;

    public array $selectedOptions = [];
    public ?int $selectedVariantId = null;
    public ?string $activeImage = null;

    public ?string $cartSuccessMessage = null;
    public bool $showLoginRequired = false;
    public bool $isWishlisted = false;
    public int $qty = 1;

    public int $ratingFilter = 0;
    public int $reviewsLimit = 5;

    public function mount(string $slug): void
    {
        $this->slug = $slug;

        $firstVariant = $this->product->variants->first();

        if ($firstVariant) {
            $this->selectedVariantId = $firstVariant->id;

            foreach ($firstVariant->optionValues as $value) {
                $this->selectedOptions[$value->product_option_id] = $value->id;
            }
        }

        $this->activeImage = $this->galleryImages()[0] ?? null;

        $customer = Auth::guard('customer')->user();
        if ($customer) {
            $this->isWishlisted = $this->product->wishlists()->where('customer_id', $customer->id)->exists();
        }
    }

    #[Computed]
    public function product()
    {
        return Product::query()
            ->with(['category', 'images' => fn($query) => $query->orderBy('sort_order'), 'primaryImage', 'options' => fn($query) => $query->orderBy('sort_order'), 'options.values' => fn($query) => $query->orderBy('sort_order'), 'variants' => fn($query) => $query->where('is_active', true)->orderBy('price'), 'variants.optionValues.option'])
            ->where('slug', $this->slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    #[Computed]
    public function selectedVariant()
    {
        if (!$this->selectedVariantId) {
            return null;
        }

        return $this->product->variants->firstWhere('id', $this->selectedVariantId);
    }

    public function hasVariants(): bool
    {
        return $this->product->variants->count() > 0;
    }

    public function selectOption(int $optionId, int $valueId): void
    {
        $this->selectedOptions[$optionId] = $valueId;
        $this->qty = 1;
        $this->resetErrorBag();

        $this->syncSelectedVariant();

        $this->activeImage = $this->galleryImages()[0] ?? null;
    }

    public function syncSelectedVariant(): void
    {
        $selectedValueIds = collect($this->selectedOptions)->filter()->map(fn($id) => (int) $id)->sort()->values()->all();

        if (count($selectedValueIds) !== $this->product->options->count()) {
            $this->selectedVariantId = null;
            return;
        }

        foreach ($this->product->variants as $variant) {
            $variantValueIds = $variant->optionValues->pluck('id')->map(fn($id) => (int) $id)->sort()->values()->all();

            if ($variantValueIds === $selectedValueIds) {
                $this->selectedVariantId = $variant->id;
                return;
            }
        }

        $this->selectedVariantId = null;
    }

    public function isValueAvailable(int $optionId, int $valueId): bool
    {
        $selected = $this->selectedOptions;
        $selected[$optionId] = $valueId;

        return $this->product->variants->contains(function ($variant) use ($selected) {
            if ((int) $variant->available_stock <= 0) {
                return false;
            }

            $variantValueIds = $variant->optionValues->pluck('id')->map(fn($id) => (int) $id)->all();

            foreach ($selected as $selectedValueId) {
                if ($selectedValueId && !in_array((int) $selectedValueId, $variantValueIds, true)) {
                    return false;
                }
            }

            return true;
        });
    }

    public function galleryImages(): array
    {
        $images = collect();

        if ($this->selectedVariant?->image) {
            $images->push($this->selectedVariant->image);
        }

        $this->product->images->sortBy('sort_order')->pluck('image')->filter()->each(fn($image) => $images->push($image));

        return $images->filter()->unique()->values()->all();
    }

    public function setActiveImage(string $image): void
    {
        $this->activeImage = $image;
    }

    #[Computed]
    public function displayImage(): ?string
    {
        return $this->activeImage ?: $this->galleryImages()[0] ?? null;
    }

    #[Computed]
    public function displayPrice(): float
    {
        if ($this->hasVariants()) {
            return (float) ($this->selectedVariant?->price ?? ($this->product->variants->min('price') ?? 0));
        }

        return (float) $this->product->price;
    }

    #[Computed]
    public function displayStock(): int
    {
        if ($this->hasVariants()) {
            return (int) ($this->selectedVariant?->available_stock ?? 0);
        }

        return (int) $this->product->available_stock;
    }

    #[Computed]
    public function displayWeight(): int
    {
        if ($this->hasVariants()) {
            return (int) ($this->selectedVariant?->weight_gram ?? 0);
        }

        return (int) $this->product->weight_gram;
    }

    public function incrementQty(): void
    {
        if ($this->qty < $this->displayStock) {
            $this->qty++;
        }
    }

    public function decrementQty(): void
    {
        if ($this->qty > 1) {
            $this->qty--;
        }
    }

    public function getOrCreateCart(): Cart
    {
        $customer = Auth::guard('customer')->user();

        return Cart::firstOrCreate([
            'customer_id' => $customer->id,
        ]);
    }

    public function cartCount(): int
    {
        $cart = $this->getOrCreateCart();

        return (int) $cart->items()->sum('qty');
    }

    public function addToCart(): void
    {
        $this->cartSuccessMessage = null;
        $this->showLoginRequired = false;

        if (!Auth::guard('customer')->check()) {
            $this->showLoginRequired = true;
            return;
        }

        $this->qty = (int) max(1, $this->qty);

        if (!$this->product->is_active) {
            $this->addError('qty', 'Produk ini sedang dinonaktifkan.');
            return;
        }

        if ($this->hasVariants() && !$this->selectedVariant) {
            $this->addError('selectedVariantId', 'Pilih varian terlebih dahulu.');
            return;
        }

        if ($this->selectedVariant && !$this->selectedVariant->is_active) {
            $this->addError('selectedVariantId', 'Varian ini sedang dinonaktifkan.');
            return;
        }

        if ($this->displayStock <= 0) {
            $this->addError('qty', 'Stok varian ini habis.');
            return;
        }

        if ($this->qty > $this->displayStock) {
            $this->addError('qty', 'Jumlah melebihi stok tersedia.');
            return;
        }

        $cart = $this->getOrCreateCart();

        $variantId = $this->selectedVariant?->id;

        $existingItem = CartItem::query()->where('cart_id', $cart->id)->where('product_id', $this->product->id)->where('product_variant_id', $variantId)->first();

        if ($existingItem) {
            $newQty = $existingItem->qty + $this->qty;

            if ($newQty > $this->displayStock) {
                $this->addError('qty', 'Jumlah di keranjang melebihi stok tersedia.');
                return;
            }

            $existingItem->update([
                'qty' => $newQty,
                'price' => $this->displayPrice,
            ]);
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $this->product->id,
                'product_variant_id' => $variantId,
                'qty' => $this->qty,
                'price' => $this->displayPrice,
            ]);
        }

        $this->cartSuccessMessage = 'Produk berhasil ditambahkan ke keranjang.';

        $this->dispatch('cart-updated', count: $this->cartCount());
    }

    public function toggleWishlist(): void
    {
        $customer = Auth::guard('customer')->user();

        if (!$customer) {
            $this->showLoginRequired = true;
            return;
        }

        $wishlist = $this->product->wishlists()->where('customer_id', $customer->id)->first();

        if ($wishlist) {
            $wishlist->delete();
            $this->isWishlisted = false;
            $this->dispatch('notify', type: 'info', title: 'Dihapus dari Wishlist', message: 'Produk telah dihapus dari wishlist kamu.');
        } else {
            $this->product->wishlists()->create([
                'customer_id' => $customer->id,
            ]);
            $this->isWishlisted = true;
            $this->dispatch('notify', type: 'success', title: 'Berhasil Disimpan', message: 'Produk berhasil disimpan ke wishlist.');
        }

        $this->dispatch('wishlist-updated');
    }

    public function filterRating(int $stars): void
    {
        $this->ratingFilter = $stars;
        $this->reviewsLimit = 5;
    }

    public function loadMoreReviews(): void
    {
        $this->reviewsLimit += 5;
    }

    #[Computed]
    public function reviews()
    {
        try {
            $query = $this->product
                ->reviews()
                ->approved()
                ->with(['customer', 'images', 'reply'])
                ->latest();

            if ($this->ratingFilter > 0) {
                $query->where('rating', $this->ratingFilter);
            }

            return $query->take($this->reviewsLimit)->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    #[Computed]
    public function totalFilteredReviews(): int
    {
        try {
            $query = $this->product->reviews()->approved();

            if ($this->ratingFilter > 0) {
                $query->where('rating', $this->ratingFilter);
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    #[Computed]
    public function ratingBreakdown(): array
    {
        try {
            return app(ProductReviewService::class)->getRatingBreakdown($this->product);
        } catch (\Throwable $e) {
            return [
                'average' => 0.0,
                'total' => 0,
                'distribution' => [
                    5 => ['count' => 0, 'percentage' => 0],
                    4 => ['count' => 0, 'percentage' => 0],
                    3 => ['count' => 0, 'percentage' => 0],
                    2 => ['count' => 0, 'percentage' => 0],
                    1 => ['count' => 0, 'percentage' => 0],
                ],
                'stars' => [
                    5 => ['count' => 0, 'percentage' => 0],
                    4 => ['count' => 0, 'percentage' => 0],
                    3 => ['count' => 0, 'percentage' => 0],
                    2 => ['count' => 0, 'percentage' => 0],
                    1 => ['count' => 0, 'percentage' => 0],
                ],
            ];
        }
    }
};
?>

<div class="min-h-screen bg-base-200 pb-28">
    {{-- Gallery --}}
    <div wire:loading.flex wire:target="addToCart"
        class="fixed inset-0 z-[9999] items-center justify-center bg-base-100/70 backdrop-blur-sm">
        <div class="flex flex-col items-center gap-3 rounded-3xl bg-base-100 p-6 shadow-xl">
            <span class="loading loading-spinner loading-lg text-primary"></span>

            <p class="text-sm font-semibold text-base-content/70">
                Loading....
            </p>
        </div>
    </div>

    <section class="relative bg-base-100">
        <div class="absolute left-4 top-4 z-20">
            <a href="{{ route('store.home') }}" wire:navigate class="btn btn-circle btn-sm bg-base-100/90 shadow">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>
        </div>

        <div class="absolute right-4 top-4 z-20">
            <button type="button" wire:click="toggleWishlist"
                class="btn btn-circle btn-sm bg-base-100/90 shadow transition active:scale-90" aria-label="Wishlist">
                @if ($isWishlisted)
                    <x-heroicon-s-heart class="h-5 w-5 text-rose-500" />
                @else
                    <x-heroicon-o-heart class="h-5 w-5 text-base-content/70" />
                @endif
            </button>
        </div>

        <div class="aspect-square w-full bg-base-300">
            @if ($this->displayImage)
                <img src="{{ Storage::disk('public')->url($this->displayImage) }}" alt="{{ $this->product->name }}"
                    class="h-full w-full object-cover">
            @else
                <div class="flex h-full w-full items-center justify-center">
                    <x-heroicon-o-photo class="h-16 w-16 text-base-content/30" />
                </div>
            @endif
        </div>

        @if (count($this->galleryImages()) > 1)
            <div class="absolute bottom-4 left-0 right-0 z-20">
                <div class="flex gap-2 overflow-x-auto px-4 pb-1">
                    @foreach ($this->galleryImages() as $image)
                        <button type="button" wire:click="setActiveImage('{{ $image }}')"
                            class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl border-2 bg-base-100 shadow
                                {{ $this->displayImage === $image ? 'border-primary' : 'border-base-100/70' }}">
                            <img src="{{ Storage::disk('public')->url($image) }}" alt="{{ $this->product->name }}"
                                class="h-full w-full object-cover">
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    <main class="-mt-6 relative z-10 rounded-t-[2rem] bg-base-100 p-4">
        @if ($cartSuccessMessage)
            <div x-data="{ show: true }" x-init="setTimeout(() => {
                show = false
                $wire.set('cartSuccessMessage', null)
            }, 2500)" x-show="show" x-transition
                class="toast toast-top toast-center z-[9999]">
                <div class="alert alert-success rounded-2xl shadow-lg">
                    <x-heroicon-o-check-circle class="h-5 w-5" />
                    <span class="text-sm font-semibold">
                        {{ $cartSuccessMessage }}
                    </span>
                </div>
            </div>
        @endif

        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-medium text-base-content/50">
                    {{ $this->product->category?->name ?? '-' }}
                </p>

                <h1 class="mt-1 text-2xl font-black leading-tight">
                    {{ $this->product->name }}
                </h1>

                <div class="mt-2 flex items-center gap-2">
                    @if (($this->product->reviews_count ?? 0) > 0)
                        <a href="#ulasan"
                            class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-bold text-amber-500 hover:bg-amber-500/20 transition">
                            <x-heroicon-s-star class="h-3.5 w-3.5 fill-current" />
                            <span>{{ number_format($this->product->rating_avg ?? 0, 1) }}</span>
                            <span class="text-base-content/50 font-normal">({{ $this->product->reviews_count }}
                                ulasan)</span>
                        </a>
                    @else
                        <span class="text-xs text-base-content/40">Belum ada ulasan</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-4 flex items-end justify-between">
            <div>
                <p class="text-xs text-base-content/50">
                    {{ $this->hasVariants() && !$this->selectedVariant ? 'Mulai dari' : 'Harga' }}
                </p>

                <p class="text-2xl font-black text-primary">
                    Rp {{ number_format($this->displayPrice, 0, ',', '.') }}
                </p>
            </div>

            <div class="text-right">
                <p class="text-xs text-base-content/50">Stok</p>
                <p class="font-bold">
                    {{ $this->displayStock }}
                </p>
            </div>
        </div>

        <div class="mt-2 flex items-center gap-2 text-sm text-base-content/60">
            <x-heroicon-o-scale class="h-4 w-4" />
            <span>Berat {{ number_format($this->displayWeight, 0, ',', '.') }} gram</span>
        </div>

        {{-- Variant options --}}
        @if ($this->hasVariants())
            <section class="mt-6 rounded-[2rem] bg-base-100">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-bold">Pilih Varian</h2>

                    <span class="text-xs text-base-content/50">
                        {{ $this->product->variants->count() }} kombinasi
                    </span>
                </div>

                <div class="space-y-5">
                    @foreach ($this->product->options as $option)
                        <div>
                            <p class="mb-2 text-sm font-bold">
                                {{ $option->name }}
                            </p>

                            <div class="flex flex-wrap gap-2">
                                @foreach ($option->values as $value)
                                    @php
                                        $active = (int) ($selectedOptions[$option->id] ?? 0) === $value->id;
                                        $available = $this->isValueAvailable($option->id, $value->id);
                                    @endphp

                                    <button type="button"
                                        wire:click="selectOption({{ $option->id }}, {{ $value->id }})"
                                        class="btn btn-sm rounded-xl {{ $active ? 'btn-primary' : 'btn-outline' }}"
                                        @disabled(!$available)>
                                        {{ $value->value }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 rounded-2xl bg-base-200 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm text-base-content/60">Varian dipilih</span>

                        <span class="text-right text-sm font-bold">
                            {{ $this->selectedVariant?->name ?? 'Belum tersedia' }}
                        </span>
                    </div>

                    <div class="mt-2 flex items-center justify-between gap-3">
                        <span class="text-sm text-base-content/60">Stok</span>

                        <span class="text-right text-sm font-bold">
                            {{ $this->displayStock }}
                        </span>
                    </div>
                </div>

                @error('selectedVariantId')
                    <p class="mt-2 text-sm text-error">{{ $message }}</p>
                @enderror
            </section>
        @endif

        {{-- Qty --}}
        <section class="mt-6">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-bold">Jumlah</h2>

                @if ($this->displayStock <= 0)
                    <span class="badge badge-error">Stok habis</span>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <button type="button" wire:click="decrementQty" class="btn btn-circle btn-outline btn-sm"
                    @disabled($qty <= 1)>
                    <x-heroicon-o-minus class="h-4 w-4" />
                </button>

                <input type="number" min="1" max="{{ $this->displayStock }}" wire:model.live="qty"
                    class="input input-bordered h-10 w-20 text-center font-bold">

                <button type="button" wire:click="incrementQty" class="btn btn-circle btn-outline btn-sm"
                    @disabled($qty >= $this->displayStock)>
                    <x-heroicon-o-plus class="h-4 w-4" />
                </button>
            </div>

            @error('qty')
                <p class="mt-2 text-sm text-error">{{ $message }}</p>
            @enderror
        </section>

        {{-- Description --}}
        <section class="mt-6">
            <h2 class="mb-3 font-bold">Deskripsi Produk</h2>

            <div class="prose prose-sm max-w-none text-base-content/80">
                {!! $this->product->description ?: '<p>Belum ada deskripsi produk.</p>' !!}
            </div>
        </section>

        {{-- Product Reviews Section --}}
        <section id="ulasan" class="mt-8 border-t border-base-200 pt-6" x-data="{ previewPhoto: null }">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-black text-base-content">Ulasan Pembeli</h2>
                    <p class="text-xs text-base-content/60">Ulasan nyata dari pelanggan yang telah membeli produk ini
                    </p>
                </div>
            </div>

            {{-- Summary Card with Rating & Distribution --}}
            <div class="rounded-3xl bg-base-200/60 p-4 sm:p-5 border border-base-200">
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center">
                    {{-- Big Average Score --}}
                    <div
                        class="sm:col-span-4 flex flex-col items-center justify-center text-center sm:border-r sm:border-base-300 sm:pr-4 py-2">
                        <span class="text-4xl font-black text-base-content tracking-tight">
                            {{ number_format($this->ratingBreakdown['average'], 1) }}
                        </span>
                        <div class="flex items-center gap-0.5 text-amber-400 mt-1">
                            @for ($s = 1; $s <= 5; $s++)
                                @if ($s <= round($this->ratingBreakdown['average']))
                                    <x-heroicon-s-star class="h-4 w-4 fill-current" />
                                @else
                                    <x-heroicon-s-star class="h-4 w-4 text-base-content/20" />
                                @endif
                            @endfor
                        </div>
                        <span class="text-xs text-base-content/60 mt-1 font-medium">
                            {{ $this->ratingBreakdown['total'] }} ulasan
                        </span>
                    </div>

                    {{-- Distribution Bars --}}
                    <div class="sm:col-span-8 space-y-1.5">
                        @foreach ([5, 4, 3, 2, 1] as $star)
                            @php
                                $starData = $this->ratingBreakdown['stars'][$star] ?? ['count' => 0, 'percentage' => 0];
                            @endphp
                            <button type="button"
                                wire:click="filterRating({{ $this->ratingFilter === $star ? 0 : $star }})"
                                class="w-full flex items-center gap-2 group text-left transition hover:opacity-80">
                                <span class="text-xs font-bold text-base-content/70 w-8 flex items-center gap-0.5">
                                    {{ $star }} <x-heroicon-s-star
                                        class="h-3 w-3 text-amber-400 fill-current inline" />
                                </span>
                                <div class="flex-1 h-2 rounded-full bg-base-300/80 overflow-hidden">
                                    <div class="h-full bg-amber-400 rounded-full transition-all duration-300"
                                        style="width: {{ $starData['percentage'] }}%"></div>
                                </div>
                                <span class="text-[11px] text-base-content/50 w-7 text-right font-medium">
                                    {{ $starData['count'] }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Filter Pills --}}
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" wire:click="filterRating(0)"
                    class="btn btn-xs rounded-full {{ $this->ratingFilter === 0 ? 'btn-primary' : 'btn-outline border-base-300 text-base-content/70' }}">
                    Semua ({{ $this->product->reviews_count }})
                </button>
                @foreach ([5, 4, 3, 2, 1] as $star)
                    @php
                        $count = $this->ratingBreakdown['stars'][$star]['count'] ?? 0;
                    @endphp
                    <button type="button" wire:click="filterRating({{ $star }})"
                        class="btn btn-xs rounded-full gap-1 {{ $this->ratingFilter === $star ? 'btn-primary' : 'btn-outline border-base-300 text-base-content/70' }}">
                        <span>{{ $star }}</span>
                        <x-heroicon-s-star
                            class="h-3 w-3 fill-current {{ $this->ratingFilter === $star ? 'text-white' : 'text-amber-400' }}" />
                        <span>({{ $count }})</span>
                    </button>
                @endforeach
            </div>

            {{-- Reviews List --}}
            <div class="mt-4 space-y-3">
                @forelse ($this->reviews as $review)
                    <div class="rounded-3xl bg-base-100 p-4 border border-base-200/90 shadow-2xs space-y-3">
                        {{-- Reviewer Info & Badge --}}
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <div
                                    class="h-9 w-9 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center text-xs uppercase">
                                    {{ substr($review->customer?->name ?? 'P', 0, 1) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-xs font-bold text-base-content">
                                            {{ $review->customer_masked_name }}
                                        </h4>
                                        @if ($review->is_verified_purchase)
                                            <span
                                                class="badge badge-success badge-xs gap-1 text-[10px] font-semibold py-1 px-1.5">
                                                <x-heroicon-s-check-badge class="h-3 w-3" /> Terverifikasi
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-[10px] text-base-content/50">
                                        {{ $review->created_at->format('d M Y') }}
                                    </span>
                                </div>
                            </div>

                            {{-- Star Rating --}}
                            <div class="flex items-center text-amber-400">
                                @for ($i = 1; $i <= 5; $i++)
                                    @if ($i <= $review->rating)
                                        <x-heroicon-s-star class="h-3.5 w-3.5 fill-current" />
                                    @else
                                        <x-heroicon-s-star class="h-3.5 w-3.5 text-base-content/20" />
                                    @endif
                                @endfor
                            </div>
                        </div>

                        {{-- Review Text --}}
                        <p class="text-xs sm:text-sm text-base-content/80 leading-relaxed">
                            {{ $review->review }}
                        </p>

                        {{-- Review Photos Gallery --}}
                        @if ($review->images->isNotEmpty())
                            <div class="flex flex-wrap gap-2 pt-1">
                                @foreach ($review->images as $img)
                                    <button type="button" @click="previewPhoto = '{{ $img->url }}'"
                                        class="h-16 w-16 rounded-2xl overflow-hidden border border-base-300 hover:border-primary transition group relative">
                                        <img src="{{ $img->url }}" alt="Foto Ulasan"
                                            class="h-full w-full object-cover group-hover:scale-105 transition" />
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        {{-- Official Store Reply Card --}}
                        @if ($review->reply)
                            <div class="rounded-2xl bg-base-200/80 border border-base-300/80 p-3.5 space-y-1.5 mt-2">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5 text-xs font-bold text-primary">
                                        <x-heroicon-s-building-storefront class="h-4 w-4 shrink-0" />
                                        <span>Respon Resmi dari {{ $review->reply->store_display_name }}</span>
                                    </div>
                                    <span class="text-[10px] text-base-content/50">
                                        {{ $review->reply->created_at->diffForHumans() }}
                                    </span>
                                </div>
                                <p class="text-xs text-base-content/80 leading-relaxed pl-5">
                                    {{ $review->reply->reply }}
                                </p>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-3xl bg-base-100 p-8 text-center border border-base-200">
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-base-200 text-base-content/40">
                            <x-heroicon-o-chat-bubble-bottom-center-text class="h-6 w-6" />
                        </div>
                        <h4 class="mt-3 text-sm font-bold text-base-content">
                            {{ $this->ratingFilter > 0 ? 'Belum ada ulasan dengan ' . $this->ratingFilter . ' bintang' : 'Belum ada ulasan untuk produk ini' }}
                        </h4>
                        <p class="mt-1 text-xs text-base-content/50">
                            Jadilah yang pertama memberikan ulasan setelah menyelesaikan pesanan produk ini!
                        </p>
                    </div>
                @endforelse
            </div>

            {{-- Load More Button --}}
            @if ($this->totalFilteredReviews > count($this->reviews))
                <div class="mt-4 text-center">
                    <button type="button" wire:click="loadMoreReviews" wire:loading.attr="disabled"
                        class="btn btn-outline btn-sm rounded-full px-6 text-xs font-bold">
                        <span wire:loading.remove wire:target="loadMoreReviews">Muat Lebih Banyak Ulasan</span>
                        <span wire:loading wire:target="loadMoreReviews" class="flex items-center gap-2">
                            <span class="loading loading-spinner loading-xs"></span> Memuat...
                        </span>
                    </button>
                </div>
            @endif

            {{-- Lightbox Photo Preview Modal --}}
            <div x-show="previewPhoto" x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs"
                @click.self="previewPhoto = null" @keydown.escape.window="previewPhoto = null">
                <div class="relative max-w-lg w-full max-h-[85vh] flex flex-col items-center">
                    <button type="button" @click="previewPhoto = null"
                        class="btn btn-circle btn-sm bg-base-100/90 absolute -top-3 -right-3 z-10 shadow">
                        ✕
                    </button>
                    <img :src="previewPhoto" alt="Preview Foto Ulasan"
                        class="rounded-3xl object-contain max-h-[80vh] shadow-2xl border border-white/10" />
                </div>
            </div>
        </section>
    </main>

    {{-- Sticky bottom action --}}
    <div
        class="fixed bottom-16 left-1/2 z-50 w-full max-w-md -translate-x-1/2 border-t border-base-300 bg-base-100 p-4">
        <div class="flex items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs text-base-content/50">Total</p>
                <p class="truncate text-lg font-black text-primary">
                    Rp {{ number_format($this->displayPrice * $qty, 0, ',', '.') }}
                </p>
            </div>

            <button wire:click="addToCart" class="btn btn-primary rounded-full px-6" @disabled($this->displayStock <= 0 || ($this->hasVariants() && !$this->selectedVariant))>
                <x-heroicon-o-shopping-bag class="h-5 w-5" />
                Keranjang
            </button>
        </div>
    </div>

    @if ($showLoginRequired)
        <div class="modal modal-open">
            <div class="modal-box rounded-[2rem]">
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <x-heroicon-o-user-circle class="h-7 w-7" />
                    </div>

                    <div>
                        <h3 class="text-lg font-black">Login dulu ya</h3>

                        <p class="mt-1 text-sm leading-relaxed text-base-content/60">
                            Untuk menambahkan produk ke keranjang atau wishlist, kamu perlu login atau daftar akun
                            terlebih dahulu.
                        </p>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button type="button" wire:click="$set('showLoginRequired', false)"
                        class="btn btn-ghost rounded-full">
                        Nanti
                    </button>

                    <a href="{{ route('customer.login') }}" wire:navigate class="btn btn-primary rounded-full">
                        Login
                    </a>
                </div>

                <div class="mt-3 text-center">
                    <a href="{{ route('customer.register') }}" wire:navigate
                        class="text-sm font-semibold text-primary">
                        Belum punya akun? Daftar
                    </a>
                </div>
            </div>

            <div class="modal-backdrop" wire:click="$set('showLoginRequired', false)"></div>
        </div>
    @endif
</div>
