<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email verification</title>
</head>
<body style="margin:0;background:#f8fafc;color:#0f172a;font-family:Arial,sans-serif">
    <div style="max-width:560px;margin:0 auto;padding:32px 18px">
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:32px;text-align:center">
            <div style="color:#ff762d;font-size:14px;font-weight:700">{{ $storeName }}</div>
            <h1 style="margin:14px 0 8px;font-size:24px">Verify your email</h1>
            <p style="margin:0;color:#64748b;line-height:1.6">Enter this code to finish creating your customer account.</p>
            <div style="margin:26px 0;padding:16px;border-radius:12px;background:#fff1e8;color:#c2410c;font-size:34px;font-weight:700;letter-spacing:8px">{{ $code }}</div>
            <p style="margin:0;color:#64748b;font-size:13px">This code expires in {{ $expiresInMinutes }} minutes. If you did not request it, you can ignore this email.</p>
        </div>
    </div>
</body>
</html>
