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
    <title>404 - Page Not Found | Micro Service</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --bg-color: #090d16;
            --card-bg: rgba(17, 24, 39, 0.75);
            --border-color: rgba(56, 189, 248, 0.25);
            --text-primary: #f8fafc;
            --text-muted: #94a3b8;
            --sky-color: #38bdf8;
            --sky-glow: rgba(56, 189, 248, 0.35);
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
                radial-gradient(at 0% 0%, rgba(56, 189, 248, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(30, 27, 75, 0.25) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(15, 23, 42, 0.95) 0px, transparent 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: var(--text-primary);
        }

        .error-card {
            max-width: 560px;
            width: 100%;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 44px 38px;
            text-align: center;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.65), 0 0 45px var(--sky-glow);
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
            background: linear-gradient(90deg, #38bdf8, #818cf8, #0284c7, #38bdf8);
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
            background: rgba(56, 189, 248, 0.12);
            border: 2px solid rgba(56, 189, 248, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--sky-color);
            font-size: 38px;
            box-shadow: 0 0 30px var(--sky-glow);
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(56, 189, 248, 0.15);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.35);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        h1 {
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: 12px;
            color: #ffffff;
        }

        .subtitle {
            font-size: 15px;
            line-height: 1.6;
            color: var(--text-muted);
            margin-bottom: 28px;
            max-width: 460px;
            margin-left: auto;
            margin-right: auto;
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
            background: linear-gradient(135deg, #0284c7, #0369a1);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(2, 132, 199, 0.4);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #0369a1, #075985);
            transform: translateY(-1px);
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
    </style>
</head>
<body>
    <div class="error-card">
        <div class="icon-container">
            <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76" fill="rgba(56, 189, 248, 0.25)"></polygon>
            </svg>
        </div>

        <div class="badge-status">
            <i class="fa-solid fa-magnifying-glass"></i> Error 404
        </div>

        <h1>Page Not Found</h1>
        <p class="subtitle">
            The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.
        </p>

        <div class="actions-row">
            <a href="/" class="btn btn-primary">
                <i class="fa-solid fa-house"></i> Home
            </a>
            <button onclick="window.history.back()" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Go Back
            </button>
        </div>

        <p class="footer-text">
            Double check the URL or return to the main dashboard navigation.
        </p>
    </div>
</body>
</html>