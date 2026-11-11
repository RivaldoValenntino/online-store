<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerOrderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $event,
        public ?string $extraMessage = null
    ) {
        $this->order->loadMissing(['items']);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->getSubject())
            ->view('emails.customer.order-status', [
                'customer' => $notifiable,
                'order' => $this->order,
                'title' => $this->getTitle(),
                'messageText' => $this->getMessage(),
                'event' => $this->event,
                'actionUrl' => route('store.orders.show', $this->order),
            ]);
    }

    /**
     * Get the array representation of the notification (in-app database).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'event' => $this->event,
            'title' => $this->getTitle(),
            'message' => $this->getMessage(),
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'url' => route('store.orders.show', $this->order),
        ];
    }

    public function getSubject(): string
    {
        return match ($this->event) {
            'order_created' => "Konfirmasi Pesanan #{$this->order->order_number}",
            'payment_success' => "Pembayaran Diterima - Pesanan #{$this->order->order_number}",
            'order_processing' => "Pesanan #{$this->order->order_number} Sedang Disiapkan",
            'order_shipped' => "Pesanan #{$this->order->order_number} Sedang Dikirim",
            'order_completed' => "Pesanan #{$this->order->order_number} Telah Selesai",
            'order_cancelled' => "Pembatalan Pesanan #{$this->order->order_number}",
            'order_refunded' => "Informasi Refund Pesanan #{$this->order->order_number}",
            default => "Update Pesanan #{$this->order->order_number}",
        };
    }

    public function getTitle(): string
    {
        return match ($this->event) {
            'order_created' => 'Konfirmasi Pesanan',
            'payment_success' => 'Pembayaran Diterima',
            'order_processing' => 'Pesanan Sedang Disiapkan',
            'order_shipped' => 'Pesanan Sedang Dikirim',
            'order_completed' => 'Konfirmasi Pesanan Selesai',
            'order_cancelled' => 'Informasi Pembatalan',
            'order_refunded' => 'Informasi Proses Refund',
            default => 'Update Status Pesanan',
        };
    }

    public function getMessage(): string
    {
        if ($this->extraMessage) {
            return $this->extraMessage;
        }

        $formattedTotal = 'Rp ' . number_format($this->order->grand_total, 0, ',', '.');

        return match ($this->event) {
            'order_created' => "Pesanan kamu #{$this->order->order_number} berhasil dibuat dengan total {$formattedTotal}. Silakan lakukan pembayaran sebelum batas waktu berakhir.",
            'payment_success' => "Pembayaran sebesar {$formattedTotal} untuk pesanan #{$this->order->order_number} berhasil kami terima. Pesanan kamu akan segera diproses.",
            'order_processing' => "Pesanan kamu #{$this->order->order_number} sedang disiapkan dan dikemas oleh toko.",
            'order_shipped' => "Pesanan kamu #{$this->order->order_number} sudah dikirimkan" . ($this->order->shipping_courier ? " via " . strtoupper($this->order->shipping_courier) : "") . ($this->order->tracking_number ? " dengan nomor resi {$this->order->tracking_number}." : "."),
            'order_completed' => "Pesanan kamu #{$this->order->order_number} telah sampai dan selesai. Terima kasih telah berbelanja di toko kami!",
            'order_cancelled' => "Pesanan #{$this->order->order_number} telah dibatalkan." . ($this->order->cancel_reason ? " Alasan: {$this->order->cancel_reason}." : ""),
            'order_refunded' => "Proses pengembalian dana (refund) untuk pesanan #{$this->order->order_number} sedang atau telah selesai diproses. Silakan periksa rekening atau saldo kamu.",
            default => "Ada pembaruan status pada pesanan kamu #{$this->order->order_number}.",
        };
    }

    public function getIcon(): string
    {
        return match ($this->event) {
            'order_created' => 'heroicon-o-shopping-bag',
            'payment_success' => 'heroicon-o-check-circle',
            'order_processing' => 'heroicon-o-arrow-path',
            'order_shipped' => 'heroicon-o-truck',
            'order_completed' => 'heroicon-o-sparkles',
            'order_cancelled' => 'heroicon-o-x-circle',
            'order_refunded' => 'heroicon-o-arrow-uturn-left',
            default => 'heroicon-o-bell',
        };
    }

    public function getColor(): string
    {
        return match ($this->event) {
            'order_created' => 'amber',
            'payment_success' => 'emerald',
            'order_processing' => 'sky',
            'order_shipped' => 'indigo',
            'order_completed' => 'emerald',
            'order_cancelled' => 'rose',
            'order_refunded' => 'purple',
            default => 'primary',
        };
    }
}
