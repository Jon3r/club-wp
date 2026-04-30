#!/bin/bash
# PJJA Trial Booking - WordPress Plugin Installation Helper
# This script helps you package the plugin for WordPress installation

echo "🥋 PJJA Trial Booking - WordPress Plugin Installer"
echo "=================================================="
echo ""

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

echo "📁 Current directory: $SCRIPT_DIR"
echo ""

# Check if we're in the right directory
if [ ! -f "pjja-trial-booking.php" ]; then
    echo "❌ Error: pjja-trial-booking.php not found!"
    echo "   Make sure you're running this script from the plugin directory."
    exit 1
fi

echo "✅ Plugin main file found"
echo ""

# Check required directories
echo "📋 Checking plugin structure..."
REQUIRED_DIRS=("includes" "admin" "templates" "assets/css" "assets/js" "assets/images")
ALL_GOOD=true

for dir in "${REQUIRED_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        echo "  ✅ $dir"
    else
        echo "  ❌ $dir (missing)"
        ALL_GOOD=false
    fi
done

if [ "$ALL_GOOD" = false ]; then
    echo ""
    echo "❌ Some required directories are missing!"
    exit 1
fi

echo ""
echo "✅ All required directories present"
echo ""

# Offer to create a ZIP file
echo "📦 Would you like to create a ZIP file for WordPress installation?"
echo "   This will create: pjja-trial-booking.zip in the parent directory"
echo ""
read -p "Create ZIP file? (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    cd ..
    PLUGIN_DIR=$(basename "$SCRIPT_DIR")
    ZIP_FILE="pjja-trial-booking.zip"
    
    echo "📦 Creating ZIP file..."
    
    # Remove old ZIP if exists
    if [ -f "$ZIP_FILE" ]; then
        rm "$ZIP_FILE"
        echo "   Removed old ZIP file"
    fi
    
    # Create ZIP excluding unnecessary files
    zip -r "$ZIP_FILE" "$PLUGIN_DIR" \
        -x "*.git*" \
        -x "*node_modules/*" \
        -x "*/api/*" \
        -x "*.md" \
        -x "*/DELETE_THESE_FILES.txt" \
        -x "*/vercel.json" \
        -x "*/package*.json" \
        -x "*/.DS_Store" \
        -x "*/install-instructions.sh"
    
    if [ $? -eq 0 ]; then
        echo ""
        echo "✅ ZIP file created successfully!"
        echo "📍 Location: $(pwd)/$ZIP_FILE"
        echo ""
        echo "📤 Next steps:"
        echo "   1. Go to your WordPress admin panel"
        echo "   2. Navigate to Plugins → Add New → Upload Plugin"
        echo "   3. Upload: $ZIP_FILE"
        echo "   4. Click 'Install Now' and then 'Activate'"
    else
        echo "❌ Failed to create ZIP file"
        exit 1
    fi
else
    echo ""
    echo "📋 Manual Installation Instructions:"
    echo "   1. Copy this entire directory to: wp-content/plugins/"
    echo "   2. Rename to: pjja-trial-booking"
    echo "   3. Activate in WordPress admin: Plugins → Installed Plugins"
fi

echo ""
echo "=================================================="
echo "📚 Documentation:"
echo "   • README-WORDPRESS.md - Full plugin documentation"
echo "   • CONVERSION-SUMMARY.md - Conversion details"
echo "   • GA4-SETUP.md - GA4 configuration guide"
echo ""
echo "⚙️  After installation:"
echo "   1. Configure settings: WordPress Admin → Trial Booking → Settings"
echo "   2. Add to page: Use shortcode [pjja_trial_booking]"
echo "   3. Test the form and check Dashboard for stats"
echo ""
echo "🎉 Ready to go! Good luck with your trial bookings!"

