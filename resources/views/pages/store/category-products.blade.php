<?php

use App\Models\Category;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Produk Kategori')] class extends Component {
    public string $slug;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    #[Computed]
    public function category()
    {
        return Category::query()
            ->where('slug', $this->slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    #[Computed]
    public function products()
    {
        return $this->category
            ->products()
            ->with(['category', 'primaryImage', 'variants'])
            ->where('is_active', true)
            ->latest()
            ->get();
    }

    public function lowestPrice($product): float
    {
        $variantPrice = $product->variants
            ->where('is_active', true)
            ->min('price');

        return (float) ($variantPrice ?: $product->price);
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
        <div class="flex items-center gap-3 p-4">
            <a href="{{ route('store.categories') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>

            <div class="min-w-0">
                <h1 class="truncate text-lg font-black">
                    {{ $this->category->name }}
                </h1>

                <p class="text-xs text-base-content/50">
                    {{ $this->products->count() }} produk tersedia
                </p>
            </div>
        </div>
    </header>

    <main class="p-4">
        @if ($this->products->isEmpty())
        <section class="rounded-[2rem] bg-base-100 p-6 text-center shadow-sm">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-[2rem] bg-primary/10 text-primary">
                <x-heroicon-o-shopping-bag class="h-12 w-12" />
            </div>

            <h2 class="mt-4 text-xl font-black">Belum ada produk</h2>

            <p class="mt-2 text-sm text-base-content/60">
                Produk untuk kategori ini belum tersedia.
            </p>
        </section>
        @else
        <section class="grid grid-cols-2 gap-3">
            @foreach ($this->products as $product)
            <a href="{{ route('store.product.detail', $product->slug) }}" wire:navigate
                class="card overflow-hidden rounded-3xl bg-base-100 shadow-sm transition active:scale-[0.98]">
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
                    <div class="badge badge-warning badge-sm absolute left-2 top-2" style="position: absolute; top: 8px; left: 8px;">
                        {{ $product->variants->count() }} varian
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

                <div class="p-3">
                    <h4 class="line-clamp-2 min-h-10 text-sm font-bold leading-tight">
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
            @endforeach
        </section>
        @endif
    </main>
</div>