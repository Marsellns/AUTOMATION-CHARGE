<!DOCTYPE html>
{{--
    Template PDF Cetak SIP — Sewa Lahan Renewal.
    Template awal (sederhana) — akan disempurnakan kemudian sesuai kebutuhan.
    Catatan DOMPDF: gunakan CSS sederhana/inline, hindari flexbox & CSS modern.
--}}
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>SIP — {{ $sewaLahan->site_code }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 40px 48px; }
        .header { text-align: center; border-bottom: 2px solid #d9232e; padding-bottom: 10px; margin-bottom: 18px; }
        .header h1 { font-size: 16px; margin: 0; text-transform: uppercase; letter-spacing: 1px; }
        .header p { margin: 4px 0 0; font-size: 10px; color: #555; }
        h2 { font-size: 12px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #ccc; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table td { padding: 5px 8px; vertical-align: top; }
        table.info td { border: 1px solid #999; }
        table.info td.label { width: 32%; background-color: #f3f4f6; font-weight: bold; }
        .footer { margin-top: 36px; font-size: 9px; color: #777; border-top: 1px solid #ccc; padding-top: 8px; }
        .footer table td { padding: 2px 0; border: none; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Surat Izin Perpanjangan (SIP)</h1>
        <p>Sewa Lahan — Infrastruktur Management | SIMASTER</p>
    </div>

    <h2>Identitas Site</h2>
    <table class="info">
        <tr>
            <td class="label">Site ID</td>
            <td>{{ $sewaLahan->site_code }}</td>
        </tr>
        <tr>
            <td class="label">Site Name</td>
            <td>{{ $sewaLahan->site_name ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Tahun Renewal</td>
            <td>{{ $sewaLahan->tahun_renewal ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Status Dokumen</td>
            <td>{{ $sewaLahan->status_dokumen ?: '—' }}</td>
        </tr>
    </table>

    <h2>Informasi SIP</h2>
    <table class="info">
        <tr>
            <td class="label">Nomor SIP</td>
            <td>{{ $sewaLahan->no_sip ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Terima SIP</td>
            <td>{{ $sewaLahan->tgl_terima_sip?->format('d/m/Y') ?? '—' }}</td>
        </tr>
    </table>

    <h2>Informasi PKS Terkait</h2>
    <table class="info">
        <tr>
            <td class="label">No PKS Baru</td>
            <td>{{ $sewaLahan->no_pks_baru ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Periode PKS Baru</td>
            <td>
                {{ $sewaLahan->start_date_baru?->format('d/m/Y') ?? '—' }}
                s.d.
                {{ $sewaLahan->end_date_baru?->format('d/m/Y') ?? '—' }}
            </td>
        </tr>
        <tr>
            <td class="label">Harga Baru</td>
            <td>Rp {{ $sewaLahan->harga_baru !== null ? number_format((float) $sewaLahan->harga_baru, 0, ',', '.') : '—' }}</td>
        </tr>
        <tr>
            <td class="label">Total Harga Baru</td>
            <td>Rp {{ $sewaLahan->total_harga_baru !== null ? number_format((float) $sewaLahan->total_harga_baru, 0, ',', '.') : '—' }}</td>
        </tr>
        <tr>
            <td class="label">No PKS Lama</td>
            <td>{{ $sewaLahan->no_pks_lama ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Periode PKS Lama</td>
            <td>
                {{ $sewaLahan->start_date_lama?->format('d/m/Y') ?? '—' }}
                s.d.
                {{ $sewaLahan->end_date_lama?->format('d/m/Y') ?? '—' }}
            </td>
        </tr>
    </table>

    <div class="footer">
        <table>
            <tr>
                <td>Dicetak: {{ now()->format('d/m/Y H:i') }} WIB</td>
                <td style="text-align: right;">SIMASTER — Infrastruktur Management</td>
            </tr>
        </table>
    </div>
</body>
</html>
