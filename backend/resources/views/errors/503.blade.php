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
    <title>503 - Service Unavailable</title>
    @include('errors.partials.status-styles')
</head>
<body>
    <main class="container">
        <h1>Error 503</h1>
        <h2 class="subtitle">Service Temporarily Unavailable</h2>
        <p class="description">Our systems are currently undergoing scheduled maintenance. Please check back shortly.</p>
        <div class="actions">
            <button onclick="window.location.reload()" class="btn">Refresh Page</button>
            <a href="/" class="btn">Home</a>
        </div>
        <p class="footer-text">Auto-refreshing in <span id="countdown">15</span>s.</p>
    </main>

    <script>
        (function() {
            let sec = 15;
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
