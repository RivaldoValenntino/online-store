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

    $navActive = 'text-primary';
    $navInactive = 'text-base-content/60';

    $homeActive = request()->routeIs('store.home');
    $categoryActive = request()->routeIs('store.categories') || request()->routeIs('store.category.products');
    $cartActive = request()->routeIs('store.cart');
    $accountActive = request()->routeIs('store.account');
@endphp

<nav class="fixed bottom-0 left-1/2 z-50 w-full max-w-md -translate-x-1/2 border-t border-base-300 bg-base-100">
    <div class="grid grid-cols-4">
        <a href="{{ route('store.home') }}" wire:navigate
            class="flex flex-col items-center gap-1 py-2 text-xs {{ $homeActive ? $navActive : $navInactive }}">
            <x-heroicon-o-home class="h-6 w-6" />
            <span>Beranda</span>
        </a>

        <a href="{{ route('store.categories') }}" wire:navigate
            class="flex flex-col items-center gap-1 py-2 text-xs {{ $categoryActive ? $navActive : $navInactive }}">
            <x-heroicon-o-squares-2x2 class="h-6 w-6" />
            <span>Kategori</span>
        </a>

        <a href="{{ route('store.cart') }}" wire:navigate x-data="{ count: {{ $cartCount }} }"
            x-on:cart-updated.window="count = $event.detail.count"
            class="relative flex flex-col items-center gap-1 py-2 text-xs {{ $cartActive ? $navActive : $navInactive }}">
            <div class="relative">
                <x-heroicon-o-shopping-bag class="h-6 w-6" />

                <template x-if="count > 0">
                    <span x-text="count > 99 ? '99+' : count"
                        class="absolute -right-3 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-black text-base-100"></span>
                </template>
            </div>

            <span>Keranjang</span>
        </a>

        <a href="{{ route('store.account') }}" wire:navigate
            class="flex flex-col items-center gap-1 py-2 text-xs {{ $accountActive ? $navActive : $navInactive }}">
            <x-heroicon-o-user class="h-6 w-6" />
            <span>Akun</span>
        </a>
    </div>
</nav>