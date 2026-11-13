@php
    use App\Models\Cart;
    use Illuminate\Support\Facades\Auth;

    $customer = Auth::guard('customer')->user();

    $cart = Cart::query()
        ->when(
            $customer,
            fn ($query) => $query->where('customer_id', $customer->id),
            fn ($query) => $query->where('session_id', session()->getId())
        )
        ->withSum('items as total_qty', 'qty')
        ->first();

    $cartCount = (int) ($cart?->total_qty ?? 0);

    $homeActive = request()->routeIs('store.home');
    $categoryActive = request()->routeIs('store.categories') || request()->routeIs('store.category.products');
    $cartActive = request()->routeIs('store.cart');
    $accountActive = request()->routeIs('store.account');
@endphp

<nav class="fixed bottom-0 left-1/2 z-50 w-full max-w-md -translate-x-1/2 border-t border-base-300 bg-base-100/95 backdrop-blur-md pb-[env(safe-area-inset-bottom,0px)] shadow-lg">
    <div class="grid grid-cols-4 py-1">
        <a href="{{ route('store.home') }}" wire:navigate
            class="group relative flex flex-col items-center gap-1 py-1.5 text-xs transition {{ $homeActive ? 'text-primary font-bold' : 'text-base-content/60 hover:text-base-content' }}">
            @if ($homeActive)
                <span class="absolute top-0 h-0.5 w-8 rounded-full bg-primary"></span>
            @endif
            <x-heroicon-s-home class="h-5 w-5 {{ $homeActive ? 'block' : 'hidden' }}" />
            <x-heroicon-o-home class="h-5 w-5 {{ $homeActive ? 'hidden' : 'block' }}" />
            <span class="text-[11px] leading-none">Beranda</span>
        </a>

        <a href="{{ route('store.categories') }}" wire:navigate
            class="group relative flex flex-col items-center gap-1 py-1.5 text-xs transition {{ $categoryActive ? 'text-primary font-bold' : 'text-base-content/60 hover:text-base-content' }}">
            @if ($categoryActive)
                <span class="absolute top-0 h-0.5 w-8 rounded-full bg-primary"></span>
            @endif
            <x-heroicon-s-squares-2x2 class="h-5 w-5 {{ $categoryActive ? 'block' : 'hidden' }}" />
            <x-heroicon-o-squares-2x2 class="h-5 w-5 {{ $categoryActive ? 'hidden' : 'block' }}" />
            <span class="text-[11px] leading-none">Kategori</span>
        </a>

        <a href="{{ route('store.cart') }}" wire:navigate x-data="{ count: {{ $cartCount }} }"
            x-on:cart-updated.window="count = $event.detail.count"
            class="group relative flex flex-col items-center gap-1 py-1.5 text-xs transition {{ $cartActive ? 'text-primary font-bold' : 'text-base-content/60 hover:text-base-content' }}">
            @if ($cartActive)
                <span class="absolute top-0 h-0.5 w-8 rounded-full bg-primary"></span>
            @endif
            <div class="relative flex h-6 w-6 items-center justify-center">
                <x-heroicon-s-shopping-bag class="h-5 w-5 {{ $cartActive ? 'block' : 'hidden' }}" />
                <x-heroicon-o-shopping-bag class="h-5 w-5 {{ $cartActive ? 'hidden' : 'block' }}" />

                <span x-show="count > 0"
                    x-cloak
                    x-text="count > 99 ? '99+' : count"
                    style="position: absolute; top: -4px; right: -6px; z-index: 10; pointer-events: none; {{ $cartCount <= 0 ? 'display: none;' : '' }}"
                    class="flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[9px] font-black text-white ring-2 ring-base-100 shadow-xs leading-none">
                    {{ $cartCount > 99 ? '99+' : $cartCount }}
                </span>
            </div>
            <span class="text-[11px] leading-none">Keranjang</span>
        </a>

        <a href="{{ route('store.account') }}" wire:navigate
            class="group relative flex flex-col items-center gap-1 py-1.5 text-xs transition {{ $accountActive ? 'text-primary font-bold' : 'text-base-content/60 hover:text-base-content' }}">
            @if ($accountActive)
                <span class="absolute top-0 h-0.5 w-8 rounded-full bg-primary"></span>
            @endif
            <x-heroicon-s-user class="h-5 w-5 {{ $accountActive ? 'block' : 'hidden' }}" />
            <x-heroicon-o-user class="h-5 w-5 {{ $accountActive ? 'hidden' : 'block' }}" />
            <span class="text-[11px] leading-none">Akun</span>
        </a>
    </div>
</nav>
