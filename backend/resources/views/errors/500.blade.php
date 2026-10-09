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
    <title>500 - Internal Server Error</title>
    @include('errors.partials.status-styles')
</head>
<body>
    <main class="container">
        <h1>Error 500</h1>
        <h2 class="subtitle">Internal Server Error</h2>
        <p class="description">An unexpected error occurred while processing your request. Please try again shortly.</p>
        <div class="actions">
            <button onclick="window.location.reload()" class="btn">Try Again</button>
            <a href="/" class="btn">Home</a>
        </div>
        <p class="footer-text">If the problem continues, contact support.</p>
    </main>
</body>
</html>
