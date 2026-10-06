// Removes all Nut-XX caches created by oc-create-circle.mjs
// Usage: node oc-cleanup-circle.mjs
import { okapiPost } from './oauth.mjs';

const caches = [];
for (let i = 1; i <= 36; i++) {
  caches.push(String(i).padStart(2, '0'));
}

console.log(`Deleting ${caches.length} circle caches...`);

let deleted = 0;
let failed = 0;

for (const num of caches) {
  const name = `Nut-${num}`;
  try {
    const result = await okapiPost('services/caches/delete', {
      cache_code: name,
    });
    if (result.error) {
      console.log(`✗ ${name}: ${result.error.developer_message || result.error.status}`);
      failed++;
    } else {
      console.log(`✓ ${name} deleted`);
      deleted++;
    }
  } catch (e) {
    console.log(`✗ ${name}: ${e.message}`);
    failed++;
  }
}

console.log(`\nDeleted: ${deleted}, Failed: ${failed}`);
