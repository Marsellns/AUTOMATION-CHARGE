<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buat Akun — SIMASTER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/simaster.css') }}" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container" style="max-width: 480px; margin-top: 5rem;">
        <h1 class="h4 mb-3 text-center">Buat Akun SIMASTER</h1>
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('register') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email / Login ID</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="requested_role" class="form-label">Role yang diminta</label>
                        <select id="requested_role" name="requested_role" class="form-select" required>
                            <option value="viewer" @selected(old('requested_role', 'viewer') === 'viewer')>Viewer (akses lihat)</option>
                            <option value="admin" @selected(old('requested_role') === 'admin')>Admin (akses penuh, perlu persetujuan)</option>
                            <option value="manager_nop" @selected(old('requested_role') === 'manager_nop')>Manager NOP (approval Presales)</option>
                            <option value="manager_sq" @selected(old('requested_role') === 'manager_sq')>Manager SQ (approval Presales)</option>
                            <option value="manager_nos" @selected(old('requested_role') === 'manager_nos')>Manager NOS (approval Presales)</option>
                            <option value="manager_nbae" @selected(old('requested_role') === 'manager_nbae')>Manager NBAE (approval Presales)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Konfirmasi Password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-brand w-100">Buat Akun</button>
                </form>
            </div>
        </div>
        <p class="text-center mt-3"><a href="{{ route('login') }}">Kembali ke login</a></p>
    </div>
</body>
</html>
