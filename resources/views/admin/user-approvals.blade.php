@extends('layouts.app')

@section('title', 'Persetujuan Akun — SIMASTER')

@section('content')
    <h1 class="visually-hidden">Persetujuan Akun</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>Nama</th><th>Email / Login ID</th><th>Role Diminta</th><th>Dibuat</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($pendingUsers as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td><span class="badge text-bg-warning">{{ $user->requested_role }}</span></td>
                                <td>{{ $user->created_at->format('d/m/Y H:i') }}</td>
                                <td class="d-flex gap-2">
                                    <form method="POST" action="{{ route('admin.user-approvals.approve', $user) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success" type="submit">Setujui</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.user-approvals.reject', $user) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Tolak</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-body-secondary py-4">Tidak ada permintaan tertunda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
