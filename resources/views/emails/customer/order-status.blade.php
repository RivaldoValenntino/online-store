<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>

<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:'Segoe UI', Arial, sans-serif; color:#1f2937;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:32px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0"
                    style="max-width:580px; background-color:#ffffff; border-radius:24px; overflow:hidden; box-shadow:0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    
                    {{-- Header Branding --}}
                    <tr>
                        <td style="padding:28px 32px 20px; background-color:#ffffff; border-bottom:1px solid #f3f4f6;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <h2 style="margin:0; font-size:20px; font-weight:900; color:#111827;">
                                            {{ config('app.name') }}
                                        </h2>
                                    </td>
                                    <td align="right">
                                        <span style="display:inline-block; padding:6px 12px; font-size:12px; font-weight:bold; border-radius:999px; background-color:#eff6ff; color:#2563eb;">
                                            #{{ $order->order_number }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Main Content --}}
                    <tr>
                        <td style="padding:32px 32px 24px;">
                            <h1 style="margin:0 0 12px; font-size:22px; font-weight:800; color:#111827;">
                                {{ $title }}
                            </h1>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#4b5563;">
                                Halo <strong>{{ $customer->name ?? $order->recipient_name }}</strong>,
                            </p>

                            <p style="margin:0 0 24px; font-size:15px; line-height:1.6; color:#4b5563;">
                                {{ $messageText }}
                            </p>

                            {{-- Order Info Box --}}
                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="background-color:#f9fafb; border-radius:16px; padding:20px; margin-bottom:24px;">
                                <tr>
                                    <td style="padding:6px 0; font-size:13px; color:#6b7280;">No. Pesanan:</td>
                                    <td align="right" style="padding:6px 0; font-size:13px; font-weight:bold; color:#111827;">
                                        {{ $order->order_number }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0; font-size:13px; color:#6b7280;">Tanggal:</td>
                                    <td align="right" style="padding:6px 0; font-size:13px; font-weight:600; color:#111827;">
                                        {{ $order->created_at->format('d M Y, H:i') }} WIB
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0; font-size:13px; color:#6b7280;">Penerima:</td>
                                    <td align="right" style="padding:6px 0; font-size:13px; font-weight:600; color:#111827;">
                                        {{ $order->recipient_name }} ({{ $order->recipient_phone }})
                                    </td>
                                </tr>
                                @if ($order->shipping_courier)
                                <tr>
                                    <td style="padding:6px 0; font-size:13px; color:#6b7280;">Pengiriman:</td>
                                    <td align="right" style="padding:6px 0; font-size:13px; font-weight:600; color:#111827;">
                                        {{ strtoupper($order->shipping_courier) }} {{ $order->shipping_service ? '- ' . $order->shipping_service : '' }}
                                    </td>
                                </tr>
                                @endif
                                @if ($order->tracking_number)
                                <tr>
                                    <td style="padding:6px 0; font-size:13px; color:#6b7280;">No. Resi:</td>
                                    <td align="right" style="padding:6px 0; font-size:13px; font-weight:bold; color:#2563eb;">
                                        {{ $order->tracking_number }}
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding:10px 0 0; font-size:14px; font-weight:bold; color:#111827; border-top:1px dashed #e5e7eb;">Total Pembayaran:</td>
                                    <td align="right" style="padding:10px 0 0; font-size:16px; font-weight:900; color:#2563eb; border-top:1px dashed #e5e7eb;">
                                        Rp {{ number_format($order->grand_total, 0, ',', '.') }}
                                    </td>
                                </tr>
                            </table>

                            {{-- Items Summary if available --}}
                            @if ($order->relationLoaded('items') && $order->items->count() > 0)
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                    <tr>
                                        <td colspan="2" style="padding-bottom:8px; font-size:13px; font-weight:bold; color:#374151;">
                                            Produk yang Dipesan:
                                        </td>
                                    </tr>
                                    @foreach ($order->items as $item)
                                        <tr>
                                            <td style="padding:6px 0; font-size:13px; color:#4b5563;">
                                                {{ $item->product_name }} {{ $item->variant_name ? '(' . $item->variant_name . ')' : '' }} &times; {{ $item->qty }}
                                            </td>
                                            <td align="right" style="padding:6px 0; font-size:13px; font-weight:600; color:#111827;">
                                                Rp {{ number_format($item->subtotal ?: ($item->price * $item->qty), 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            {{-- Button CTA --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $actionUrl }}"
                                            style="display:inline-block; background-color:#2563eb; color:#ffffff; text-decoration:none; padding:14px 28px; border-radius:999px; font-size:14px; font-weight:bold; letter-spacing:0.3px;">
                                            Lihat Detail Pesanan
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:28px 0 0; font-size:13px; line-height:1.6; color:#9ca3af; text-align:center;">
                                Jika ada pertanyaan mengenai pesanan ini, kamu bisa membalas email ini atau menghubungi customer service kami.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 32px 28px; background-color:#f9fafb; border-top:1px solid #f3f4f6; text-align:center;">
                            <p style="margin:0; font-size:12px; color:#9ca3af;">
                                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
