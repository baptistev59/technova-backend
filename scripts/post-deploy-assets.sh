#!/bin/bash
# Post-deployment hook to copy JS assets to public/assets for importmap
# This ensures product-form.js is accessible in production

set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Ensure public/assets/js exists
mkdir -p "$PROJECT_ROOT/public/assets/js"

# Copy product-form.js from assets to public/assets for importmap
if [ -f "$PROJECT_ROOT/assets/js/product-form.js" ]; then
    cp "$PROJECT_ROOT/assets/js/product-form.js" "$PROJECT_ROOT/public/assets/js/product-form.js"
    echo "✓ Copied assets/js/product-form.js to public/assets/js/"
else
    echo "⚠ Warning: assets/js/product-form.js not found"
fi

# Ensure permissions are correct
chmod 644 "$PROJECT_ROOT/public/assets/js/product-form.js" 2>/dev/null || true

echo "✓ Post-deployment asset setup complete"
