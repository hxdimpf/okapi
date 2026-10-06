# OKAPI test scripts

Node.js (ESM) scripts for testing OKAPI services manually.

## Setup

```bash
cp config.example.js config.js
# edit config.js — fill in your consumer key, secrets, and target baseUrl
```

`config.js` is gitignored. Never commit it.

## Finding a valid cache code

```bash
node oc-search.mjs <username>    # prints OC codes of caches owned by <username>
```

## Running tests

All scripts accept the cache code as a CLI argument. For tests that require
a valid code in the target database, use one from `oc-search.mjs`.

```bash
# Verify installation (no cache code needed)
node oc-installation.mjs

# Public endpoints
node oc-get-logs.mjs OC1234

# OAuth endpoints
node oc-get-cache.mjs OC1234
node oc-log-capabilities.mjs OC1234

# Services added in this fork
node oc-save-user-coords.mjs OC1234 48.123456 9.123456
node oc-lists.mjs OC1234
node oc-fieldnotes.mjs OC1234
```

## Cache CRUD (writes to the target database)

Run as the owner of the caches. Creates, moves and archives 36 test caches named `Nut-01` … `Nut-36`.

```bash
node oc-create-circle.mjs       # caches/create: 36 caches in a circle
node oc-update-circle-fix.mjs   # caches/update: correct the even ones to a true circle
node oc-delete-odd-nuts.mjs     # caches/delete: archive the odd ones
node oc-cleanup-circle.mjs      # archive all remaining Nut-XX caches
```

## Switching installations

Edit `baseUrl` in `config.js` (and use credentials issued by that installation):
```js
export const baseUrl = 'https://okapi.example.org';
```
