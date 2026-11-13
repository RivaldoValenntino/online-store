<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $campaign->subject ?? $campaign->title }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f7f9fa;
            color: #2d3748;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            padding: 30px 24px;
            text-align: center;
            color: #ffffff;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .header p {
            margin: 6px 0 0 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .content {
            padding: 30px 24px;
            line-height: 1.6;
        }

        .greeting {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .body-text {
            color: #4a5568;
            font-size: 15px;
            margin-bottom: 24px;
            white-space: pre-line;
        }

        .voucher-box {
            background-color: #eff6ff;
            border: 2px dashed #3b82f6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin: 24px 0;
        }

        .voucher-code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 26px;
            font-weight: 800;
            color: #1d4ed8;
            letter-spacing: 2px;
            margin: 8px 0;
        }

        .voucher-desc {
            font-size: 13px;
            color: #64748b;
        }

        .cta-btn {
            display: block;
            width: fit-content;
            margin: 28px auto 10px auto;
            background-color: #4f46e5;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            text-align: center;
        }

        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }

        .footer a {
            color: #64748b;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('services.apps.app_name') ?: config('app.name', 'Podo Raos') }}</h1>
            <p>Oleh-Oleh Khas & Dodol Istimewa</p>
        </div>

        <div class="content">
            <div class="greeting">Halo, {{ $customer->name }}! 👋</div>

            <div class="body-text">
                {{ $campaign->content }}
            </div>

            @if ($campaign->voucher)
                <div class="voucher-box">
                    <div
                        style="font-size: 13px; font-weight: 600; text-transform: uppercase; color: #3b82f6; letter-spacing: 1px;">
                        KODE VOUCHER SPESIAL</div>
                    <div class="voucher-code">{{ $campaign->voucher->code }}</div>
                    <div class="voucher-desc">
                        {{ $campaign->voucher->name }} • Diskon {{ $campaign->voucher->formattedDiscount() }}
                        @if ($campaign->voucher->min_spend > 0)
                            <br>(Min. belanja Rp {{ number_format($campaign->voucher->min_spend, 0, ',', '.') }})
                        @endif
                        @if ($campaign->voucher->expires_at)
                            <br>Berlaku s/d {{ $campaign->voucher->expires_at->format('d M Y H:i') }}
                        @endif
                    </div>
                </div>
            @endif

            @php
                $targetUrl = $campaign->action_url ?: url('/promos');
            @endphp
            <a href="{{ $targetUrl }}" class="cta-btn">Klaim Promo Sekarang &rarr;</a>
        </div>

        <div class="footer">
            <p>Email ini dikirimkan ke <strong>{{ $customer->email }}</strong> karena Anda terdaftar di
                {{ config('services.apps.app_name') ?: config('app.name', 'Podo Raos') }}.</p>
            <p>Jika Anda tidak ingin menerima email promo lagi di masa mendatang, silakan <a
                    href="{{ route('promos.unsubscribe', ['token' => base64_encode($customer->id . '|' . md5($customer->email . config('app.key')))]) }}">berhenti
                    berlangganan (unsubscribe)</a>.</p>
            <p>&copy; {{ date('Y') }} {{ config('services.apps.app_name') ?: config('app.name', 'Podo Raos') }}.
                Seluruh hak cipta dilindungi.</p>
        </div>
    </div>
</body>

</html>
