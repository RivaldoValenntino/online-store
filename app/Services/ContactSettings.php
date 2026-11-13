<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Order;
use Illuminate\Support\Carbon;

class ContactSettings
{
    public const GROUP = 'contact';

    public const KEYS = [
        'cs_name',
        'cs_phone',
        'cs_is_active',
    ];

    /**
     * Retrieve all contact settings with defaults.
     */
    public function all(): array
    {
        $settings = AppSetting::query()
            ->where('group', self::GROUP)
            ->pluck('value', 'key');

        $hasPhone = array_key_exists('cs_phone', $settings->all());
        $hasActive = array_key_exists('cs_is_active', $settings->all());
        $hasName = array_key_exists('cs_name', $settings->all());

        return [
            'cs_name' => ($hasName && filled($settings['cs_name']))
                ? $settings['cs_name']
                : config('services.contact.cs_name', 'CS ' . (config('services.apps.app_name') ?: config('app.name', 'Podo Raos'))),
            'cs_phone' => $hasPhone
                ? $settings['cs_phone']
                : config('services.contact.cs_phone', ''),
            'cs_is_active' => ($hasActive && ! is_null($settings['cs_is_active']))
                ? filter_var($settings['cs_is_active'], FILTER_VALIDATE_BOOLEAN)
                : filter_var(config('services.contact.cs_is_active', true), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * Get a specific setting value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default;
    }

    /**
     * Normalize phone number to international E.164 format without '+' (e.g. 628xxxxxxxxxx).
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        // Keep only digits
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (blank($digits)) {
            return null;
        }

        // Replace leading 0 with 62
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        }

        // Must start with 62 and be at least 9 digits
        if (! str_starts_with($digits, '62') || strlen($digits) < 9) {
            return null;
        }

        return $digits;
    }

    /**
     * Check if customer service via WhatsApp is available.
     */
    public function isAvailable(): bool
    {
        $isActive = filter_var($this->get('cs_is_active', true), FILTER_VALIDATE_BOOLEAN);
        $phone = self::normalizePhone($this->get('cs_phone'));

        return $isActive && filled($phone);
    }

    /**
     * Save contact settings into app_settings table.
     */
    public function save(array $values): void
    {
        if (array_key_exists('cs_phone', $values)) {
            $normalized = self::normalizePhone($values['cs_phone']);
            $values['cs_phone'] = $normalized ?? (blank($values['cs_phone']) ? null : trim((string) $values['cs_phone']));
        }

        if (array_key_exists('cs_is_active', $values)) {
            $values['cs_is_active'] = filter_var($values['cs_is_active'], FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        }

        foreach (self::KEYS as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            AppSetting::query()->updateOrCreate(
                [
                    'group' => self::GROUP,
                    'key' => $key,
                ],
                [
                    'value' => blank($values[$key]) ? null : (string) $values[$key],
                ],
            );
        }
    }

    /**
     * Build the pre-formatted WhatsApp complaint message based on the customer's order.
     */
    public function buildComplaintMessage(Order $order): string
    {
        $order->load('items');

        $dateFormatted = $order->created_at instanceof Carbon
            ? $order->created_at->translatedFormat('d F Y H:i')
            : ($order->created_at ? Carbon::parse($order->created_at)->translatedFormat('d F Y H:i') : '-');

        $statusText = match ($order->status) {
            'pending_payment' => 'Menunggu Pembayaran',
            'confirmed' => 'Menunggu Pengiriman',
            'processing' => $order->shipping_status === 'shipped' ? 'Sedang Dikirim' : 'Sedang Diproses',
            'completed' => 'Pesanan Selesai',
            'expired' => 'Pembayaran Kedaluwarsa',
            'cancelled' => 'Pesanan Dibatalkan',
            default => strtoupper(str_replace('_', ' ', (string) $order->status)),
        };

        $items = $order->items;
        $productList = [];
        if ($items->isNotEmpty()) {
            foreach ($items as $item) {
                $variant = filled($item->variant_name) ? " ({$item->variant_name})" : '';
                $productList[] = "  • {$item->product_name}{$variant} ({$item->qty}x)";
            }
            $productsText = "\n" . implode("\n", $productList);
        } else {
            $productsText = " -";
        }

        $totalFormatted = 'Rp ' . number_format($order->grand_total, 0, ',', '.');
        $appName = (string) (config('services.apps.app_name') ?: config('app.name', 'Podo Raos'));
        return "Halo {$appName}, saya ingin mengajukan komplain terkait pesanan saya.\n\n"
            . "Informasi Pesanan:\n"
            . "- Nomor Pesanan: {$order->order_number}\n"
            . "- Tanggal Pesanan: {$dateFormatted}\n"
            . "- Produk:{$productsText}\n"
            . "- Total Pesanan: {$totalFormatted}\n"
            . "- Status Pesanan: {$statusText}\n\n"
            . "Kendala yang saya alami:\n"
            . "[Tulis kendala Anda di sini]\n\n"
            . "Mohon bantuannya untuk pengecekan. Terima kasih.";
    }

    /**
     * Generate a WhatsApp inquiry URL with a custom pre-filled message, or null if CS is not available.
     */
    public function getInquiryUrl(?string $customMessage = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $phone = self::normalizePhone($this->get('cs_phone'));
        if (blank($phone)) {
            return null;
        }

        $appName = (string) (config('services.apps.app_name') ?: config('app.name', 'Podo Raos'));
        $message = $customMessage ?? "Halo {$appName}, saya ingin bertanya seputar rekomendasi produk dan pesanan khusus.";

        return "https://wa.me/{$phone}?text=" . rawurlencode($message);
    }

    /**
     * Generate the complete WhatsApp URL for an order complaint or null if unavailable.
     */
    public function getComplaintUrl(Order $order): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $phone = self::normalizePhone($this->get('cs_phone'));
        if (blank($phone)) {
            return null;
        }

        $message = $this->buildComplaintMessage($order);

        return "https://wa.me/{$phone}?text=" . rawurlencode($message);
    }
}
