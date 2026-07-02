#!/bin/bash
# =============================================================================
# Dentfluence — Unlock a locked feature for editing
# Usage: bash scripts/unlock-feature.sh <feature-name>
# Example: bash scripts/unlock-feature.sh appointments
# =============================================================================

FEATURE="$1"
LOCK_FILE="LOCKED_FEATURES.md"
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
NC='\033[0m'

if [ -z "$FEATURE" ]; then
  echo -e "${RED}Error: Please provide a feature name.${NC}"
  echo "Usage: bash scripts/unlock-feature.sh <feature-name>"
  echo "Example: bash scripts/unlock-feature.sh appointments"
  exit 1
fi

if [ ! -f "$LOCK_FILE" ]; then
  echo -e "${RED}Error: $LOCK_FILE not found.${NC}"
  exit 1
fi

# Check if feature exists and is locked
if ! grep -q "^### $FEATURE$" "$LOCK_FILE"; then
  echo -e "${RED}Error: Feature '$FEATURE' not found in $LOCK_FILE${NC}"
  exit 1
fi

# Check if already unlocked
if grep -A2 "^### $FEATURE$" "$LOCK_FILE" | grep -q "STATUS: UNLOCKED"; then
  echo -e "${YELLOW}⚠️  Feature '$FEATURE' is already unlocked.${NC}"
  exit 0
fi

# Add UNLOCKED status line after the feature header
# Uses sed to insert after the matching ### line
sed -i "/^### $FEATURE$/a **STATUS: UNLOCKED FOR EDITING** — unlocked on $(date '+%Y-%m-%d %H:%M')" "$LOCK_FILE"

echo ""
echo -e "${GREEN}🔓 Feature '$FEATURE' is now UNLOCKED for editing.${NC}"
echo ""
echo -e "${YELLOW}Remember to re-lock when done:${NC}"
echo -e "   bash scripts/lock-feature.sh $FEATURE"
echo ""
