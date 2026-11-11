<?php

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pesanan Saya')] class extends Component {
    use WithPagination;

    public string $status = 'all';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function orders()
    {
        return Order::query()
            ->with('items')
            ->where('customer_id', Auth::guard('customer')->id())
            ->when($this->status !== 'all', function ($query) {
                switch ($this->status) {
                    case 'pending_payment':
                        $query->where('status', 'pending_payment')->where('payment_status', 'pending');
                        break;

                    case 'confirmed':
                        $query
                            ->where('status', 'confirmed')
                            ->where('payment_status', 'paid')
                            ->whereIn('shipping_status', ['pending', 'waiting_shipment']);
                        break;

                    case 'processing':
                        $query->where('status', 'processing')->where('shipping_status', 'processed');
                        break;

                    case 'shipped':
                        $query->where('status', 'processing')->where('shipping_status', 'shipped');
                        break;

                    case 'completed':
                        $query->where('status', 'completed')->where('shipping_status', 'delivered');
                        break;

                    case 'cancelled':
                        $query->where(function ($q) {
                            $q->whereIn('status', ['cancelled', 'expired'])
                                ->orWhereIn('payment_status', ['expired', 'cancelled', 'denied', 'failed'])
                                ->orWhereIn('shipping_status', ['cancelled', 'returned', 'failed']);
                        });
                        break;
                }
            })
            ->latest()
            ->paginate(10);
    }

    public function statusBadgeClass(Order $order): string
    {
        return match ($order->status) {
            'pending_payment' => 'badge-warning',

            'confirmed' => 'badge-info',

            'processing' => $order->shipping_status === 'shipped' ? 'badge-primary' : 'badge-accent',

            'completed' => 'badge-success',

            'expired', 'cancelled' => 'badge-error',

            default => 'badge-neutral',
        };
    }

    public function statusText(Order $order): string
    {
        return match ($order->status) {
            'pending_payment' => 'Menunggu Pembayaran',

            'confirmed' => 'Menunggu Pengiriman',

            'processing' => $order->shipping_status === 'shipped' ? 'Sedang Dikirim' : 'Sedang Diproses',

            'completed' => 'Pesanan Selesai',

            'expired' => 'Pembayaran Kedaluwarsa',

            'cancelled' => 'Pesanan Dibatalkan',

            default => ucfirst($order->status),
        };
    }
};
?>

<div class="min-h-screen bg-base-200 pb-24">
    <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur">
        <div class="flex items-center gap-3 p-4">
            <a href="{{ route('store.account') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>

            <div>
                <h1 class="text-lg font-black">Pesanan Saya</h1>
                <p class="text-xs text-base-content/50">
                    Riwayat dan status pesanan kamu
                </p>
            </div>
        </div>
    </header>

    <main class="space-y-4 p-4">
        {{-- Filter Status --}}
        <section class="rounded-[2rem] bg-base-100 p-3 shadow-sm">

            <div x-data="statusScroller()" x-init="init()" class="relative">

                {{-- Left --}}
                <button x-show="canPrev" x-transition.opacity @click="prev"
                    class="btn btn-circle btn-sm absolute left-0 top-1/2 z-20 -translate-y-1/2 bg-base-100 shadow-lg">

                    <x-heroicon-o-chevron-left class="w-4 h-4" />

                </button>

                {{-- Right --}}
                <button x-show="canNext" x-transition.opacity @click="next"
                    class="btn btn-circle btn-sm absolute right-0 top-1/2 z-20 -translate-y-1/2 bg-base-100 shadow-lg">

                    <x-heroicon-o-chevron-right class="w-4 h-4" />

                </button>

                {{-- Fade --}}
                <div x-show="canPrev"
                    class="pointer-events-none absolute left-0 top-0 bottom-0 w-10 rounded-l-[2rem] bg-gradient-to-r from-base-100 to-transparent z-10">
                </div>

                <div x-show="canNext"
                    class="pointer-events-none absolute right-0 top-0 bottom-0 w-10 rounded-r-[2rem] bg-gradient-to-l from-base-100 to-transparent z-10">
                </div>

                {{-- Scroll --}}
                <div x-ref="container" @scroll="update"
                    :class="{
                        'pl-10': canPrev,
                        'pr-10': canNext,
                        'pl-0': !canPrev,
                        'pr-0': !canNext,
                    }"
                    class="flex gap-2 overflow-x-auto scroll-smooth whitespace-nowrap no-scrollbar px-8">

                    <button wire:click="$set('status','all')"
                        class="btn btn-sm rounded-full shrink-0 {{ $status === 'all' ? 'btn-primary' : 'btn-ghost' }}">
                        Semua
                    </button>

                    <button wire:click="$set('status','pending_payment')"
                        class="btn btn-sm rounded-full shrink-0 {{ $status === 'pending_payment' ? 'btn-primary' : 'btn-ghost' }}">
                        Belum Bayar
                    </button>

                    <button wire:click="$set('status','confirmed')"
                        class="btn btn-sm rounded-full shrink-0 {{ $status === 'confirmed' ? 'btn-primary' : 'btn-ghost' }}">
                        Dikonfirmasi
                    </button>

                    <button wire:click="$set('status','processing')"
                        class="btn btn-sm rounded-full shrink-0 {{ $status === 'processing' ? 'btn-primary' : 'btn-ghost' }}">
                        Diproses
                    </button>

                    <button wire:click="$set('status','shipped')"
                        class="btn btn-sm rounded-full shrink-0 {{ $status === 'shipped' ? 'btn-primary' : 'btn-ghost' }}">
                        Dikirim
                    </button>

                    <button wire:click="$set('status','completed')"
                        class="btn btn-sm rounded-full shrink-0 {{ $status === 'completed' ? 'btn-primary' : 'btn-ghost' }}">
                        Selesai
                    </button>

                    <button wire:click="$set('status','cancelled')"
                        class="btn btn-sm rounded-full shrink-0 {{ $status === 'cancelled' ? 'btn-primary' : 'btn-ghost' }}">
                        Dibatalkan
                    </button>

                </div>

            </div>

        </section>
        {{-- Orders --}}
        <div wire:loading.remove wire:target="status">

            <section class="space-y-3">
                @forelse ($this->orders as $order)
                    <a href="{{ route('store.orders.show', $order) }}" wire:navigate
                        class="block rounded-[2rem] bg-base-100 p-4 shadow-sm transition active:scale-[0.99]">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs text-base-content/50">
                                    {{ $order->created_at->locale('id')->translatedFormat('d F Y, H:i') }}
                                </p>

                                <h2 class="mt-1 truncate font-black">
                                    {{ $order->order_number }}
                                </h2>
                            </div>

                            <span class="badge {{ $this->statusBadgeClass($order) }} badge-sm shrink-0">
                                {{ $this->statusText($order) }}
                            </span>
                        </div>

                        <div class="mt-3 space-y-2">
                            @foreach ($order->items->take(2) as $item)
                                <div class="flex justify-between gap-3 text-sm">
                                    <div class="min-w-0">
                                        <p class="truncate font-bold">
                                            {{ $item->product_name }}
                                        </p>

                                        @if ($item->variant_name)
                                            <p class="truncate text-xs text-base-content/50">
                                                {{ $item->variant_name }}
                                            </p>
                                        @endif
                                    </div>

                                    <p class="shrink-0 text-xs text-base-content/50">
                                        x{{ $item->qty }}
                                    </p>
                                </div>
                            @endforeach

                            @if ($order->items->count() > 2)
                                <p class="text-xs text-base-content/50">
                                    +{{ $order->items->count() - 2 }} produk lainnya
                                </p>
                            @endif
                        </div>

                        <div class="mt-4 border-t border-base-200 pt-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-base-content/50">Total Belanja</p>
                                    <p class="text-lg font-black text-primary">
                                        Rp {{ number_format($order->grand_total, 0, ',', '.') }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-2">
                                    @if ($order->payment_status !== 'paid' && $order->payment_status !== 'cancelled' && $order->payment_status !== 'expired')
                                        <span class="btn btn-primary btn-sm rounded-full">
                                            Bayar
                                        </span>
                                    @else
                                        <span class="btn btn-soft btn-sm rounded-full">
                                            Detail
                                        </span>
                                    @endif

                                    <x-heroicon-o-chevron-right class="h-5 w-5 text-base-content/30" />
                                </div>
                            </div>
                        </div>
                    </a>
                @empty

                    @if ($status === 'all')
                        {{-- Tidak pernah belanja --}}
                        <div class="rounded-[2rem] bg-base-100 p-8 text-center shadow-sm">

                            <div
                                class="mx-auto flex h-20 w-20 items-center justify-center rounded-[2rem] bg-primary/10 text-primary">
                                <x-heroicon-o-shopping-bag class="h-12 w-12" />
                            </div>

                            <h2 class="mt-4 text-xl font-black">
                                Belum ada pesanan
                            </h2>

                            <p class="mt-2 text-sm text-base-content/60">
                                Pesanan yang kamu buat akan muncul di sini.
                            </p>

                            <a href="{{ route('store.products') }}" wire:navigate
                                class="btn btn-primary mt-5 rounded-full">
                                Mulai Belanja
                            </a>

                        </div>
                    @else
                        {{-- Filter kosong --}}
                        <div class="rounded-[2rem] bg-base-100 p-8 text-center shadow-sm">

                            <div
                                class="mx-auto flex h-20 w-20 items-center justify-center rounded-[2rem] bg-base-200 text-base-content/40">
                                <x-heroicon-o-funnel class="h-10 w-10" />
                            </div>

                            <h2 class="mt-4 text-lg font-black">
                                Tidak ada pesanan
                            </h2>

                            <p class="mt-2 text-sm text-base-content/60">
                                Tidak ada pesanan dengan status ini.
                            </p>

                        </div>
                    @endif
                @endforelse
            </section>

            @if ($this->orders->hasPages())
                <div class="rounded-[2rem] bg-base-100 p-4 shadow-sm mt-4">
                    {{ $this->orders->links() }}
                </div>
            @endif

        </div>

        {{-- Loading --}}
        <div wire:loading.flex wire:target="status" class="flex flex-col gap-3">

            @for ($i = 0; $i < 4; $i++)

                <div class="animate-pulse rounded-[2rem] bg-base-100 p-4 shadow-sm">

                    {{-- Header --}}
                    <div class="flex justify-between">

                        <div class="space-y-2">

                            <div class="h-3 w-28 rounded-full bg-base-300"></div>

                            <div class="h-5 w-40 rounded-full bg-base-300"></div>

                        </div>

                        <div class="h-6 w-20 rounded-full bg-base-300"></div>

                    </div>

                    {{-- Products --}}
                    <div class="mt-5 space-y-4">

                        @for ($x = 0; $x < 2; $x++)
                            <div class="flex justify-between">

                                <div class="flex-1 space-y-2">

                                    <div class="h-4 w-5/6 rounded-full bg-base-300"></div>

                                    <div class="h-3 w-2/5 rounded-full bg-base-300"></div>

                                </div>

                                <div class="ml-4 h-3 w-8 rounded-full bg-base-300"></div>

                            </div>
                        @endfor

                    </div>

                    {{-- Footer --}}
                    <div class="mt-5 border-t border-base-200 pt-4">

                        <div class="flex justify-between items-center">

                            <div class="space-y-2">

                                <div class="h-3 w-24 rounded-full bg-base-300"></div>

                                <div class="h-6 w-36 rounded-full bg-base-300"></div>

                            </div>

                            <div class="flex items-center gap-3">

                                <div class="h-9 w-24 rounded-full bg-base-300"></div>

                                <div class="h-5 w-5 rounded-full bg-base-300"></div>

                            </div>

                        </div>

                    </div>

                </div>

            @endfor

        </div>
        @if ($this->orders->hasPages())
            <div class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
                {{ $this->orders->links() }}
            </div>
        @endif
    </main>
</div>
