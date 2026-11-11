<?php

use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Wishlist Saya')] class extends Component {
    #[Computed]
    public function customer()
    {
        return Auth::guard('customer')->user();
    }

    #[Computed]
    public function wishlistItems()
    {
        if (! $this->customer) {
            return collect();
        }

        return Wishlist::query()
            ->where('customer_id', $this->customer->id)
            ->with(['product.category', 'product.primaryImage', 'product.variants'])
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->latest()
            ->get();
    }

    public function removeFromWishlist(int $productId): void
    {
        if (! $this->customer) {
            $this->redirectRoute('customer.login', navigate: true);
            return;
        }

        Wishlist::query()
            ->where('customer_id', $this->customer->id)
            ->where('product_id', $productId)
            ->delete();

        unset($this->wishlistItems);

        $this->dispatch('notify',
            type: 'info',
            title: 'Dihapus dari Wishlist',
            message: 'Produk berhasil dihapus dari wishlist kamu.'
        );

        $this->dispatch('wishlist-updated');
    }

    public function lowestPrice($product): float
    {
        $variantPrice = $product->variants
            ->where('is_active', true)
            ->min('price');

        return (float) ($variantPrice ?: $product->price);
    }
};
?>

<div class="min-h-screen bg-base-200 pb-24">
    <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur">
        <div class="flex items-center gap-3 p-4">
            <a href="{{ route('store.account') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>

            <div class="min-w-0 flex-1">
                <h1 class="truncate text-lg font-black">Wishlist Saya</h1>
                <p class="text-xs text-base-content/50">
                    {{ $this->wishlistItems->count() }} produk tersimpan
                </p>
            </div>
        </div>
    </header>

    <main class="p-4">
        @if ($this->wishlistItems->isEmpty())
            <div class="rounded-[2rem] bg-base-100 p-8 text-center shadow-sm">
                <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-[2rem] bg-rose-50 text-rose-500">
                    <x-heroicon-o-heart class="h-10 w-10" />
                </div>

                <h2 class="mt-4 text-lg font-black">Wishlist kamu masih kosong</h2>
                <p class="mt-2 text-xs leading-relaxed text-base-content/60">
                    Simpan produk impianmu dengan menekan ikon hati agar mudah dibeli nanti.
                </p>

                <a href="{{ route('store.products') }}" wire:navigate class="btn btn-primary btn-sm mt-6 rounded-full px-6">
                    <x-heroicon-o-shopping-bag class="h-4 w-4" />
                    Mulai Belanja
                </a>
            </div>
        @else
            <div class="grid grid-cols-2 gap-3">
                @foreach ($this->wishlistItems as $item)
                    @php $product = $item->product; @endphp
                    @if ($product)
                        <div class="card overflow-hidden rounded-3xl bg-base-100 shadow-sm transition">
                            <figure class="relative aspect-square w-full bg-base-300">
                                <a href="{{ route('store.product.detail', $product->slug) }}" wire:navigate class="h-full w-full">
                                    @if ($product->primaryImage?->image)
                                        <img src="{{ Storage::disk('public')->url($product->primaryImage->image) }}"
                                            alt="{{ $product->name }}" class="h-full w-full object-cover">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center">
                                            <x-heroicon-o-photo class="h-10 w-10 text-base-content/30" />
                                        </div>
                                    @endif
                                </a>

                                @if ($product->variants->count() > 0)
                                    <div class="badge badge-warning badge-sm absolute left-2 top-2" style="position: absolute; top: 8px; left: 8px;">
                                        {{ $product->variants->count() }} varian
                                    </div>
                                @endif

                                <button type="button" wire:click="removeFromWishlist({{ $product->id }})"
                                    class="btn btn-circle btn-xs absolute top-2 right-2 z-10 bg-base-100/90 shadow transition hover:bg-base-100 active:scale-90"
                                    style="position: absolute; top: 8px; right: 8px;"
                                    title="Hapus dari Wishlist">
                                    <x-heroicon-s-heart class="h-3.5 w-3.5 text-rose-500" />
                                </button>
                            </figure>

                            <div class="p-3 flex flex-col justify-between flex-1">
                                <div>
                                    <p class="truncate text-[11px] text-base-content/50">
                                        {{ $product->category?->name ?? '-' }}
                                    </p>

                                    <a href="{{ route('store.product.detail', $product->slug) }}" wire:navigate>
                                        <h4 class="mt-1 line-clamp-2 min-h-10 text-sm font-bold leading-tight hover:text-primary transition">
                                            {{ $product->name }}
                                        </h4>
                                    </a>

                                    <div class="mt-2">
                                        <p class="text-[11px] text-base-content/50">Mulai dari</p>
                                        <p class="font-black text-primary">
                                            Rp {{ number_format($this->lowestPrice($product), 0, ',', '.') }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <a href="{{ route('store.product.detail', $product->slug) }}" wire:navigate
                                        class="btn btn-primary btn-outline btn-xs w-full rounded-full">
                                        Lihat Detail
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </main>
</div>
