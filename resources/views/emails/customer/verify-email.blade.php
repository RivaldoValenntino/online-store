<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Verifikasi Email</title>
</head>

<body style="margin:0; padding:0; background:#f3f4f6; font-family:Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:32px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0"
                    style="max-width:560px; background:#ffffff; border-radius:20px; overflow:hidden;">
                    <tr>
                        <td style="padding:28px 28px 12px;">
                            <h1 style="margin:0; font-size:24px; color:#111827;">
                                Verifikasi Email Kamu
                            </h1>

                            <p style="margin:16px 0 0; font-size:15px; line-height:1.6; color:#4b5563;">
                                Halo {{ $customer->name ?? 'Customer' }},
                            </p>

                            <p style="margin:12px 0 0; font-size:15px; line-height:1.6; color:#4b5563;">
                                Terima kasih sudah mendaftar. Klik tombol di bawah ini untuk mengaktifkan akun kamu.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:24px 28px;">
                            <a href="{{ $verificationUrl }}"
                                style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none; padding:14px 24px; border-radius:999px; font-size:15px; font-weight:bold;">
                                Verifikasi Email
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 28px;">
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#6b7280;">
                                Kalau tombol tidak bisa diklik, salin link berikut ke browser:
                            </p>

                            <p
                                style="word-break:break-all; margin:8px 0 0; font-size:12px; line-height:1.6; color:#2563eb;">
                                {{ $verificationUrl }}
                            </p>

                            <p style="margin:20px 0 0; font-size:13px; color:#9ca3af;">
                                Link ini akan kedaluwarsa dalam 60 menit.
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin:16px 0 0; font-size:12px; color:#9ca3af;">
                    © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                </p>
            </td>
        </tr>
    </table>
</body>

</html>
