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
    <title>403 - Access Denied by Security Firewall</title>
    @include('errors.partials.status-styles')
</head>
<body>
    <main class="container">
        <h1>Error 403</h1>
        <h2 class="subtitle">Access Denied by Security Firewall</h2>
        <p class="description">{{ $reason ?? 'You do not have permission to access this page or the request was blocked by the security firewall.' }}</p>
        <div class="actions">
            <a href="/" class="btn">Home</a>
            <button onclick="window.history.length > 1 ? window.history.back() : window.location.href='/'" class="btn">Go Back</button>
        </div>
        <p class="footer-text">
            Return to the main dashboard or contact an administrator if you need access.
            @if(!empty($incidentId))
                Reference ID: {{ $incidentId }}
            @endif
        </p>
    </main>
</body>
</html>
