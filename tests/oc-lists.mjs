// Tests services/lists/* (requires OAuth)
// Full CRUD: create → add_caches → get_caches → remove_caches → delete
// Usage: node oc-lists.mjs <cache_code>
// The cache_code must exist in the target database.
import { okapiPost, okapiGet } from './oauth.mjs';

const cache_code = process.argv[2];
if (!cache_code) {
  console.error('Usage: node oc-lists.mjs <cache_code>');
  process.exit(1);
}

let ok = true;
const fail = (step, result) => { console.error(`✗ ${step}:`, JSON.stringify(result)); ok = false; };
const pass = (step, note = '') => console.log(`✓ ${step}${note ? ': ' + note : ''}`);

// 1. Create list
const created = await okapiPost('services/lists/create', {
  list_name: 'okapi-test-suite',
  list_status: 'private',
});
if (!created.list_uuid) { fail('create', created); process.exit(1); }
pass('create', `uuid=${created.list_uuid}`);
const uuid = created.list_uuid;

// 2. Add cache
const added = await okapiPost('services/lists/add_caches', { list_uuid: uuid, cache_codes: cache_code });
if (added.error) fail('add_caches', added); else pass('add_caches');

// 3. Get caches
const got = await okapiPost('services/lists/get_caches', { list_uuid: uuid, fields: 'code|name' });
const found = got?.results?.some?.(c => c.code === cache_code);
if (!found) fail('get_caches', got); else pass('get_caches', `found ${cache_code}`);

// 4. Remove cache
const removed = await okapiPost('services/lists/remove_caches', { list_uuid: uuid, cache_codes: cache_code });
if (removed.error) fail('remove_caches', removed); else pass('remove_caches');

// 5. Delete list
const deleted = await okapiPost('services/lists/delete', { list_uuid: uuid });
if (deleted.error) fail('delete', deleted); else pass('delete');

process.exit(ok ? 0 : 1);
