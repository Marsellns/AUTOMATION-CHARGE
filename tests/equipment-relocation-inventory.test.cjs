const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.join(__dirname, '..');
const snapshot = JSON.parse(fs.readFileSync(path.join(root, 'public/data/equipment_relocation_inventory.json'), 'utf8'));
const column = Object.fromEntries(snapshot.columns.map((name, index) => [name, index]));

test('snapshot preserves the reference inventory and chart totals', () => {
    assert.equal(snapshot.schema, 1);
    assert.equal(snapshot.rows.length, 50640);
    assert.equal(new Set(snapshot.rows.map(row => row[column.uniq_key])).size, snapshot.rows.length);

    const count = (group, predicate = () => true) => snapshot.rows.filter(row =>
        row[column.equipment_group] === group && predicate(row)).length;
    const safe = row => ['OK', 'SAFE'].includes(String(row[column.safe_to_reloc]).trim().toUpperCase());
    assert.equal(count('RU'), 37532);
    assert.equal(count('BBP'), 13108);
    assert.equal(count('RU', safe), 470);
    assert.equal(count('BBP', safe), 2341);

    for (const group of ['RU', 'BBP']) {
        const byNop = new Map();
        for (const row of snapshot.rows.filter(row => row[column.equipment_group] === group)) {
            byNop.set(row[column.nop], (byNop.get(row[column.nop]) || 0) + 1);
        }
        assert.equal([...byNop.values()].reduce((sum, value) => sum + value, 0), count(group));
        assert.deepEqual([...byNop.keys()].sort(), ['NOP BEKASI', 'NOP BOGOR', 'NOP KARAWANG']);
    }
});

test('browser loader merges monitoring once and uses local snapshot', async () => {
    const source = fs.readFileSync(path.join(root, 'public/assets/js/equipment-relocation-data.js'), 'utf8');
    const firstKey = snapshot.rows[0][column.uniq_key];
    const calls = [];
    const context = {
        window: { __equipmentRelocationUrls: { inventory: '/inventory', monitoring: '/monitoring' } },
        fetch: async url => {
            calls.push(url);
            return { ok: true, json: async () => url === '/inventory'
                ? snapshot
                : { success: true, data: [{ donor_uniq_key: firstKey, pic: 'NOP BEKASI', progress: 'ON GOING' }] } };
        },
    };
    vm.runInNewContext(source, context);
    await context.window.__equipmentRelocationReady;
    await context.window.EquipmentRelocationData.load();

    assert.deepEqual(calls, ['/inventory', '/monitoring']);
    assert.equal(context.window.__equipmentInventoryData.length, snapshot.rows.length);
    assert.equal(context.window.__equipmentInventoryData[0].progress, 'ON GOING');
    assert.equal(context.window.__equipmentInventoryData[0].has_data, true);
    assert.equal(context.window.__equipmentInventoryData[1].progress, '');
});
