<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Services\MidtransService;
use App\Services\ProductReviewService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Detail Pesanan')] class extends Component {
    use WithFileUploads;

    public Order $order;

    public bool $showCancelModal = false;

    // Review Modal Properties
    public bool $showReviewModal = false;
    public ?int $reviewingOrderItemId = null;
    public string $reviewingProductName = '';
    public int $rating = 5;
    public string $reviewText = '';
    public $reviewPhotos = [];
    public ?int $editingReviewId = null;
    public array $existingReviewImages = [];
    public array $removeImageIds = [];

    public function mount(Order $order): void
    {
        abort_unless($order->customer_id === Auth::guard('customer')->id(), 403);

        $this->order = $order->load(['items.review.images', 'items.review.reply']);
        $this->expireIfNeeded();
    }

    // public function payNow(): void
    // {
    //     $this->order->refresh();

    //     if ($this->order->payment_status === 'paid') {
    //         return;
    //     }

    //     if ($this->order->status !== 'pending_payment') {
    //         $this->addError('payment', 'Pesanan ini sudah tidak bisa dibayar.');
    //         return;
    //     }

    //     if ($this->order->payment_expires_at && now()->greaterThan($this->order->payment_expires_at)) {
    //         $this->addError('payment', 'Waktu pembayaran pesanan ini sudah kedaluwarsa.');
    //         return;
    //     }

    //     if (!$this->order->midtrans_redirect_url) {
    //         $this->addError('payment', 'Link pembayaran belum tersedia.');
    //         return;
    //     }

    //     $this->redirect($this->order->midtrans_redirect_url);
    // }
    public function payNow(): void
    {
        $this->order->refresh();

        if ($this->order->payment_status === 'paid') {
            return;
        }

        if ($this->order->status !== 'pending_payment') {
            $this->addError('payment', 'Pesanan ini sudah tidak bisa dibayar.');
            return;
        }

        if ($this->order->payment_expires_at && now()->greaterThan($this->order->payment_expires_at)) {
            $this->expireIfNeeded();
            $this->addError('payment', 'Waktu pembayaran pesanan ini sudah kedaluwarsa.');
            return;
        }

        if ($this->order->midtrans_redirect_url) {
            $this->redirect($this->order->midtrans_redirect_url);
            return;
        }

        $midtransOrderId = $this->order->order_number . '-PAY-1';

        $snap = app(MidtransService::class)->createSnapTransaction($this->order, $midtransOrderId);

        $this->order->update([
            'midtrans_order_id' => $midtransOrderId,
            'midtrans_snap_token' => $snap->token,
            'midtrans_redirect_url' => $snap->redirect_url,
        ]);

        $this->redirect($snap->redirect_url);
    }
    public function changePaymentMethod(): void
    {
        $this->order->refresh();

        if ($this->order->payment_status === 'paid') {
            return;
        }

        if ($this->order->status !== 'pending_payment') {
            $this->addError('payment', 'Pesanan ini sudah tidak bisa dibayar.');
            return;
        }

        if ($this->order->payment_expires_at && now()->greaterThan($this->order->payment_expires_at)) {
            $this->expireIfNeeded();
            $this->addError('payment', 'Waktu pembayaran pesanan ini sudah kedaluwarsa.');
            return;
        }

        $midtransOrderId = $this->order->order_number . '-PAY-' . now()->format('YmdHis');

        $snap = app(MidtransService::class)->createSnapTransaction($this->order, $midtransOrderId);

        $this->order->update([
            'midtrans_order_id' => $midtransOrderId,
            'midtrans_snap_token' => $snap->token,
            'midtrans_redirect_url' => $snap->redirect_url,
        ]);

        $this->redirect($snap->redirect_url);
    }
    public function isPaymentExpired(): bool
    {
        return $this->order->payment_expires_at && now()->greaterThan($this->order->payment_expires_at) && $this->order->payment_status !== 'paid';
        // return $this->order->status === 'expired' || $this->order->payment_status === 'expired';
    }

    public function formattedDateTime($date): string
    {
        if (!$date) {
            return '-';
        }

        return $date->locale('id')->translatedFormat('l, d F Y H:i');
    }
    public function cancelOrder(): void
    {
        $this->showCancelModal = false;

        $this->order->refresh()->load(['items']);

        if ($this->order->payment_status === 'paid') {
            $this->addError('payment', 'Pesanan yang sudah dibayar tidak bisa dibatalkan.');
            return;
        }

        if ($this->order->status !== 'pending_payment') {
            $this->addError('payment', 'Pesanan ini sudah tidak bisa dibatalkan.');
            return;
        }

        DB::transaction(function () {
            $order = Order::query()->whereKey($this->order->id)->lockForUpdate()->firstOrFail();

            if ($order->stock_restored_at || $order->stock_released_at || $order->status === 'cancelled') {
                $order->update([
                    'status' => 'cancelled',
                    'payment_status' => 'cancelled',
                    'shipping_status' => 'cancelled',
                ]);

                return;
            }

            app(\App\Services\StockReservationService::class)->release($order, 'customer_cancelled');

            if ($order->voucher_id) {
                VoucherUsage::where('order_id', $order->id)->delete();
                $voucher = Voucher::whereKey($order->voucher_id)->lockForUpdate()->first();
                if ($voucher && $voucher->total_used > 0) {
                    $voucher->decrement('total_used');
                }
            }

            $order->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
                'shipping_status' => 'cancelled',
            ]);
        });

        session()->flash('flash.type', 'success');
        session()->flash('flash.title', 'Pesanan dibatalkan');
        session()->flash('flash.message', 'Pesanan berhasil dibatalkan dan stok produk dikembalikan.');

        $this->redirectRoute('store.orders.show', $this->order->id, navigate: true);
    }
    public function openCancelModal(): void
    {
        $this->resetErrorBag('payment');

        if ($this->order->payment_status === 'paid') {
            $this->addError('payment', 'Pesanan yang sudah dibayar tidak bisa dibatalkan.');
            return;
        }

        if (!in_array($this->order->status, ['pending_payment'], true)) {
            $this->addError('payment', 'Pesanan ini sudah tidak bisa dibatalkan.');
            return;
        }

        $this->showCancelModal = true;
    }

    public function closeCancelModal(): void
    {
        $this->showCancelModal = false;
    }
    public function expireIfNeeded(): void
    {
        $this->order->refresh();

        if ($this->order->payment_status === 'paid') {
            return;
        }

        if ($this->order->status !== 'pending_payment') {
            return;
        }

        if (!$this->order->payment_expires_at || now()->lessThan($this->order->payment_expires_at)) {
            return;
        }

        app(\App\Jobs\ExpireOrderJob::class, [
            'orderId' => $this->order->id,
        ])->handle();

        $this->order->refresh()->load(['items']);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->order->status) {
            'pending_payment' => 'badge-warning',
            'confirmed' => 'badge-info',
            'processing' => $this->order->shipping_status === 'shipped' ? 'badge-primary' : 'badge-accent',
            'completed' => 'badge-success',
            'expired', 'cancelled' => 'badge-error',
            default => 'badge-neutral',
        };
    }

    public function statusText(): string
    {
        return match ($this->order->status) {
            'pending_payment' => 'Menunggu Pembayaran',
            'confirmed' => 'Menunggu Pengiriman',
            'processing' => $this->order->shipping_status === 'shipped' ? 'Sedang Dikirim' : 'Sedang Diproses',
            'completed' => 'Pesanan Selesai',
            'expired' => 'Pembayaran Kedaluwarsa',
            'cancelled' => 'Pesanan Dibatalkan',
            default => strtoupper(str_replace('_', ' ', $this->order->status)),
        };
    }

    public function isComplaintAvailable(): bool
    {
        return app(\App\Services\ContactSettings::class)->isAvailable();
    }

    public function complaintUrl(): ?string
    {
        return app(\App\Services\ContactSettings::class)->getComplaintUrl($this->order);
    }

    public function openReviewModal(int $orderItemId): void
    {
        $this->resetReviewForm();

        $item = $this->order->items()->where('id', $orderItemId)->with('review.images')->first();
        if (!$item) {
            return;
        }

        $this->reviewingOrderItemId = $item->id;
        $this->reviewingProductName = $item->product_name;

        if ($item->review) {
            $this->editingReviewId = $item->review->id;
            $this->rating = $item->review->rating;
            $this->reviewText = $item->review->review;
            $this->existingReviewImages = $item->review->images
                ->map(
                    fn($img) => [
                        'id' => $img->id,
                        'url' => $img->url,
                    ],
                )
                ->all();
        } else {
            $this->rating = 5;
            $this->reviewText = '';
        }

        $this->showReviewModal = true;
    }

    public function closeReviewModal(): void
    {
        $this->showReviewModal = false;
        $this->resetReviewForm();
    }

    public function resetReviewForm(): void
    {
        $this->resetErrorBag();
        $this->reviewingOrderItemId = null;
        $this->reviewingProductName = '';
        $this->rating = 5;
        $this->reviewText = '';
        $this->reviewPhotos = [];
        $this->editingReviewId = null;
        $this->existingReviewImages = [];
        $this->removeImageIds = [];
    }

    public function markImageForRemoval(int $imageId): void
    {
        if (!in_array($imageId, $this->removeImageIds, true)) {
            $this->removeImageIds[] = $imageId;
        }
    }

    public function cancelImageRemoval(int $imageId): void
    {
        $this->removeImageIds = array_values(array_diff($this->removeImageIds, [$imageId]));
    }

    public function removeTempPhoto(int $index): void
    {
        unset($this->reviewPhotos[$index]);
        $this->reviewPhotos = array_values($this->reviewPhotos);
    }

    public function submitReview(): void
    {
        $this->validate(
            [
                'rating' => 'required|integer|min:1|max:5',
                'reviewText' => 'required|string|min:3|max:2000',
                'reviewPhotos.*' => 'nullable|image|max:3072',
            ],
            [
                'rating.required' => 'Pilih rating bintang.',
                'rating.min' => 'Rating minimal 1 bintang.',
                'rating.max' => 'Rating maksimal 5 bintang.',
                'reviewText.required' => 'Tuliskan ulasan pengalaman Anda.',
                'reviewText.min' => 'Ulasan minimal 3 karakter.',
                'reviewPhotos.*.image' => 'File harus berupa gambar (JPG, PNG, WebP).',
                'reviewPhotos.*.max' => 'Ukuran maksimal foto 3MB.',
            ],
        );

        $customer = Auth::guard('customer')->user();
        if (!$customer) {
            $this->redirectRoute('customer.login', navigate: true);
            return;
        }

        $item = $this->order->items()->where('id', $this->reviewingOrderItemId)->first();
        if (!$item) {
            $this->addError('reviewText', 'Item pesanan tidak ditemukan.');
            return;
        }

        $service = app(ProductReviewService::class);

        try {
            if ($this->editingReviewId) {
                $review = ProductReview::where('id', $this->editingReviewId)->where('customer_id', $customer->id)->firstOrFail();

                $service->updateReview($customer, $review, ['rating' => $this->rating, 'review' => $this->reviewText], $this->reviewPhotos, $this->removeImageIds);

                $this->dispatch('notify', type: 'success', title: 'Ulasan Diperbarui', message: 'Ulasan Anda berhasil diperbarui.');
            } else {
                $service->createReview($customer, $item, ['rating' => $this->rating, 'review' => $this->reviewText], $this->reviewPhotos);

                $this->dispatch('notify', type: 'success', title: 'Terima Kasih!', message: 'Ulasan Anda berhasil dikirim dan dipublikasikan.');
            }

            $this->closeReviewModal();
            $this->order->refresh()->load(['items.review.images', 'items.review.reply']);
        } catch (\Throwable $e) {
            $this->addError('reviewText', $e->getMessage());
        }
    }

    public function deleteReview(): void
    {
        if (!$this->editingReviewId) {
            return;
        }

        $customer = Auth::guard('customer')->user();
        if (!$customer) {
            return;
        }

        try {
            $review = ProductReview::where('id', $this->editingReviewId)->where('customer_id', $customer->id)->firstOrFail();

            app(ProductReviewService::class)->deleteReview($customer, $review);

            $this->dispatch('notify', type: 'info', title: 'Ulasan Dihapus', message: 'Ulasan Anda telah dihapus.');
            $this->closeReviewModal();
            $this->order->refresh()->load(['items.review.images', 'items.review.reply']);
        } catch (\Throwable $e) {
            $this->addError('reviewText', $e->getMessage());
        }
    }
};
?>

<div class="min-h-screen bg-base-200 pb-24">
    <header class="sticky top-0 z-40 border-b border-base-300 bg-base-100/95 backdrop-blur">
        <div class="flex items-center gap-3 p-4">
            <a href="{{ route('store.orders') }}" wire:navigate class="btn btn-circle btn-sm btn-ghost">
                <x-heroicon-o-chevron-left class="h-5 w-5" />
            </a>

            <div>
                <h1 class="text-lg font-black">Detail Pesanan</h1>
                <p class="text-xs text-base-content/50">
                    {{ $order->order_number }}
                </p>
            </div>
        </div>
    </header>

    <main class="space-y-4 p-4">
        @error('payment')
            <div class="alert alert-error alert-soft rounded-2xl">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                <span>{{ $message }}</span>
            </div>
        @enderror

        <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
            <div class="flex flex-wrap sm:flex-nowrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-base-content/50">Nomor Order</p>
                    <h2 class="font-black text-sm sm:text-base break-all">{{ $order->order_number }}</h2>
                </div>

                <span
                    class="badge {{ $this->statusBadgeClass() }} shrink-0 whitespace-nowrap text-xs font-bold py-2.5 px-3 h-auto leading-normal">
                    {{ $this->statusText() }}
                </span>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-2xl bg-base-200 p-3">
                    <p class="text-xs text-base-content/50">Pembayaran</p>
                    <p class="font-bold truncate">{{ strtoupper(str_replace('_', ' ', $order->payment_status)) }}</p>
                </div>

                <div class="rounded-2xl bg-base-200 p-3">
                    <p class="text-xs text-base-content/50">Pengiriman</p>
                    <p class="font-bold truncate">{{ strtoupper(str_replace('_', ' ', $order->shipping_status)) }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
            <div class="mb-3 flex items-start sm:items-center justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <h2 class="font-black">Info Transaksi</h2>
                    <p class="text-xs text-base-content/50">
                        Detail waktu dan pembayaran
                    </p>
                </div>

                @if ($order->payment_status === 'paid')
                    <span
                        class="badge badge-success shrink-0 whitespace-nowrap text-xs font-bold py-2.5 px-3 h-auto leading-normal">Lunas</span>
                @elseif ($this->isPaymentExpired())
                    <span
                        class="badge badge-error shrink-0 whitespace-nowrap text-xs font-bold py-2.5 px-3 h-auto leading-normal">Kedaluwarsa</span>
                @else
                    <span
                        class="badge badge-warning shrink-0 whitespace-nowrap text-xs font-bold py-2.5 px-3 h-auto leading-normal">Menunggu
                        Bayar</span>
                @endif
            </div>

            <div class="space-y-3 text-sm">
                <div class="flex items-start justify-between gap-4">
                    <span class="text-base-content/60">Dibuat</span>
                    <span class="text-right font-bold">
                        {{ $this->formattedDateTime($order->created_at) }}
                    </span>
                </div>

                <div class="flex items-start justify-between gap-4">
                    <span class="text-base-content/60">Batas Bayar</span>
                    <span class="text-right font-bold">
                        {{ $this->formattedDateTime($order->payment_expires_at) }}
                    </span>
                </div>

                @if ($order->paid_at)
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-base-content/60">Dibayar</span>
                        <span class="text-right font-bold text-success">
                            {{ $this->formattedDateTime($order->paid_at) }}
                        </span>
                    </div>
                @endif
            </div>

            @if ($order->status === 'expired' || $order->payment_status === 'expired')
                <div class="mt-4 rounded-2xl border border-error/30 bg-error/10 p-4 text-center">
                    <x-heroicon-o-clock class="mx-auto h-9 w-9 text-error" />

                    <p class="mt-2 font-black text-error">
                        Waktu pembayaran sudah habis
                    </p>

                    <p class="mt-1 text-xs text-base-content/60">
                        Pesanan ini sudah kedaluwarsa dan tidak bisa dibayar lagi.
                    </p>
                </div>
            @elseif ($order->payment_status !== 'paid' && $order->status === 'pending_payment' && $order->payment_expires_at)
                <div x-data="{
                    end: new Date('{{ $order->payment_expires_at->toIso8601String() }}').getTime(),
                    now: Date.now(),
                    timer: null,
                    get remaining() {
                        return Math.max(0, this.end - this.now);
                    },
                    get expired() {
                        return this.remaining <= 0;
                    },
                    get hours() {
                        return Math.floor(this.remaining / 1000 / 60 / 60);
                    },
                    get minutes() {
                        return Math.floor((this.remaining / 1000 / 60) % 60);
                    },
                    get seconds() {
                        return Math.floor((this.remaining / 1000) % 60);
                    },
                    pad(value) {
                        return String(value).padStart(2, '0');
                    },
                    init() {
                        this.timer = setInterval(() => {
                            this.now = Date.now();
                
                            if (this.expired) {
                                clearInterval(this.timer);
                            }
                        }, 1000);
                    }
                }" class="mt-4 rounded-2xl border border-warning/30 bg-warning/10 p-4">
                    <template x-if="!expired">
                        <div>
                            <div class="flex items-center justify-center gap-2 text-warning">
                                <x-heroicon-o-clock class="h-5 w-5" />
                                <p class="text-sm font-black">
                                    Sisa Waktu Pembayaran
                                </p>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-2xl bg-base-100 p-3 shadow-sm">
                                    <p class="text-2xl font-black text-warning" x-text="pad(hours)"></p>
                                    <p class="mt-1 text-xs text-base-content/50">Jam</p>
                                </div>

                                <div class="rounded-2xl bg-base-100 p-3 shadow-sm">
                                    <p class="text-2xl font-black text-warning" x-text="pad(minutes)"></p>
                                    <p class="mt-1 text-xs text-base-content/50">Menit</p>
                                </div>

                                <div class="rounded-2xl bg-base-100 p-3 shadow-sm">
                                    <p class="text-2xl font-black text-warning" x-text="pad(seconds)"></p>
                                    <p class="mt-1 text-xs text-base-content/50">Detik</p>
                                </div>
                            </div>

                            {{-- <p class="mt-3 text-center text-xs text-base-content/60">
                                Stok pesanan ini ditahan sementara sampai batas waktu pembayaran.
                            </p> --}}
                        </div>
                    </template>

                    <template x-if="expired">
                        <div class="text-center">
                            <div
                                class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-error/10 text-error">
                                <x-heroicon-o-clock class="h-8 w-8" />
                            </div>

                            <p class="mt-3 font-black text-error">
                                Waktu pembayaran sudah habis
                            </p>

                            <p class="mt-1 text-xs text-base-content/60">
                                Pesanan ini sudah melewati batas waktu pembayaran.
                            </p>
                        </div>
                    </template>
                </div>
            @endif
        </section>
        <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
            <h2 class="mb-3 font-black">Alamat Pengiriman</h2>

            <p class="font-bold">{{ $order->recipient_name }}</p>
            <p class="text-sm text-base-content/60">{{ $order->recipient_phone }}</p>
            <p class="mt-2 text-sm leading-relaxed text-base-content/70">
                {{ $order->shipping_address }}
            </p>

            <div class="mt-3 rounded-2xl bg-base-200 p-3 text-sm">
                <p class="font-bold">
                    {{ strtoupper($order->shipping_courier ?? '-') }} {{ $order->shipping_service }}
                </p>
                <p class="text-xs text-base-content/50">
                    Estimasi {{ $order->shipping_etd ?: '-' }}
                </p>
            </div>

            @if ($order->payment_status === 'paid')
                <a href="{{ route('store.orders.tracking', $order) }}" wire:navigate
                    class="btn btn-outline btn-primary mt-3 w-full rounded-full">
                    <x-heroicon-o-truck class="h-5 w-5" />
                    Tracking Paket
                </a>
            @endif
        </section>

        <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
            <h2 class="mb-3 font-black">Produk</h2>

            <div class="space-y-3">
                @foreach ($order->items as $item)
                    <div class="border-b border-base-200 pb-3 last:border-b-0 last:pb-0">
                        <div class="flex justify-between gap-3">
                            <div class="min-w-0">
                                <p class="line-clamp-2 text-sm font-bold">
                                    {{ $item->product_name }}
                                </p>

                                @if ($item->variant_name)
                                    <p class="text-xs text-base-content/50">
                                        {{ $item->variant_name }}
                                    </p>
                                @endif

                                <p class="mt-1 text-xs text-base-content/50">
                                    x{{ $item->qty }}
                                </p>
                            </div>

                            <p class="shrink-0 text-sm font-black">
                                Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                            </p>
                        </div>

                        {{-- Review status & button for completed order --}}
                        @if ($this->order->canBeReviewed())
                            <div
                                class="mt-2.5 pt-2 border-t border-dashed border-base-200 flex items-center justify-between">
                                @if ($item->review)
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="badge badge-success badge-sm font-bold gap-1 text-[11px] text-white">
                                            <x-heroicon-s-star class="h-3 w-3 text-warning" />
                                            {{ $item->review->rating }}/5
                                        </span>
                                        <span class="text-[11px] text-base-content/60 font-medium">Sudah diulas</span>
                                    </div>
                                    <button type="button" wire:click="openReviewModal({{ $item->id }})"
                                        class="btn btn-ghost btn-xs text-primary font-bold">
                                        Lihat / Edit Ulasan
                                    </button>
                                @else
                                    <span class="text-[11px] text-base-content/50">Belum diulas</span>
                                    <button type="button" wire:click="openReviewModal({{ $item->id }})"
                                        class="btn btn-primary btn-xs rounded-xl px-3 font-bold gap-1 shadow-xs text-white">
                                        <x-heroicon-o-star class="h-3.5 w-3.5" />
                                        Beri Ulasan
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
            <h2 class="mb-3 font-black">Ringkasan</h2>

            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-base-content/60">Subtotal</span>
                    <span class="font-bold">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
                </div>

                <div class="flex justify-between">
                    <span class="text-base-content/60">Ongkir</span>
                    <span class="font-bold">Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
                </div>

                @if ($order->discount > 0)
                    <div class="flex justify-between text-success">
                        <span class="flex items-center gap-1">
                            Diskon
                            @if ($order->voucher_code)
                                <span
                                    class="badge badge-xs badge-primary font-mono font-bold shrink-0 whitespace-nowrap">{{ $order->voucher_code }}</span>
                            @endif
                        </span>
                        <span class="font-bold">- Rp {{ number_format($order->discount, 0, ',', '.') }}</span>
                    </div>
                @endif

                <div class="border-t border-base-200 pt-2">
                    <div class="flex justify-between">
                        <span class="font-black">Total</span>
                        <span class="text-lg font-black text-primary">
                            Rp {{ number_format($order->grand_total, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
            @if ($order->payment_status !== 'paid' && $order->status === 'pending_payment' && !$this->isPaymentExpired())
                <div class="mt-4 space-y-3 border-t border-base-200 pt-4">
                    <button type="button" wire:click="payNow" wire:loading.attr="disabled" wire:target="payNow"
                        class="btn btn-primary w-full rounded-full">
                        <span wire:loading.remove wire:target="payNow">
                            Bayar Sekarang
                        </span>

                        <span wire:loading wire:target="payNow" class="flex items-center gap-2">
                            <span class="loading loading-spinner loading-sm"></span>
                            Membuka pembayaran...
                        </span>
                    </button>
                    @if ($order->midtrans_redirect_url)
                        <button type="button" wire:click="changePaymentMethod" wire:loading.attr="disabled"
                            wire:target="changePaymentMethod" class="btn btn-outline btn-primary w-full rounded-full">
                            <span wire:loading.remove wire:target="changePaymentMethod">
                                Ganti Metode Pembayaran
                            </span>

                            <span wire:loading wire:target="changePaymentMethod" class="flex items-center gap-2">
                                <span class="loading loading-spinner loading-sm"></span>
                                Membuka metode baru...
                            </span>
                        </button>
                    @endif
                    <button type="button" wire:click="openCancelModal" wire:loading.attr="disabled"
                        wire:target="openCancelModal" class="btn btn-outline btn-error w-full rounded-full">
                        Batalkan Pesanan
                    </button>


                </div>
            @endif

            {{-- <p class="text-center text-xs text-base-content/50">
                Stok pesanan ini ditahan sementara sampai batas waktu pembayaran.
            </p> --}}
            @if ($order->status === 'cancelled')
                <div class="mt-4 rounded-2xl bg-error/10 p-3 text-sm text-error">
                    <div class="flex gap-2">
                        <x-heroicon-o-x-circle class="h-5 w-5 shrink-0" />
                        <p class="font-medium">Pesanan ini sudah dibatalkan.</p>
                    </div>
                </div>
            @endif

            @if ($order->payment_status === 'paid')
                <div class="mt-4 rounded-2xl bg-success/10 p-3 text-sm text-success">
                    <div class="flex gap-2">
                        <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" />
                        <p class="font-medium">Pembayaran berhasil diterima.</p>
                    </div>
                </div>
            @endif
            @if ($this->isPaymentExpired() && $order->status === 'pending_payment')
                <div class="mt-4 rounded-2xl bg-error/10 p-3 text-sm text-error">
                    <div class="flex gap-2">
                        <x-heroicon-o-clock class="h-5 w-5 shrink-0" />
                        <div>
                            <p class="font-bold">Waktu pembayaran sudah habis.</p>
                            {{-- <p class="mt-1 text-xs text-error/80">
                                Pesanan ini perlu dibatalkan otomatis agar stok dikembalikan.
                            </p> --}}
                        </div>
                    </div>
                </div>
            @endif
        </section>

        {{-- Section Ajukan Komplain via WhatsApp --}}
        <section class="rounded-[2rem] bg-base-100 p-4 shadow-sm">
            <div class="flex items-center gap-3 mb-2">
                {{-- <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl  text-emerald-600">
                    <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2m.01 1.67c2.2 0 4.26.86 5.82 2.42a8.225 8.225 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.196 8.196 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24m4.52 11.66c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.02-1.25-.75-.67-1.26-1.5-1.41-1.75-.14-.25-.02-.39.11-.51.11-.11.25-.29.38-.44.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.44-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1s.9 2.43 1.03 2.6c.12.17 1.77 2.7 4.29 3.79.6.26 1.07.41 1.44.53.6.19 1.15.16 1.58.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.1-.23-.17-.48-.29" />
                    </svg>
                </div> --}}
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-500">
                    <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2m.01 1.67c2.2 0 4.26.86 5.82 2.42a8.225 8.225 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.196 8.196 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24m4.52 11.66c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.02-1.25-.75-.67-1.26-1.5-1.41-1.75-.14-.25-.02-.39.11-.51.11-.11.25-.29.38-.44.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.44-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1s.9 2.43 1.03 2.6c.12.17 1.77 2.7 4.29 3.79.6.26 1.07.41 1.44.53.6.19 1.15.16 1.58.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.1-.23-.17-.48-.29" />
                    </svg>
                </div>
                <div>
                    <h2 class="font-black text-sm sm:text-base">Butuh Bantuan Pesanan?</h2>
                    <p class="text-xs text-base-content/60">Layanan Customer Service
                        {{ config('services.apps.app_name') }}</p>
                </div>
            </div>

            <p class="text-xs sm:text-sm text-base-content/70 leading-relaxed mb-4">
                Jika terdapat masalah dengan pesanan, produk rusak, produk tidak sesuai, atau kendala pengiriman,
                silakan hubungi Customer Service kami.
            </p>

            @if ($this->isComplaintAvailable())
                <a href="{{ $this->complaintUrl() }}" target="_blank" rel="noopener noreferrer"
                    class="btn w-full rounded-full border-none shadow-sm flex items-center justify-center gap-2 font-bold text-sm sm:text-base py-3">
                    {{-- <svg class="h-5 w-5 fill-current shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2m.01 1.67c2.2 0 4.26.86 5.82 2.42a8.225 8.225 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.196 8.196 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24m4.52 11.66c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.13-1.06-.39-2.02-1.25-.75-.67-1.26-1.5-1.41-1.75-.14-.25-.02-.39.11-.51.11-.11.25-.29.38-.44.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.44-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1s.9 2.43 1.03 2.6c.12.17 1.77 2.7 4.29 3.79.6.26 1.07.41 1.44.53.6.19 1.15.16 1.58.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.1-.23-.17-.48-.29" />
                    </svg> --}}
                    <span>Ajukan Komplain via WhatsApp</span>
                </a>
            @else
                <div
                    class="rounded-2xl bg-base-200 p-3 text-xs sm:text-sm text-base-content/60 flex items-center gap-2">
                    <x-heroicon-o-information-circle class="h-5 w-5 shrink-0 text-base-content/40" />
                    <span>Layanan Customer Service WhatsApp saat ini sedang tidak aktif atau belum dikonfigurasi.</span>
                </div>
            @endif
        </section>
    </main>

    @if ($showCancelModal)
        <div class="modal modal-open">
            <div class="modal-box rounded-[2rem]">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-error/10 text-error">
                    <x-heroicon-o-exclamation-triangle class="h-9 w-9" />
                </div>

                <h3 class="mt-4 text-center text-xl font-black">
                    Batalkan Pesanan?
                </h3>

                <p class="mt-2 text-center text-sm leading-relaxed text-base-content/60">
                    Pesanan ini akan dibatalkan dan stok produk akan dikembalikan.
                    Kamu tidak bisa membayar pesanan ini setelah dibatalkan.
                </p>

                <div class="mt-5 rounded-2xl bg-base-200 p-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="text-base-content/60">Nomor Order</span>
                        <span class="font-bold text-right">{{ $order->order_number }}</span>
                    </div>

                    <div class="mt-2 flex justify-between gap-3">
                        <span class="text-base-content/60">Total</span>
                        <span class="font-black text-primary">
                            Rp {{ number_format($order->grand_total, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <div class="modal-action grid grid-cols-2 gap-2">
                    <button type="button" wire:click="closeCancelModal" class="btn btn-ghost rounded-full">
                        Tidak Jadi
                    </button>

                    <button type="button" wire:click="cancelOrder" wire:loading.attr="disabled"
                        wire:target="cancelOrder" class="btn btn-error rounded-full">
                        <span wire:loading.remove wire:target="cancelOrder">
                            Ya, Batalkan
                        </span>

                        <span wire:loading wire:target="cancelOrder" class="flex items-center gap-2">
                            <span class="loading loading-spinner loading-sm"></span>
                            Proses...
                        </span>
                    </button>
                </div>
            </div>

            <div class="modal-backdrop" wire:click="closeCancelModal"></div>
        </div>
    @endif

    {{-- Review Modal --}}
    @if ($showReviewModal)
        <div class="modal modal-open z-50">
            <div
                class="modal-box rounded-[2rem] max-w-md w-full bg-base-100 p-5 sm:p-6 shadow-2xl border border-base-200">
                <div class="flex items-start justify-between gap-3 border-b border-base-200 pb-3">
                    <div>
                        <h3 class="text-base font-black text-base-content">
                            {{ $editingReviewId ? 'Edit Ulasan Produk' : 'Beri Ulasan Produk' }}
                        </h3>
                        <p class="text-xs text-primary font-bold mt-0.5 line-clamp-1">
                            {{ $reviewingProductName }}
                        </p>
                    </div>

                    <button type="button" wire:click="closeReviewModal"
                        class="btn btn-ghost btn-xs btn-circle text-base-content/50">
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    {{-- Interactive Star Picker --}}
                    <div>
                        <label class="block text-xs font-bold text-base-content/70 mb-1.5">Rating Kepuasan</label>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center gap-1">
                                @for ($s = 1; $s <= 5; $s++)
                                    <button type="button" wire:click="$set('rating', {{ $s }})"
                                        class="p-1 transition hover:scale-110 active:scale-95 focus:outline-none">
                                        @if ($rating >= $s)
                                            <x-heroicon-s-star class="h-7 w-7 text-amber-400 drop-shadow-xs" />
                                        @else
                                            <x-heroicon-o-star class="h-7 w-7 text-base-300 hover:text-amber-300" />
                                        @endif
                                    </button>
                                @endfor
                            </div>
                            <span class="text-xs font-bold text-amber-500">
                                @if ($rating === 5)
                                    Sangat Puas ⭐ 5/5
                                @elseif ($rating === 4)
                                    Puas ⭐ 4/5
                                @elseif ($rating === 3)
                                    Cukup ⭐ 3/5
                                @elseif ($rating === 2)
                                    Kurang ⭐ 2/5
                                @else
                                    Sangat Buruk ⭐ 1/5
                                @endif
                            </span>
                        </div>
                        @error('rating')
                            <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Review Text --}}
                    <div>
                        <label class="block text-xs font-bold text-base-content/70 mb-1.5">Ulasan Anda</label>
                        <textarea wire:model="reviewText" rows="4"
                            class="textarea textarea-bordered w-full rounded-2xl text-xs sm:text-sm placeholder:text-base-content/40 focus:border-primary"
                            placeholder="Bagaimana kualitas produk, rasa, kemasan, dan kecepatan pengiriman menurut Anda?"></textarea>
                        @error('reviewText')
                            <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Existing Images (if editing) --}}
                    @if (!empty($existingReviewImages))
                        <div>
                            <label class="block text-xs font-bold text-base-content/70 mb-1.5">Foto Sebelumnya</label>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($existingReviewImages as $img)
                                    @php $isRemoved = in_array($img['id'], $removeImageIds, true); @endphp
                                    <div
                                        class="relative h-16 w-16 rounded-xl overflow-hidden border {{ $isRemoved ? 'opacity-40 border-error' : 'border-base-300' }}">
                                        <img src="{{ $img['url'] }}" class="h-full w-full object-cover">
                                        @if ($isRemoved)
                                            <button type="button"
                                                wire:click="cancelImageRemoval({{ $img['id'] }})"
                                                class="absolute inset-0 bg-black/60 text-white text-[10px] flex items-center justify-center font-bold">
                                                Batal Hapus
                                            </button>
                                        @else
                                            <button type="button"
                                                wire:click="markImageForRemoval({{ $img['id'] }})"
                                                class="absolute top-1 right-1 h-5 w-5 rounded-full bg-error text-white flex items-center justify-center shadow-xs">
                                                <x-heroicon-o-x-mark class="h-3 w-3" />
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Upload New Photos --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-base-content/70">Tambah Foto Produk
                                (Opsional)</label>
                            <span class="text-[10px] text-base-content/40">Maks. 5 foto (3MB)</span>
                        </div>

                        <input type="file" wire:model="reviewPhotos" multiple
                            accept="image/jpeg,image/png,image/webp,image/jpg"
                            class="file-input file-input-bordered file-input-sm w-full rounded-xl text-xs" />

                        <div wire:loading wire:target="reviewPhotos"
                            class="text-xs text-primary mt-1 flex items-center gap-1.5">
                            <span class="loading loading-spinner loading-xs"></span> Mengunggah foto...
                        </div>

                        @error('reviewPhotos.*')
                            <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror

                        {{-- Preview New Photos --}}
                        @if (!empty($reviewPhotos))
                            <div class="flex flex-wrap gap-2 mt-2">
                                @foreach ($reviewPhotos as $idx => $photo)
                                    <div
                                        class="relative h-16 w-16 rounded-xl overflow-hidden border border-primary/30 shadow-xs">
                                        <img src="{{ $photo->temporaryUrl() }}" class="h-full w-full object-cover">
                                        <button type="button" wire:click="removeTempPhoto({{ $idx }})"
                                            class="absolute top-1 right-1 h-5 w-5 rounded-full bg-black/70 text-white flex items-center justify-center">
                                            <x-heroicon-o-x-mark class="h-3 w-3" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Modal Actions --}}
                <div class="modal-action mt-5 flex items-center justify-between gap-2">
                    <div>
                        @if ($editingReviewId)
                            <button type="button" wire:click="deleteReview"
                                wire:confirm="Yakin ingin menghapus ulasan ini?"
                                class="btn btn-ghost btn-sm text-error rounded-xl font-bold">
                                Hapus Ulasan
                            </button>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="closeReviewModal" class="btn btn-ghost btn-sm rounded-xl">
                            Batal
                        </button>

                        <button type="button" wire:click="submitReview" wire:loading.attr="disabled"
                            class="btn btn-primary btn-sm rounded-xl px-5 font-bold shadow-xs text-white">
                            <span wire:loading.remove wire:target="submitReview">
                                {{ $editingReviewId ? 'Simpan Perubahan' : 'Kirim Ulasan' }}
                            </span>
                            <span wire:loading wire:target="submitReview" class="flex items-center gap-1.5">
                                <span class="loading loading-spinner loading-xs"></span>
                                Memproses...
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal-backdrop" wire:click="closeReviewModal"></div>
        </div>
    @endif
</div>
