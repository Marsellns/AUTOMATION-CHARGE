/* Keep open reports current without interrupting an unfinished input form. */
(() => {
    'use strict';
    const script = document.currentScript || document.querySelector('script[data-version-url]');
    if (!script || window.__simasterDataRefresh) return;
    window.__simasterDataRefresh = true;
    let version = script.dataset.initialVersion;
    let dirty = false;
    let loading = false;
    let pending = false;
    let notice;
    function markDirty(event) {
        const target = event.target;
        if (!target.matches('input, textarea, select')) return;
        if (target.closest('[data-simaster-filter-panel], .simaster-filter-menu, .simaster-filter-drawer, .dt-search, .dataTables_filter, .global-filter-panel')) return;
        if (target.matches('input[type="search"], [id^="filter-"], [id^="erFilter"], #page-size')) return;
        const form = target.closest('form');
        if (form?.getAttribute('method')?.toLowerCase() === 'get') return;
        dirty = true;
    }
    document.addEventListener('input', markDirty);
    document.addEventListener('change', markDirty);
    function showNotice() {
        if (notice) return;
        notice = document.createElement('button');
        notice.type = 'button';
        notice.className = 'btn btn-primary position-fixed bottom-0 end-0 m-3';
        notice.style.zIndex = '1090';
        notice.textContent = 'Data diperbarui — muat ulang';
        notice.addEventListener('click', () => {
            if (!dirty || window.confirm('Muat ulang halaman? Input yang belum disimpan akan hilang.')) window.location.reload();
        });
        document.body.append(notice);
    }
    function refresh() {
        if (dirty || document.querySelector('.modal.show, [role="dialog"][aria-hidden="false"]') || /\/operations\//.test(location.pathname)) {
            pending = true;
            showNotice();
            return;
        }
        const detail = { handled: false };
        window.dispatchEvent(new CustomEvent('simaster:data-changed', { detail }));
        let tables = false;
        const $ = window.jQuery;
        if ($?.fn?.dataTable) {
            $.fn.dataTable.tables({ api: true }).iterator('table', function (settings) {
                if (settings.ajax) { this.ajax.reload(null, false); tables = true; }
            });
        }
        if (!detail.handled && (!tables || document.querySelector('[data-highcharts-chart], canvas'))) {
            window.location.reload();
        }
    }
    async function check() {
        if (loading || document.hidden) return;
        loading = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(script.dataset.versionUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) return;
            const data = await response.json();
            if (version && data.version !== version) { version = data.version; refresh(); }
        } catch (_) { /* Retry at the next interval after a network interruption. */ }
        finally { clearTimeout(timeout); loading = false; }
    }
    setInterval(check, 30000);
    window.addEventListener('focus', check);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
    document.addEventListener('hidden.bs.modal', () => { if (pending && !dirty) { pending = false; notice?.remove(); notice = null; refresh(); } });
})();
