#!/bin/bash

# Deployment script for OKAPI services to test server
# Usage: ./deploy.sh [branch] [server]
# Example: ./deploy.sh oc4-cache-crud oc3.baiti.net
#          ./deploy.sh oc4-combined oc3.baiti.net

set -e  # Exit on error

# Configuration
BRANCH="${1:-oc4-cache-crud}"
SERVER="${2:-oc3.baiti.net}"
OKAPI_PATH="/var/www/oc/okapi"
OKAPI_USER="baiti"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "OKAPI Deployment Script"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Target Server: $SERVER"
echo "Target Branch: $BRANCH"
echo "OKAPI Path:    $OKAPI_PATH"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Verify we can reach the server
echo "▶ Checking connection to $SERVER..."
if ! ssh -o ConnectTimeout=5 "$OKAPI_USER@$SERVER" "echo 'Connected'" > /dev/null 2>&1; then
  echo "✗ Cannot connect to $SERVER"
  exit 1
fi
echo "✓ Connected"
echo ""

# Deploy to remote server
echo "▶ Deploying to $SERVER..."
ssh "$OKAPI_USER@$SERVER" bash << EOF
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "▶ Changing to $OKAPI_PATH"
cd "$OKAPI_PATH"

echo "▶ Fetching latest code from origin..."
git fetch origin
echo "✓ Fetched"

echo "▶ Checking out branch: $BRANCH"
git checkout "$BRANCH"
echo "✓ Checked out $BRANCH"

echo "▶ Pulling latest changes..."
git pull origin "$BRANCH"
echo "✓ Pulled"

echo ""
echo "▶ Current commit:"
git log -1 --oneline

echo ""
echo "▶ Verifying service files exist..."
SERVICES=("create" "read" "update" "delete")
for service in "\${SERVICES[@]}"; do
  if [ -f "okapi/services/caches/\$service/WebService.php" ]; then
    echo "  ✓ services/caches/\$service"
  else
    echo "  ✗ services/caches/\$service NOT FOUND"
    exit 1
  fi
done

echo ""
echo "▶ Running composer install..."
composer install --no-interaction --no-progress 2>&1 | tail -5
echo "✓ Composer complete"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "\${GREEN}✓ Deployment successful!${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "OKAPI services ready at:"
echo "  - http://$SERVER/okapi/services/caches/create.html"
echo "  - http://$SERVER/okapi/services/caches/read.html"
echo "  - http://$SERVER/okapi/services/caches/update.html"
echo "  - http://$SERVER/okapi/services/caches/delete.html"
echo ""

EOF

echo ""
echo "✓ Deployment complete!"
echo ""
echo "Next steps:"
echo "  1. Run the test: node tests/oc-create-circle.mjs"
echo "  2. Check test server: http://$SERVER/okapi/"
echo ""
