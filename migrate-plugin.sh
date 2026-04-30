#!/bin/bash
# Migration Script for PJJA Booking Plugin
# This script helps migrate plugin files to an existing installation

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}PJJA Booking Plugin Migration Script${NC}"
echo "=========================================="
echo ""

# Check if running from plugin directory
if [ ! -f "pjja-trial-booking.php" ]; then
    echo -e "${RED}Error: This script must be run from the plugin directory${NC}"
    exit 1
fi

# Get target directory
read -p "Enter the path to your existing plugin installation (e.g., /var/www/wp-content/plugins/pjja-booking): " TARGET_DIR

if [ ! -d "$TARGET_DIR" ]; then
    echo -e "${RED}Error: Target directory does not exist: $TARGET_DIR${NC}"
    exit 1
fi

# Confirm backup
echo ""
echo -e "${YELLOW}Warning: This will overwrite files in $TARGET_DIR${NC}"
read -p "Have you backed up the existing installation? (yes/no): " BACKED_UP

if [ "$BACKED_UP" != "yes" ]; then
    echo -e "${YELLOW}Please backup your installation first!${NC}"
    exit 1
fi

# Files to copy
FILES=(
    "pjja-trial-booking.php"
    "admin/admin-page.php"
    "admin/settings-page.php"
    "includes/class-admin-settings.php"
    "includes/class-rest-api.php"
    "includes/class-github-updater.php"
)

echo ""
echo "Copying files..."
echo ""

# Copy each file
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "Copying $file..."
        cp "$file" "$TARGET_DIR/$file"
        echo -e "${GREEN}✓${NC} Copied $file"
    else
        echo -e "${RED}✗${NC} File not found: $file"
    fi
done

echo ""
echo -e "${GREEN}Migration complete!${NC}"
echo ""
echo "Next steps:"
echo "1. Clear WordPress cache"
echo "2. Check plugin version in WordPress admin"
echo "3. Verify SMTP settings are visible"
echo "4. Test email sending"

