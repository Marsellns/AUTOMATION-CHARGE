<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#143c34">
    <title>Masuk — SIMAWAR</title>
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">
</head>
<body class="simawar-auth-page">
    <main class="simawar-auth-shell">
        @include('auth.partials.login-hero')

        <section class="simawar-auth-form-side" aria-labelledby="login-title">
            <div class="simawar-auth-form-wrap">
                <div class="simawar-auth-index"><span>AKSES SISTEM</span><span>SIMAWAR / 01</span></div>
                <div class="simawar-auth-intro">
                    <p class="simawar-auth-kicker">PORTAL INTERNAL</p>
                    <h1 id="login-title">Selamat datang kembali.</h1>
                    <p>Masuk dengan akun yang telah terdaftar untuk melanjutkan pekerjaan Anda.</p>
                </div>

                @if ($errors->any())
                    <div class="simawar-auth-alert simawar-auth-alert-error" role="alert">{{ $errors->first() }}</div>
                @endif
                @if (session('status'))
                    <div class="simawar-auth-alert simawar-auth-alert-success" role="status">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="simawar-auth-form">
                    @csrf
                    <div class="simawar-auth-field">
                        <label for="email">Email akun</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                               autocomplete="username" inputmode="email" placeholder="nama@email.com"
                               required>
                    </div>
                    <div class="simawar-auth-field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password"
                               autocomplete="current-password" placeholder="Masukkan password" required>
                    </div>
                    <button type="submit" class="simawar-auth-submit">
                        <span>Masuk ke SIMAWAR</span><span aria-hidden="true">↗</span>
                    </button>
                </form>

                <p class="simawar-auth-register">Belum memiliki akun? <a href="{{ route('register') }}">Ajukan akses</a></p>
                <p class="simawar-auth-note">Akses sistem hanya untuk pengguna yang telah disetujui.</p>
            </div>
        </section>
    </main>
</body>
</html>
