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
            ->with(['items'])
            ->where('customer_id', Auth::guard('customer')->id())
            ->when($this->status !== 'all', function ($query) {
                if ($this->status === 'pending_payment') {
                    $query->where('payment_status', 'pending');
                }

                if ($this->status === 'paid') {
                    $query->where('payment_status', 'paid');
                }

                if ($this->status === 'expired') {
                    $query->whereIn('status', ['expired', 'cancelled']);
                }
            })
            ->latest()
            ->paginate(10);
    }

    public function statusBadgeClass(Order $order): string
    {
        return match ($order->payment_status) {
            'paid' => 'badge-success',
            'pending_payment' => 'badge-warning',
            default => 'badge-error',
        };
    }

    public function statusText(Order $order): string
    {
        if ($order->payment_status === 'paid') {
            return 'Sudah Dibayar';
        }

        if ($order->status === 'expired') {
            return 'Kedaluwarsa';
        }

        if ($order->status === 'cancelled') {
            return 'Dibatalkan';
        }

        return 'Menunggu Bayar';
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
        {{-- Filter --}}
        <section class="rounded-[2rem] bg-base-100 p-3 shadow-sm">
            <div class="grid grid-cols-4 gap-2">
                <button type="button" wire:click="$set('status', 'all')"
                    class="btn btn-sm rounded-full {{ $status === 'all' ? 'btn-primary' : 'btn-ghost' }}">
                    Semua
                </button>

                <button type="button" wire:click="$set('status', 'pending_payment')"
                    class="btn btn-sm rounded-full {{ $status === 'pending_payment' ? 'btn-primary' : 'btn-ghost' }}">
                    Pending
                </button>

                <button type="button" wire:click="$set('status', 'paid')"
                    class="btn btn-sm rounded-full {{ $status === 'paid' ? 'btn-primary' : 'btn-ghost' }}">
                    Dibayar
                </button>

                <button type="button" wire:click="$set('status', 'expired')"
                    class="btn btn-sm rounded-full {{ $status === 'expired' ? 'btn-primary' : 'btn-ghost' }}">
                    Batal
                </button>
            </div>
        </section>

        {{-- Orders --}}
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
                <div class="rounded-[2rem] bg-base-100 p-8 text-center shadow-sm">
                    <div
                        class="mx-auto flex h-20 w-20 items-center justify-center rounded-[2rem] bg-primary/10 text-primary">
                        <x-heroicon-o-shopping-bag class="h-12 w-12" />
                    </div>

                    <h2 class="mt-4 text-xl font-black">Belum ada pesanan</h2>

                    <p class="mt-2 text-sm text-base-content/60">
                        Pesanan yang kamu buat akan muncul di sini.
                    </p>

                    <a href="{{ route('store.products') }}" wire:navigate class="btn btn-primary mt-5 rounded-full">
                        Mulai Belanja
                    </a>
                </div>
            @endforelse
        </section>

        @if ($this->orders->hasPages())
            <div class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
                {{ $this->orders->links() }}
            </div>
        @endif
    </main>
</div>
