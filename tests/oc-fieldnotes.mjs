// Tests services/draftlogs/upload_fieldnotes (requires OAuth)
// Usage: node oc-fieldnotes.mjs <cache_code>
// The cache_code must exist in the target database.
// Uploads one draft "Found it" log and checks the response counts.
import { okapiPost } from './oauth.mjs';

const cache_code = process.argv[2];
if (!cache_code) {
  console.error('Usage: node oc-fieldnotes.mjs <cache_code>');
  process.exit(1);
}

const date = new Date().toISOString().replace(/\.\d+Z$/, 'Z');
// CSV: cache_code, date, log_type, log_text
const fieldNotes = `${cache_code},${date},Found it,"Test draft log from okapi test suite"`;

const result = await okapiPost('services/draftlogs/upload_fieldnotes', {
  field_notes: fieldNotes,
});

console.log(JSON.stringify(result, null, 2));

if (result.error) {
  console.error('✗ upload failed');
  process.exit(1);
}
if (result.success !== true) {
  console.error('✗ success !== true');
  process.exit(1);
}
console.log(`✓ processed ${result.processed_records}/${result.total_records} records`);
