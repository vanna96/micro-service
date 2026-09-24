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
    <title>403 - Access Denied | Security Firewall</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --bg-color: #090d16;
            --card-bg: rgba(17, 24, 39, 0.75);
            --border-color: rgba(239, 68, 68, 0.28);
            --text-primary: #f8fafc;
            --text-muted: #94a3b8;
            --danger-color: #ef4444;
            --danger-light: #f87171;
            --danger-glow: rgba(239, 68, 68, 0.35);
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
                radial-gradient(at 0% 0%, rgba(239, 68, 68, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(30, 27, 75, 0.3) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(15, 23, 42, 0.95) 0px, transparent 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: var(--text-primary);
        }

        .security-container {
            max-width: 580px;
            width: 100%;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 44px 38px;
            text-align: center;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.65), 0 0 45px var(--danger-glow);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .security-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ef4444, #f97316, #dc2626, #ef4444);
            background-size: 300% 100%;
            animation: gradientMove 4s ease infinite;
        }

        @keyframes gradientMove {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .icon-wrapper {
            width: 92px;
            height: 92px;
            margin: 0 auto 26px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.12);
            border: 2px solid rgba(239, 68, 68, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--danger-light);
            font-size: 38px;
            box-shadow: 0 0 30px var(--danger-glow);
            animation: pulse-ring 2.5s infinite;
        }

        @keyframes pulse-ring {
            0% {
                transform: scale(0.96);
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.5);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 18px rgba(239, 68, 68, 0);
            }
            100% {
                transform: scale(0.96);
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.35);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        h1 {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: 12px;
            color: #ffffff;
        }

        .lead-text {
            font-size: 15px;
            line-height: 1.6;
            color: var(--text-muted);
            margin-bottom: 26px;
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
        }

        .details-card {
            background: rgba(15, 23, 42, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 16px 20px;
            text-align: left;
            margin-bottom: 28px;
            font-size: 13.5px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .detail-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .detail-row:first-child {
            padding-top: 0;
        }

        .detail-label {
            color: var(--text-muted);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-value {
            color: #e2e8f0;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            font-size: 12.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .incident-badge {
            background: rgba(239, 68, 68, 0.12);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 3px 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .incident-badge:hover {
            background: rgba(239, 68, 68, 0.22);
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

        .actions {
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
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.5);
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

        .footer-note {
            margin-top: 24px;
            font-size: 12px;
            color: #64748b;
        }

        .toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1e293b;
            color: #f8fafc;
            border: 1px solid rgba(239, 68, 68, 0.4);
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
    <div class="security-container">
        <div class="icon-wrapper">
            <i class="fa-solid fa-shield-halved"></i>
        </div>

        <div class="badge-status">
            <i class="fa-solid fa-ban"></i> Access Forbidden
        </div>

        <h1>Access Denied by Security Firewall</h1>
        <p class="lead-text">
            {{ $reason ?? 'Your request has been intercepted and blocked by the platform security inspection engine.' }}
        </p>

        <div class="details-card">
            <div class="detail-row">
                <span class="detail-label">
                    <i class="fa-solid fa-fingerprint text-danger"></i> Incident ID
                </span>
                <span class="detail-value">
                    <span class="incident-badge" onclick="copyIncidentId()" title="Click to copy Incident ID">
                        <span id="incident-text">{{ $incidentId ?? 'SEC-' . strtoupper(substr(md5(time()), 0, 8)) }}</span>
                        <button type="button" class="btn-copy"><i class="fa-regular fa-copy"></i></button>
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">
                    <i class="fa-solid fa-network-wired text-muted"></i> Client IP
                </span>
                <span class="detail-value">{{ $ip ?? request()->ip() }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">
                    <i class="fa-solid fa-clock text-muted"></i> Timestamp
                </span>
                <span class="detail-value">{{ now()->toIso8601String() }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">
                    <i class="fa-solid fa-shield-virus text-muted"></i> Defense Layer
                </span>
                <span class="detail-value" style="color: #f87171;">WAF-L7-Active</span>
            </div>
        </div>

        <div class="actions">
            <a href="/" class="btn btn-primary">
                <i class="fa-solid fa-house"></i> Home
            </a>
            <button onclick="window.history.back()" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Go Back
            </button>
        </div>

        <p class="footer-note">
            If you believe this is a false positive, please provide your Incident ID to technical support.
        </p>
    </div>

    <div id="toast" class="toast">
        <i class="fa-solid fa-circle-check text-danger"></i> Incident ID copied to clipboard!
    </div>

    <script>
        function copyIncidentId() {
            const text = document.getElementById('incident-text').innerText.trim();
            navigator.clipboard.writeText(text).then(() => {
                showToast();
            }).catch(() => {
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
