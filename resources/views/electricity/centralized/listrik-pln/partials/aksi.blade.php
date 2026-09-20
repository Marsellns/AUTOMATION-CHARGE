<div class="aksi-cell dropdown" onclick="event.stopPropagation();">
    <button class="btn btn-outline-secondary btn-sm dropdown-toggle d-inline-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        Aksi
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li>
            <a class="dropdown-item d-flex align-items-center py-2" href="{{ route('electricity.centralized.listrik-pln.status-pembayaran.index', $item) }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-2 text-primary"><path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/><path d="M3 4h10v2H3V4zm0 4h10v2H3V8zm0 4h7v2H3v-2z"/></svg>
                <span>Status Pembayaran</span>
            </a>
        </li>
        <li>
            <a class="dropdown-item d-flex align-items-center py-2" href="{{ route('electricity.centralized.listrik-pln.grafik', $item) }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-2 text-danger"><path d="M0 0h1v15h15v1H0V0Zm10 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0V4.707l-4.146 4.147a.5.5 0 0 1-.708 0L7 6.707l-3.646 3.647a.5.5 0 0 1-.708-.708l4-4a.5.5 0 0 1 .708 0L9.5 7.793 13.293 4H10.5a.5.5 0 0 1-.5-.5Z"/></svg>
                <span>Grafik Tagihan</span>
            </a>
        </li>
        <li><hr class="dropdown-divider my-1"></li>
        <li>
            <a class="dropdown-item d-flex align-items-center py-2" href="{{ route('electricity.centralized.listrik-pln.edit', $item) }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-2 text-warning"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3-1 1 1 1 1-1-1-1zM8.5 7.5l3 3-1 1-3-3 1-1z"/></svg>
                <span>Edit Data</span>
            </a>
        </li>
        <li>
            <a class="dropdown-item d-flex align-items-center py-2" href="{{ route('electricity.centralized.listrik-pln.bongkar-rampung.create', $item) }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-2 text-success"><path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/></svg>
                <span>Bongkar Rampung</span>
            </a>
        </li>
    </ul>
</div>