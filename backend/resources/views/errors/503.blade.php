@php
    if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class)) {
        \Barryvdh\Debugbar\Facades\Debugbar::disable();
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 - Service Unavailable | System Maintenance</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --bg-color: #090d16;
            --card-bg: rgba(17, 24, 39, 0.78);
            --border-color: rgba(245, 158, 11, 0.3);
            --text-primary: #f8fafc;
            --text-muted: #94a3b8;
            --amber-color: #f59e0b;
            --amber-light: #fbbf24;
            --amber-glow: rgba(245, 158, 11, 0.35);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        }

        body {
            background-color: var(--bg-color);
            background-image:
                radial-gradient(at 0% 0%, rgba(245, 158, 11, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(30, 27, 75, 0.3) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(15, 23, 42, 0.95) 0px, transparent 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: var(--text-primary);
        }

        .error-card {
            max-width: 580px;
            width: 100%;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 44px 38px;
            text-align: center;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.65), 0 0 45px var(--amber-glow);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .error-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #f59e0b, #fbbf24, #f97316, #f59e0b);
            background-size: 300% 100%;
            animation: gradientMove 4s ease infinite;
        }

        @keyframes gradientMove {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .icon-container {
            width: 92px;
            height: 92px;
            margin: 0 auto 26px;
            border-radius: 50%;
            background: rgba(245, 158, 11, 0.12);
            border: 2px solid rgba(245, 158, 11, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--amber-color);
            font-size: 38px;
            box-shadow: 0 0 30px var(--amber-glow);
            animation: gearRotate 12s linear infinite;
        }

        @keyframes gearRotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(245, 158, 11, 0.15);
            color: var(--amber-light);
            border: 1px solid rgba(245, 158, 11, 0.35);
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--amber-light);
            box-shadow: 0 0 8px var(--amber-light);
            animation: pulseDot 1.8s ease-in-out infinite;
        }

        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.35; transform: scale(0.75); }
        }

        h1 {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: 12px;
            color: #ffffff;
            line-height: 1.25;
        }

        .khmer-title {
            font-family: 'Kantumruy Pro', sans-serif;
            font-size: 18px;
            font-weight: 600;
            color: var(--amber-light);
            margin-bottom: 14px;
            display: block;
        }

        .subtitle {
            font-size: 15px;
            line-height: 1.6;
            color: var(--text-muted);
            margin-bottom: 24px;
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
        }

        .timer-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 26px;
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .timer-val {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            color: var(--amber-light);
            font-size: 14px;
        }

        .actions-row {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #0f172a;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.14);
            color: #ffffff;
        }

        .footer-text {
            margin-top: 24px;
            font-size: 12px;
            color: #64748b;
        }

        @media (max-width: 600px) {
            .error-card {
                padding: 36px 20px;
            }
            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="icon-container">
            <i class="fa-solid fa-screwdriver-wrench"></i>
        </div>

        <div class="badge-status">
            <span class="pulse-dot"></span>
            Status 503 • Maintenance Mode
        </div>

        <h1>Service Temporarily Unavailable</h1>
        <span class="khmer-title">សេវាកម្មមិនទាន់ដំណើរការបណ្តោះអាសន្ន</span>

        <p class="subtitle">
            Our systems are currently undergoing scheduled maintenance or experiencing elevated demand. We are working hard to restore full operation shortly.
        </p>

        <div class="timer-container">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>Auto-refreshing in <span id="countdown" class="timer-val">15</span>s</span>
        </div>

        <div class="actions-row">
            <button onclick="window.location.reload()" class="btn btn-primary">
                <i class="fa-solid fa-rotate-right"></i> Refresh Now
            </button>
            <a href="/" class="btn btn-secondary">
                <i class="fa-solid fa-house"></i> Return Home
            </a>
        </div>

        <p class="footer-text">
            Automated system health checks are ongoing. Thank you for your patience.
        </p>
    </div>

    <script>
        (function() {
            let seconds = 15;
            const el = document.getElementById('countdown');
            const timer = setInterval(function() {
                seconds--;
                if (el) el.textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(timer);
                    window.location.reload();
                }
            }, 1000);
        })();
    </script>
</body>
</html>
