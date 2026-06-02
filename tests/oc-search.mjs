// Searches OKAPI for geocaches and prints their OC codes.
// Use the results as arguments to the other test scripts.
// Usage: node oc-search.mjs [search_term]
//
// Without a search term, returns a handful of recently-active caches.
import { okapiPublicGet, okapiPost } from './oauth.mjs';

const query = process.argv[2] ?? '';

// First search for cache codes matching the query (or just nearby a central point)
const searchParams = query
  ? { name: query, limit: 10, fields: 'code|name|location|status|type' }
  : { center: '48.5|9.0', radius: 50, limit: 10, fields: 'code|name|location|status|type', status: 'Available' };

const endpoint = query ? 'services/caches/search/byname' : 'services/caches/search/nearest';

// Step 1: get cache codes from search
const searchResult = await okapiPublicGet(endpoint, searchParams);

if (searchResult.error) {
  console.error('Search failed:', searchResult.error);
  process.exit(1);
}

const codes = searchResult.results;
if (!codes?.length) {
  console.log('No results.');
  process.exit(0);
}

// Step 2: fetch details for those codes
const details = await okapiPost('services/caches/geocaches', {
  cache_codes: codes.join('|'),
  fields: 'code|name|status|type|location',
});

for (const [code, cache] of Object.entries(details)) {
  if (cache.error) continue;
  console.log(`${code}  ${cache.status.padEnd(12)}  ${cache.type.padEnd(20)}  ${cache.name}`);
}
