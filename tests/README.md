# OKAPI test scripts

Node.js (ESM) scripts for testing OKAPI services manually.

## Setup

```bash
cp config.example.js config.js
# edit config.js — fill in your consumer key, secrets, and target baseUrl
```

`config.js` is gitignored. Never commit it.

## Finding a valid cache code in the ddev database

```bash
ssh ocde "cd ~/opencaching/oc-server3 && ddev exec 'mysql -uroot -proot -e \"SELECT wp_oc FROM caches LIMIT 5\" db'"
```

## Running tests

All scripts accept the cache code as a CLI argument. For tests that require
a valid code in the target database, use one from the query above.

```bash
# Verify installation (no cache code needed)
node oc-installation.mjs

# Public endpoints
node oc-get-logs.mjs OC1234

# OAuth endpoints
node oc-get-cache.mjs OC1234
node oc-log-capabilities.mjs OC1234

# New services (oc4-combined branch)
node oc-save-user-coords.mjs OC1234 48.123456 9.123456
node oc-lists.mjs OC1234
node oc-fieldnotes.mjs OC1234
```

## Switching between production and ddev

Edit `baseUrl` in `config.js`:
```js
export const baseUrl = 'https://opencaching.ddev.site';  // local ddev
// export const baseUrl = 'https://www.opencaching.de';  // production
```
