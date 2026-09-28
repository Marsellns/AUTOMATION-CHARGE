/* Inventaris mandiri SIMAWAR.
 * Browser hanya membaca berkas dalam proyek ini dan data monitoring Laravel. */
(function () {
    "use strict";

    const urls = window.__equipmentRelocationUrls || {};
    window.__equipmentInventoryData = [];
    window.__equipmentRelocationWarnings = [];

    async function fetchJson(url) {
        const response = await fetch(url, {
            credentials: "same-origin",
            cache: "no-store",
            headers: { Accept: "application/json" }
        });
        if (!response.ok) throw new Error(`Gagal memuat data (${response.status}).`);
        return response.json();
    }

    async function load() {
        const inventory = await fetchJson(urls.inventory);
        if (inventory.schema !== 1 || !Array.isArray(inventory.columns) || !Array.isArray(inventory.rows)) {
            throw new Error("Format inventaris Equipment Relocation tidak valid.");
        }

        const columns = inventory.columns;
        const rows = inventory.rows.map(values => {
            const item = Object.fromEntries(columns.map((column, index) => [column, values[index]]));
            item.is_safe_to_reloc = ["OK", "SAFE"].includes(String(item.safe_to_reloc || "").trim().toUpperCase());
            return item;
        });

        let monitoring = [];
        try {
            const result = await fetchJson(urls.monitoring);
            if (result.success === false || !Array.isArray(result.data)) throw new Error("Data monitoring tidak valid.");
            monitoring = result.data;
        } catch (error) {
            window.__equipmentRelocationWarnings.push("Inventaris dimuat, tetapi data progres relokasi belum tersedia. " + error.message);
        }

        const monitoringByKey = new Map(monitoring.map(row => [String(row.donor_uniq_key || "").trim(), row]));
        window.__equipmentInventoryData = rows.map(item => {
            const data = monitoringByKey.get(item.uniq_key);
            return {
                ...item,
                donor_acceptor: data?.donor_acceptor || "",
                site_target_source: data?.site_target_source || "",
                pic: data?.pic || "",
                progress: data?.progress || "",
                remark: data?.remark || "",
                has_data: !!data
            };
        });

        return { inventory: window.__equipmentInventoryData };
    }

    // Satu permintaan inventaris untuk seluruh KPI, diagram, tabel, dan filter.
    const ready = load();
    window.__equipmentRelocationReady = ready;
    window.EquipmentRelocationData = { load: () => ready };
})();
