<div class="modal fade" id="electricityDetailModal" tabindex="-1" aria-labelledby="electricity-detail-heading" aria-hidden="true" data-url="{{ route('electricity.payment-detail') }}">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="electricity-detail-heading" data-detail-title>Rincian Pembayaran Listrik</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" data-detail-content aria-busy="false">
                <p class="small text-body-secondary" data-detail-summary role="status" aria-live="polite"></p>
                <p class="small" data-detail-note></p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle text-nowrap">
                        <thead><tr><th>Site ID</th><th>Nama Site</th><th class="text-end">Transaksi/Rekening</th><th class="text-end">Nominal (Rp)</th><th>Keterangan</th></tr></thead>
                        <tbody data-detail-rows></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="small text-body-secondary" data-detail-page></span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-detail-prev disabled>Sebelumnya</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-detail-next disabled>Berikutnya</button>
                    <button type="button" class="btn btn-sm btn-primary" data-detail-retry hidden>Coba lagi</button>
                </div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script src="{{ asset('js/electricity-payment-detail.js') }}"></script>
@endpush
