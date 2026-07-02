#!/bin/bash
# =============================================================================
# Dentfluence — Re-lock a feature after editing
# Usage: bash scripts/lock-feature.sh <feature-name>
# Example: bash scripts/lock-feature.sh appointments
# =============================================================================

FEATURE="$1"
LOCK_FILE="LOCKED_FEATURES.md"
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

if [ -z "$FEATURE" ]; then
  echo -e "${RED}Error: Please provide a feature name.${NC}"
  echo "Usage: bash scripts/lock-feature.sh <feature-name>"
  exit 1
fi

if [ ! -f "$LOCK_FILE" ]; then
  echo -e "${RED}Error: $LOCK_FILE not found.${NC}"
  exit 1
fi

if ! grep -q "^### $FEATURE$" "$LOCK_FILE"; then
  echo -e "${RED}Error: Feature '$FEATURE' not found in $LOCK_FILE${NC}"
  exit 1
fi

# Remove the UNLOCKED STATUS line for this feature
sed -i "/^### $FEATURE$/{ n; /\*\*STATUS: UNLOCKED/d }" "$LOCK_FILE"

echo ""
echo -e "${GREEN}🔒 Feature '$FEATURE' is now LOCKED again.${NC}"
echo ""
