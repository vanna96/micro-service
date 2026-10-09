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
    <title>429 - Too Many Requests</title>
    @include('errors.partials.status-styles')
</head>
<body>
    <main class="container">
        <h1>Error 429</h1>
        <h2 class="subtitle">Too Many Requests</h2>
        <p class="description">You've made too many requests in a short time. Please wait a moment before trying again.</p>
        <div class="actions">
            <button onclick="window.location.reload()" class="btn">Try Again</button>
            <a href="/" class="btn">Home</a>
        </div>
        <p class="footer-text">
            Auto-refreshing in <span id="countdown">{{ $retryAfter ?? 60 }}</span>s.
            @if(!empty($incidentId))
                Reference ID: {{ $incidentId }}
            @endif
        </p>
    </main>

    <script>
        (function() {
            let sec = parseInt('{{ $retryAfter ?? 60 }}', 10) || 60;
            const el = document.getElementById('countdown');
            const timer = setInterval(function() {
                sec--;
                if (el) el.textContent = sec;
                if (sec <= 0) {
                    clearInterval(timer);
                    window.location.reload();
                }
            }, 1000);
        })();
    </script>
</body>
</html>
