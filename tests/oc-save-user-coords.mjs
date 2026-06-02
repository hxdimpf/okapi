// Tests services/caches/save_user_coords (requires OAuth)
// Usage: node oc-save-user-coords.mjs <cache_code> <lat> <lon>
// The cache_code must exist in the target database.
import { okapiPost } from './oauth.mjs';

const cache_code  = process.argv[2];
const lat         = process.argv[3] ?? '48.123456';
const lon         = process.argv[4] ?? '9.123456';

if (!cache_code) {
  console.error('Usage: node oc-save-user-coords.mjs <cache_code> [lat] [lon]');
  process.exit(1);
}

const result = await okapiPost('services/caches/save_user_coords', {
  cache_code,
  user_coords: `${lat}|${lon}`,
});
console.log(JSON.stringify(result, null, 2));
