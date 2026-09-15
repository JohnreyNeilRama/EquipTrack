{{-- Shell for public pages: landing, login, registration --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="view-transition" content="same-origin">
    <title>@yield('title', 'EquipTrack')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo_only.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo_only.png') }}">
    @stack('css')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    @yield('content')
    @stack('scripts')
</body>
</html>
