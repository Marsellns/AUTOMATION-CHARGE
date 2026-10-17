(() => {
    const number = value => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    let root, nodes, selection, controller, version = 0, page = 1;

    function message(text) {
        nodes.rows.replaceChildren();
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 5;
        cell.className = 'text-center text-body-secondary py-4';
        cell.textContent = text;
        row.appendChild(cell);
        nodes.rows.appendChild(row);
    }

    function init() {
        if (root) return;
        root = document.getElementById('electricityDetailModal');
        nodes = Object.fromEntries(['title', 'summary', 'note', 'rows', 'page', 'prev', 'next', 'retry', 'content']
            .map(key => [key, root.querySelector(`[data-detail-${key}]`)]));
        nodes.prev.addEventListener('click', () => load(page - 1));
        nodes.next.addEventListener('click', () => load(page + 1));
        nodes.retry.addEventListener('click', () => load(page));
        root.addEventListener('hidden.bs.modal', () => { version++; controller?.abort(); });
    }

    async function load(nextPage) {
        const requestVersion = ++version;
        controller?.abort();
        controller = new AbortController();
        const request = controller;
        let timedOut = false;
        const timeout = setTimeout(() => { timedOut = true; request.abort(); }, 30000);
        page = nextPage;
        nodes.prev.disabled = nodes.next.disabled = true;
        nodes.retry.hidden = true;
        nodes.content.setAttribute('aria-busy', 'true');
        nodes.summary.textContent = 'Memuat rincian dari database…';
        nodes.note.textContent = nodes.page.textContent = '';
        message('Memuat data…');
        try {
            const params = new URLSearchParams({ ...selection, page });
            const response = await fetch(`${root.dataset.url}?${params}`, { signal: request.signal, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            if (requestVersion !== version) return;
            page = data.page;
            nodes.summary.textContent = `${number(data.total)} site · ${number(data.records_count)} transaksi/rekening · Rp ${number(data.amount)} · Sumber: ${data.source}`;
            nodes.note.textContent = data.note;
            nodes.rows.replaceChildren();
            for (const site of data.sites) {
                const row = document.createElement('tr');
                [site.site_id, site.site_name, number(site.records_count), number(site.amount), site.membership].forEach((value, index) => {
                    const cell = document.createElement('td');
                    cell.textContent = value ?? '—';
                    if (index === 2 || index === 3) cell.className = 'text-end text-nowrap';
                    row.appendChild(cell);
                });
                nodes.rows.appendChild(row);
            }
            if (!data.sites.length) message('Belum ada data pada periode dan filter ini.');
            nodes.page.textContent = `Halaman ${number(page)} dari ${number(data.last_page)}`;
            nodes.prev.disabled = page <= 1;
            nodes.next.disabled = page >= data.last_page;
        } catch (error) {
            if (requestVersion !== version || (error.name === 'AbortError' && !timedOut)) return;
            nodes.summary.textContent = timedOut ? 'Waktu permintaan habis. Silakan coba lagi.' : 'Rincian gagal dimuat. Silakan coba lagi.';
            message('Data tidak dapat dimuat.');
            nodes.retry.hidden = false;
        } finally {
            clearTimeout(timeout);
            if (requestVersion === version) nodes.content.setAttribute('aria-busy', 'false');
        }
    }

    function open(source, period, metric = 'sites', nop = '') {
        if (!period) return;
        init();
        selection = { source, tahun: period.tahun, bulan: period.bulan, metric, nop };
        const type = metric === 'billing' ? 'Tagihan' : 'Pembayaran';
        nodes.title.textContent = `${type} ${source === 'macro' ? 'Site Makro' : 'Site Inbuilding'} — ${months[period.bulan - 1]} ${period.tahun}`;
        bootstrap.Modal.getOrCreateInstance(root).show();
        return load(1);
    }
    window.SimasterElectricityDetail = Object.freeze({ open });
})();
