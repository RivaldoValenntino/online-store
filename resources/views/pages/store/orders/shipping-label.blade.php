<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Label Pengiriman {{ $order->order_number }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            padding: 16px;
            color: #111;
            background: #f5f5f5;
        }

        .label {
            max-width: 420px;
            margin: 0 auto;
            background: #fff;
            border: 2px solid #111;
            padding: 16px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .title {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .muted {
            color: #555;
            font-size: 12px;
        }

        .section {
            border-top: 1px dashed #111;
            margin-top: 12px;
            padding-top: 12px;
        }

        .big {
            font-size: 22px;
            font-weight: 900;
        }

        .small {
            font-size: 12px;
        }

        .items {
            margin: 0;
            padding-left: 18px;
        }

        .print-btn {
            display: block;
            width: 100%;
            max-width: 420px;
            margin: 12px auto;
            padding: 12px;
            border: 0;
            border-radius: 999px;
            background: #111;
            color: white;
            font-weight: 700;
            cursor: pointer;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .print-btn {
                display: none;
            }

            .label {
                border: 2px solid #111;
                max-width: none;
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <button onclick="window.print()" class="print-btn">
        Cetak Label / Resi
    </button>

    <div class="label">
        <div class="row">
            <div>
                <div class="title">LABEL PENGIRIMAN</div>
                <div class="muted">{{ config('app.name') }}</div>
            </div>

            <div style="text-align: right;">
                <div class="muted">Order</div>
                <div><strong>{{ $order->order_number }}</strong></div>
            </div>
        </div>

        <div class="section">
            <div class="muted">Ekspedisi</div>
            <div class="big">
                {{ strtoupper($order->shipping_courier ?? '-') }}
                {{ $order->shipping_service }}
            </div>

            <div class="small">
                Estimasi: {{ $order->shipping_etd ?: '-' }}
            </div>

            <div class="small">
                No Resi/AWB:
                <strong>{{ $order->awb_number ?: 'Belum tersedia' }}</strong>
            </div>
        </div>

        <div class="section">
            <div class="muted">Penerima</div>
            <div><strong>{{ $order->recipient_name }}</strong></div>
            <div>{{ $order->recipient_phone }}</div>
            <div class="small" style="margin-top: 6px;">
                {{ $order->shipping_address }}
            </div>
        </div>

        <div class="section">
            <div class="muted">Isi Paket</div>
            <ul class="items">
                @foreach ($order->items as $item)
                    <li class="small">
                        {{ $item->product_name }}
                        @if ($item->variant_name)
                            - {{ $item->variant_name }}
                        @endif
                        x{{ $item->qty }}
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="section row">
            <div>
                <div class="muted">Ongkir</div>
                <strong>Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</strong>
            </div>

            <div style="text-align: right;">
                <div class="muted">Status Bayar</div>
                <strong>{{ strtoupper($order->payment_status) }}</strong>
            </div>
        </div>
    </div>
</body>

</html>
