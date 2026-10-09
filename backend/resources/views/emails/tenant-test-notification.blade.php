<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tenant SMTP Configuration Test</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6f9;color:#1e293b;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <div style="max-width:580px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.06);border:1px solid #e2e8f0;">
        <div style="background:linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);padding:28px 24px;text-align:center;color:#ffffff;">
            <div style="font-size:13px;text-transform:uppercase;letter-spacing:1.5px;opacity:0.9;font-weight:600;margin-bottom:6px;">{{ $storeName }}</div>
            <h1 style="margin:0;font-size:22px;font-weight:700;">SMTP Email Configuration Verified</h1>
        </div>

        <div style="padding:28px 24px;">
            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#334155;">
                Hello, this is a test notification confirming that the outgoing email configuration for <strong>{{ $storeName }}</strong> is functioning properly.
            </p>

            <div style="background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;padding:16px 20px;margin-bottom:24px;">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr>
                        <td style="padding:6px 0;color:#64748b;width:35%;font-weight:600;">Tenant Store:</td>
                        <td style="padding:6px 0;color:#0f172a;font-weight:600;">{{ $storeName }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#64748b;font-weight:600;">Mail Host:</td>
                        <td style="padding:6px 0;color:#0f172a;font-family:monospace;">{{ $smtpHost }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#64748b;font-weight:600;">Mail Port:</td>
                        <td style="padding:6px 0;color:#0f172a;font-family:monospace;">{{ $smtpPort }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#64748b;font-weight:600;">From Address:</td>
                        <td style="padding:6px 0;color:#0f172a;font-family:monospace;">{{ $fromEmail }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#64748b;font-weight:600;">Tested At:</td>
                        <td style="padding:6px 0;color:#0f172a;">{{ $testedAt }}</td>
                    </tr>
                </table>
            </div>

            <p style="margin:0;font-size:13px;line-height:1.5;color:#64748b;border-top:1px solid #f1f5f9;padding-top:16px;">
                All mail sent within this tenant scope will now use these database-saved SMTP credentials instead of global .env settings.
            </p>
        </div>
    </div>
</body>
</html>
