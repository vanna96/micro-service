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
    <title>429 - Rate Limit Exceeded | Platform Security</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --bg-color: #090d16;
            --card-bg: rgba(17, 24, 39, 0.75);
            --border-color: rgba(245, 158, 11, 0.28);
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
                radial-gradient(at 0% 0%, rgba(245, 158, 11, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(30, 27, 75, 0.25) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(15, 23, 42, 0.95) 0px, transparent 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: var(--text-primary);
        }

        .rate-limit-card {
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

        .rate-limit-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #f59e0b, #fbbf24, #d97706, #f59e0b);
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
            color: var(--amber-light);
            font-size: 38px;
            box-shadow: 0 0 30px var(--amber-glow);
            animation: pulse-ring 2.5s infinite;
        }

        @keyframes pulse-ring {
            0% {
                transform: scale(0.96);
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 18px rgba(245, 158, 11, 0);
            }
            100% {
                transform: scale(0.96);
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
            }
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.35);
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
            margin-bottom: 24px;
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Countdown Container */
        .countdown-box {
            background: rgba(15, 23, 42, 0.7);
            border: 1px dashed rgba(245, 158, 11, 0.35);
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
        }

        .countdown-circle {
            position: relative;
            width: 54px;
            height: 54px;
        }

        .countdown-circle svg {
            width: 54px;
            height: 54px;
            transform: rotate(-90deg);
        }

        .countdown-circle circle {
            fill: none;
            stroke-width: 4;
        }

        .circle-bg {
            stroke: rgba(255, 255, 255, 0.1);
        }

        .circle-progress {
            stroke: var(--amber-light);
            stroke-dasharray: 140;
            stroke-dashoffset: 0;
            stroke-linecap: round;
            transition: stroke-dashoffset 1s linear;
        }

        .countdown-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-family: 'JetBrains Mono', monospace;
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
        }

        .countdown-info {
            text-align: left;
        }

        .countdown-info strong {
            display: block;
            font-size: 14px;
            color: #f1f5f9;
            margin-bottom: 2px;
        }

        .countdown-info span {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Forensic Details */
        .forensics-card {
            background: rgba(15, 23, 42, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 28px;
            text-align: left;
            font-size: 13.5px;
        }

        .forensic-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .forensic-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .forensic-row:first-child {
            padding-top: 0;
        }

        .forensic-label {
            color: var(--text-muted);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .forensic-value {
            color: #e2e8f0;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            font-size: 12.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .incident-badge {
            background: rgba(245, 158, 11, 0.12);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
            padding: 3px 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .incident-badge:hover {
            background: rgba(245, 158, 11, 0.22);
            color: #ffffff;
        }

        .btn-copy {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 11px;
            padding-left: 4px;
        }

        /* Actions */
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
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #d97706, #b45309);
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

        /* Toast notification */
        .toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1e293b;
            color: #f8fafc;
            border: 1px solid rgba(245, 158, 11, 0.4);
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 100;
        }

        .toast.show {
            transform: translateX(-50%) translateY(0);
        }
    </style>
</head>
<body>
    <div class="rate-limit-card">
        <div class="icon-container">
            <i class="fa-solid fa-gauge-high"></i>
        </div>

        <div class="badge-status">
            <i class="fa-solid fa-shield-halved"></i> Rate Limit Active
        </div>

        <h1>429 Too Many Requests</h1>
        <p class="subtitle">
            You have temporarily exceeded the allowed request threshold. Our automated protection firewall has paused new requests to preserve system reliability.
        </p>

        {{-- Interactive Countdown Box --}}
        <div class="countdown-box">
            <div class="countdown-circle">
                <svg>
                    <circle class="circle-bg" cx="27" cy="27" r="22"></circle>
                    <circle id="progress-circle" class="circle-progress" cx="27" cy="27" r="22"></circle>
                </svg>
                <div class="countdown-text" id="countdown-number">{{ $retryAfter ?? 60 }}</div>
            </div>
            <div class="countdown-info">
                <strong>Automatic Cooldown in Progress</strong>
                <span>Page will be eligible to retry once the timer expires.</span>
            </div>
        </div>

        {{-- Forensics Card --}}
        <div class="forensics-card">
            <div class="forensic-row">
                <span class="forensic-label">
                    <i class="fa-solid fa-fingerprint text-warning"></i> Incident ID
                </span>
                <span class="forensic-value">
                    <span class="incident-badge" onclick="copyIncidentId()" title="Click to copy Incident ID">
                        <span id="incident-text">{{ $incidentId ?? 'SEC-' . strtoupper(substr(md5(time()), 0, 8)) }}</span>
                        <button type="button" class="btn-copy"><i class="fa-regular fa-copy"></i></button>
                    </span>
                </span>
            </div>
            <div class="forensic-row">
                <span class="forensic-label">
                    <i class="fa-solid fa-network-wired text-muted"></i> Client IP
                </span>
                <span class="forensic-value">{{ $ip ?? request()->ip() }}</span>
            </div>
            <div class="forensic-row">
                <span class="forensic-label">
                    <i class="fa-solid fa-clock-rotate-left text-muted"></i> Cooldown Window
                </span>
                <span class="forensic-value">{{ $retryAfter ?? 60 }} seconds</span>
            </div>
            <div class="forensic-row">
                <span class="forensic-label">
                    <i class="fa-solid fa-shield-virus text-muted"></i> Defense Layer
                </span>
                <span class="forensic-value" style="color: #fbbf24;">DDoS & Rate Mitigation</span>
            </div>
        </div>

        {{-- Actions --}}
        <div class="actions-row">
            <button id="btn-reload" onclick="handleRetry()" class="btn btn-primary">
                <i class="fa-solid fa-rotate-right"></i> Try Again Now
            </button>
            <a href="/" class="btn btn-secondary">
                <i class="fa-solid fa-house"></i> Home
            </a>
        </div>

        <p class="footer-text">
            If you need higher API throughput or believe this is in error, please quote your Incident ID to technical support.
        </p>
    </div>

    {{-- Toast notification --}}
    <div id="toast" class="toast">
        <i class="fa-solid fa-circle-check text-warning"></i> Incident ID copied to clipboard!
    </div>

    <script>
        let totalSeconds = parseInt('{{ $retryAfter ?? 60 }}', 10) || 60;
        let remainingSeconds = totalSeconds;
        const numberEl = document.getElementById('countdown-number');
        const circleEl = document.getElementById('progress-circle');
        const maxCircumference = 2 * Math.PI * 22; // approx 138.23

        circleEl.style.strokeDasharray = maxCircumference;

        const countdownInterval = setInterval(() => {
            remainingSeconds--;
            if (remainingSeconds <= 0) {
                clearInterval(countdownInterval);
                numberEl.textContent = '0';
                circleEl.style.strokeDashoffset = maxCircumference;
                const btnReload = document.getElementById('btn-reload');
                btnReload.innerHTML = '<i class="fa-solid fa-circle-check"></i> Ready! Reloading...';
                setTimeout(() => {
                    window.location.reload();
                }, 800);
            } else {
                numberEl.textContent = remainingSeconds;
                const progress = (totalSeconds - remainingSeconds) / totalSeconds;
                circleEl.style.strokeDashoffset = progress * maxCircumference;
            }
        }, 1000);

        function handleRetry() {
            window.location.reload();
        }

        function copyIncidentId() {
            const text = document.getElementById('incident-text').innerText.trim();
            navigator.clipboard.writeText(text).then(() => {
                showToast();
            }).catch(() => {
                // Fallback for non-https/legacy
                const el = document.createElement('textarea');
                el.value = text;
                document.body.appendChild(el);
                el.select();
                document.execCommand('copy');
                document.body.removeChild(el);
                showToast();
            });
        }

        function showToast() {
            const toast = document.getElementById('toast');
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 2500);
        }
    </script>
</body>
</html>
