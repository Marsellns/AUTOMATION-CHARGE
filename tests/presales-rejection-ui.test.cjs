const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const root = path.join(__dirname, '..');
const view = fs.readFileSync(path.join(root, 'resources/views/presales/show.blade.php'), 'utf8');

test('Presales rejection requires a reason and explicit modal confirmation', () => {
    assert.match(view, /id="presalesRejectTrigger"/);
    assert.match(view, /id="presalesRejectModal"/);
    assert.match(view, /Apakah Anda yakin ingin menolak dokumen ini\?/);
    assert.match(view, /id="presalesRejectConfirm"/);
    assert.match(view, /id="presalesRejectConfirmation" value=""/);
    assert.match(view, /comments\.value\.trim\(\) === ''/);
    assert.match(view, /confirmation\.value = '1'/);
    assert.match(view, /action\.value = 'reject'/);
});
