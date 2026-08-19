<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SIMASTER')</title>
    {{-- Styling brand SIMASTER: palet 1 aksen + skala netral status, font Archivo/Inter --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css" rel="stylesheet">
    <link href="{{ asset('css/simaster.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand bg-white border-bottom mb-3">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold" href="{{ url('/dashboard') }}">SIMASTER</a>
            <ul class="navbar-nav me-auto flex-row gap-3">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active fw-semibold' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('po-hq.*') ? 'active fw-semibold' : '' }}" href="{{ route('po-hq.index') }}">PO HQ</a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                @auth
                    <span class="text-body-secondary small">
                        {{ auth()->user()->name }}
                        <span class="badge text-bg-secondary">{{ auth()->user()->roles->pluck('name')->implode(', ') ?: 'tanpa role' }}</span>
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Logout</button>
                    </form>
                @endauth
            </div>
        </div>
    </nav>

    <main class="container-fluid px-4">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @yield('content')
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
    @stack('scripts')
</body>
</html>
