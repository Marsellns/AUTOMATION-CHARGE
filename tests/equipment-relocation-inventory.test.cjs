const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.join(__dirname, '..');
const columns = [
    'uniq_key', 'site_id', 'nop', 'region', 'to_name', 'ne_name',
    'equipment_group', 'equipment_type', 'category', 'board_name',
    'board_type', 'serial_number', 'utilization_status', 'safe_to_reloc',
];
const inventory = {
    schema: 1,
    columns,
    rows: [
        ['UNIT-001', 'BKS001', 'NOP BEKASI', '', '', '', 'RU', '', '', '', '', '', '', 'Safe'],
        ['UNIT-002', 'BKS002', 'NOP BOGOR', '', '', '', 'BBP', '', '', '', '', '', '', 'OK'],
    ],
};

test('browser loader reads the MySQL-backed inventory endpoint and merges monitoring', async () => {
    const source = fs.readFileSync(path.join(root, 'public/assets/equipment-relocation/js/data.js'), 'utf8');
    const calls = [];
    const context = {
        window: {
            __equipmentRelocationUrls: {
                inventory: '/equipment-relocation/inventory-data',
                monitoring: '/equipment-relocation/relocation-data',
            },
        },
        fetch: async url => {
            calls.push(url);
            return {
                ok: true,
                json: async () => url.includes('inventory-data')
                    ? inventory
                    : { success: true, data: [{ donor_uniq_key: 'UNIT-001', pic: 'NOP BEKASI', progress: 'ON GOING' }] },
            };
        },
    };

    vm.runInNewContext(source, context);
    await context.window.__equipmentRelocationReady;
    await context.window.EquipmentRelocationData.load();

    assert.deepEqual(calls, [
        '/equipment-relocation/inventory-data',
        '/equipment-relocation/relocation-data',
    ]);
    assert.equal(context.window.__equipmentInventoryData.length, 2);
    assert.equal(context.window.__equipmentInventoryData[0].progress, 'ON GOING');
    assert.equal(context.window.__equipmentInventoryData[0].has_data, true);
    assert.equal(context.window.__equipmentInventoryData[1].is_safe_to_reloc, true);
});
