<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        body {
            font-family: sans-serif;
            color: #222;
            margin: 40px;
        }

        .wrap {
            max-width: 900px;
            margin: auto;
        }

        h1 {
            margin-bottom: 0;
        }

        .muted {
            color: #666;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
        }

        th,
        td {
            border-bottom: 1px solid #ddd;
            padding: 10px 8px;
            text-align: left;
        }

        .right {
            text-align: right;
        }

        .summary {
            margin-top: 24px;
            width: 320px;
            margin-left: auto;
        }

        .summary td {
            border: none;
            padding: 6px 0;
        }
    </style>
</head>

<body>
    <div class="wrap">
        <h1>Invoice</h1>
        <p class="muted">{{ $order->order_number }}</p>

        <p>
            <strong>Penerima:</strong> {{ $order->recipient_name }}<br>
            <strong>Telepon:</strong> {{ $order->recipient_phone }}<br>
            <strong>Alamat:</strong> {{ $order->shipping_address }}
        </p>

        <table>
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Varian</th>
                    <th>Qty</th>
                    <th class="right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->variant_name ?: '-' }}</td>
                        <td>{{ $item->qty }}</td>
                        <td class="right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="summary">
            <tr>
                <td>Subtotal</td>
                <td class="right">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Ongkir</td>
                <td class="right">Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Diskon</td>
                <td class="right">Rp {{ number_format($order->discount, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td><strong>Total</strong></td>
                <td class="right"><strong>Rp {{ number_format($order->grand_total, 0, ',', '.') }}</strong></td>
            </tr>
        </table>
    </div>
</body>

</html>
