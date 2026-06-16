<?php

use App\Models\Category;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kategori')] class extends Component {
    public string $search = '';

    #[Computed]
    public function categories()
    {
        return Category::query()
            ->withCount([
                'products' => fn ($query) => $query->where('is_active', true),
            ])
            ->where('is_active', true)
            ->when(trim($this->search) !== '', function ($query) {
                $query->where('name', 'like', '%' . trim($this->search) . '%');
            })
            ->orderBy('name')
            ->get();
    }

    public function clearSearch(): void
    {
        $this->search = '';
    }
};
?>

<div class="min-h-screen bg-base-200 pb-24">
    <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur">
        <div class="space-y-4 p-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('store.home') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                    <x-heroicon-o-chevron-left class="h-5 w-5" />
                </a>

                <div>
                    <h1 class="text-lg font-black">Kategori</h1>
                    <p class="text-xs text-base-content/50">
                        Temukan produk berdasarkan kategori
                    </p>
                </div>
            </div>

            <div class="flex h-11 items-center gap-2 rounded-full border border-base-300 bg-base-200 px-4 transition
                       focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                <x-heroicon-o-magnifying-glass class="h-5 w-5 shrink-0 text-base-content/40" />

                <input type="text" wire:model.live.debounce.500ms="search" class="grow border-0 bg-transparent text-sm outline-none placeholder:text-base-content/40
                           focus:border-0 focus:outline-none focus:ring-0" placeholder="Cari kategori...">

                @if ($search)
                <button type="button" wire:click="clearSearch" class="btn btn-ghost btn-xs btn-circle">
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                </button>
                @endif
            </div>
        </div>
    </header>

    <main class="p-4">
        @if ($this->categories->isEmpty())
        <section class="rounded-[2rem] bg-base-100 p-6 text-center shadow-sm">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-[2rem] bg-primary/10 text-primary">
                <x-heroicon-o-squares-2x2 class="h-12 w-12" />
            </div>

            <h2 class="mt-4 text-xl font-black">Kategori tidak ditemukan</h2>

            <p class="mt-2 text-sm text-base-content/60">
                Coba gunakan kata kunci lain.
            </p>
        </section>
        @else
        <section class="grid grid-cols-2 gap-3">
            @foreach ($this->categories as $category)
            <a href="{{ route('store.category.products', $category->slug) }}" wire:navigate
                class="rounded-[2rem] bg-base-100 p-4 shadow-sm transition active:scale-[0.98]">
                <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-3xl bg-base-200">
                    @if ($category->image)
                    <img src="{{ Storage::disk('public')->url($category->image) }}" alt="{{ $category->name }}"
                        class="h-full w-full object-cover">
                    @else
                    <x-heroicon-o-squares-2x2 class="h-9 w-9 text-base-content/40" />
                    @endif
                </div>

                <h2 class="mt-3 line-clamp-1 font-black">
                    {{ $category->name }}
                </h2>

                <p class="mt-1 text-xs text-base-content/50">
                    {{ $category->products_count }} produk
                </p>
            </a>
            @endforeach
        </section>
        @endif
    </main>
</div>