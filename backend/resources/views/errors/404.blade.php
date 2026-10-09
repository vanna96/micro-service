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
    <title>Error 404 - Page Not Found</title>
    @include('errors.partials.status-styles')
</head>
<body>
    <main class="container">
        <h1>Error 404</h1>
        <h2 class="subtitle">Page Not Found</h2>
        <p class="description">The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
        <div class="actions">
            <a href="/" class="btn">Home</a>
            <button onclick="window.history.length > 1 ? window.history.back() : window.location.href='/'" class="btn">Go Back</button>
        </div>
        <p class="footer-text">Double check the URL or return to the main dashboard navigation.</p>
    </main>
</body>
</html>
