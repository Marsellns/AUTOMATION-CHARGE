(() => {
    const count = value => Number(value || 0).toLocaleString('id-ID');
    const percent = value => value == null ? 'Belum tersedia' : `${Number(value).toLocaleString('id-ID', { maximumFractionDigits: value > 99.9 && value < 100 ? 2 : 1 })}%`;
    const money = value => `Rp ${Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 })}`;
    const moneyAxis = value => Math.abs(value) >= 1e9 ? `Rp ${Number(value / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 1 })} miliar`
        : Math.abs(value) >= 1e6 ? `Rp ${Number(value / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 })} juta` : money(value);
    const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));

    function options(data, theme, metric = 'sites', onPointClick) {
        const sites = metric === 'sites';
        return {
            chart: { type: 'column', backgroundColor: 'transparent', spacingTop: 35, scrollablePlotArea: { minWidth: Math.max(680, (data.labels || []).length * 110 + 100), scrollPositionX: 0 } },
            title: { text: null }, credits: { enabled: false }, exporting: { enabled: false },
            xAxis: { categories: data.labels || [], tickmarkPlacement: 'between', gridLineWidth: 1, gridLineColor: theme.gridLine, labels: { style: { color: theme.text } } },
            yAxis: { min: 0, maxPadding: .24, allowDecimals: !sites, gridLineWidth: 1, gridLineColor: theme.gridLine, gridLineDashStyle: 'ShortDash',
                title: { text: sites ? 'Jumlah site dengan data pembayaran' : 'Nominal pembayaran (Rp)', style: { color: theme.text } },
                labels: { style: { color: theme.text }, formatter() { return sites ? count(this.value) : moneyAxis(this.value); } } },
            legend: { enabled: true, itemStyle: { color: theme.text } },
            tooltip: { shared: false, formatter() {
                const detail = this.point.custom;
                return `<b>${escape(data.labels[this.point.index])} — ${escape(this.series.name)}</b><br>` +
                    `Site dengan pembayaran: <b>${count(detail.paid_sites)}</b><br>` +
                    `${escape(detail.population_label)}: <b>${count(detail.population)}</b><br>` +
                    `Cakupan: <b>${percent(detail.percentage)}</b><br>Nominal: <b>${money(detail.amount)}</b><br>` +
                    `Sumber: ${escape(detail.source)}` + (detail.unmatched_sites ? `<br>${count(detail.unmatched_sites)} site pembayaran di luar master aktif; tidak masuk persentase.` : '');
            } },
            plotOptions: { column: { borderWidth: 1, borderColor: theme.gridLine, borderRadius: 5, groupPadding: .12, pointPadding: .06,
                cursor: onPointClick ? 'pointer' : 'default',
                dataLabels: { enabled: sites, inside: false, crop: true, overflow: 'justify', y: -5,
                    formatter() { return `${count(this.y)}<br/>(${percent(this.point.custom.percentage)})`; },
                    style: { color: theme.text, fontSize: '13px', fontWeight: '600', textOutline: 'none' } },
                point: { events: { click() { if (onPointClick) onPointClick(this.series.options.custom.key, data.periods[this.index], metric); } } } } },
            series: (data.series || []).map(series => ({ name: series.name, color: series.key === 'macro' ? '#f97316' : '#2563eb', custom: { key: series.key },
                data: series.points.map(point => ({ y: point.available ? (sites ? point.paid_sites : point.amount) : null,
                    custom: { ...point, source: series.source, population_label: series.population_label } })) })),
        };
    }
    function caption(data) {
        const ibc = (data.series || []).find(series => series.key === 'inbuilding');
        const population = ibc?.points?.[0]?.population;
        const ibcCaption = ibc ? `Site Inbuilding: Payment IBC berstatus terbayar / ${count(population)} site aktif pada master saat ini. ` : '';
        return `Site Makro: Flagging Listrik All / populasi snapshot per bulan. ${ibcCaption}${(data.notes || []).join(' ')}`;
    }
    window.SimasterElectricityComparison = Object.freeze({ options, caption });
})();
