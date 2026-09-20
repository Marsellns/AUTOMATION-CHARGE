// One-time import of an inventory snapshot. The application never reads the source path.
const fs = require('node:fs');
const path = require('node:path');

const source = process.argv[2];
if (!source) {
    console.error('Usage: node scripts/build-equipment-relocation-inventory.cjs <source JSON>');
    process.exit(1);
}

const input = JSON.parse(fs.readFileSync(path.resolve(source), 'utf8'));
if (!Array.isArray(input)) throw new Error('Source inventory must be an array.');

const columns = [
    'uniq_key', 'site_id', 'nop', 'region', 'to_name', 'ne_name',
    'equipment_group', 'equipment_type', 'category', 'board_name',
    'board_type', 'serial_number', 'utilization_status', 'safe_to_reloc',
];
const seen = new Set();
const rows = input.map((item, index) => {
    const key = String(item.uniq1 || '').trim();
    if (!key || seen.has(key)) throw new Error(`Missing or duplicate uniq1 at row ${index + 1}.`);
    seen.add(key);

    const group = Object.hasOwn(item, 'Utilized') ? 'RU'
        : Object.hasOwn(item, 'idle/connected') ? 'BBP' : null;
    if (!group) throw new Error(`Unknown equipment group at row ${index + 1}.`);

    return [
        key,
        item['site id'] || '',
        item.NOP || item['nop dapot eastern'] || '',
        item.region || '',
        item.TO || '',
        item.NEName || '',
        group,
        item['category type'] || item['Board Type'] || '',
        item.category || '',
        item['Board Name'] || '',
        item['Board Type'] || '',
        item['SN(Bar Code)'] || '',
        item.Utilized ?? item['idle/connected'] ?? '',
        item['Safe to Reloc'] || '',
    ];
});

const destination = path.join(__dirname, '..', 'public', 'data', 'equipment_relocation_inventory.json');
fs.mkdirSync(path.dirname(destination), { recursive: true });
fs.writeFileSync(destination, JSON.stringify({ schema: 1, columns, rows }));

const ru = rows.filter(row => row[6] === 'RU').length;
const safe = rows.filter(row => ['OK', 'SAFE'].includes(String(row[13]).trim().toUpperCase())).length;
console.log(`Imported ${rows.length} equipment (${ru} RU, ${rows.length - ru} BBP, ${safe} safe).`);
