#!/bin/bash

###############################################################################
# Phase 6 Installation Script
# Automates the installation of Phase 6 features
###############################################################################

set -e  # Exit on error

echo "=========================================="
echo "Phase 6: Advanced Enterprise Features"
echo "Installation Script"
echo "=========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
DB_USER="reviewflow_user"
DB_NAME="reviewflow"
MIGRATIONS_DIR="./migrations"

echo -e "${YELLOW}This script will:${NC}"
echo "  1. Run all Phase 6 database migrations"
echo "  2. Create required directories"
echo "  3. Set proper permissions"
echo "  4. Verify installation"
echo ""

read -p "Continue with installation? (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Installation cancelled."
    exit 1
fi

echo ""
echo "=========================================="
echo "Step 1: Database Migrations"
echo "=========================================="

# Prompt for database password
read -sp "Enter MySQL password for $DB_USER: " DB_PASS
echo ""

# Function to run migration
run_migration() {
    local file=$1
    echo -e "${YELLOW}Running migration: $file${NC}"
    
    if mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$file" 2>/dev/null; then
        echo -e "${GREEN}✓ Success: $file${NC}"
        return 0
    else
        echo -e "${RED}✗ Failed: $file${NC}"
        return 1
    fi
}

# Run all Phase 6 migrations
MIGRATIONS=(
    "phase6_email_marketing.sql"
    "phase6_tickets.sql"
    "phase6_seller_enhancements.sql"
    "phase6_notifications.sql"
    "phase6_seo.sql"
    "phase6_api.sql"
)

FAILED=0
for migration in "${MIGRATIONS[@]}"; do
    if [ -f "$MIGRATIONS_DIR/$migration" ]; then
        if ! run_migration "$MIGRATIONS_DIR/$migration"; then
            FAILED=$((FAILED + 1))
        fi
    else
        echo -e "${RED}✗ File not found: $migration${NC}"
        FAILED=$((FAILED + 1))
    fi
done

echo ""
if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}All migrations completed successfully!${NC}"
else
    echo -e "${RED}$FAILED migration(s) failed. Please check the errors above.${NC}"
    exit 1
fi

echo ""
echo "=========================================="
echo "Step 2: Creating Directories"
echo "=========================================="

# Create required directories
DIRECTORIES=(
    "uploads/tickets"
    "cache/email_templates"
    "cache/api"
)

for dir in "${DIRECTORIES[@]}"; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
        echo -e "${GREEN}✓ Created: $dir${NC}"
    else
        echo -e "${YELLOW}- Exists: $dir${NC}"
    fi
done

echo ""
echo "=========================================="
echo "Step 3: Setting Permissions"
echo "=========================================="

# Set permissions
chmod 755 uploads/tickets
chmod 755 cache/email_templates
chmod 755 cache/api

echo -e "${GREEN}✓ Permissions set${NC}"

echo ""
echo "=========================================="
echo "Step 4: Verification"
echo "=========================================="

# Verify tables exist
echo "Verifying database tables..."

TABLES=(
    "email_campaigns"
    "support_tickets"
    "seller_order_templates"
    "notification_categories"
    "seo_settings"
    "api_keys"
)

MISSING=0
for table in "${TABLES[@]}"; do
    if mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "DESCRIBE $table" &>/dev/null; then
        echo -e "${GREEN}✓ Table exists: $table${NC}"
    else
        echo -e "${RED}✗ Table missing: $table${NC}"
        MISSING=$((MISSING + 1))
    fi
done

echo ""
if [ $MISSING -eq 0 ]; then
    echo -e "${GREEN}✓ All tables verified successfully!${NC}"
else
    echo -e "${RED}✗ $MISSING table(s) missing. Please check the installation.${NC}"
    exit 1
fi

echo ""
echo "=========================================="
echo "Installation Summary"
echo "=========================================="
echo -e "${GREEN}✓ Database migrations: Completed${NC}"
echo -e "${GREEN}✓ Directories: Created${NC}"
echo -e "${GREEN}✓ Permissions: Set${NC}"
echo -e "${GREEN}✓ Verification: Passed${NC}"
echo ""
echo "=========================================="
echo -e "${GREEN}Phase 6 Installation Complete!${NC}"
echo "=========================================="
echo ""
echo "Next Steps:"
echo "1. Configure JWT_SECRET in includes/config.php"
echo "2. Set up email service (SMTP/SendGrid)"
echo "3. Configure push notification service"
echo "4. Test API endpoints"
echo "5. Review PHASE6_IMPLEMENTATION_COMPLETE.md"
echo ""
echo "For detailed documentation, see:"
echo "  - PHASE6_IMPLEMENTATION_COMPLETE.md"
echo "  - Individual README files in each directory"
echo ""
echo "API Documentation available at:"
echo "  - /admin/api-settings.php"
echo ""
echo -e "${YELLOW}Important Security Notes:${NC}"
echo "  - Change JWT_SECRET in production"
echo "  - Review API rate limits"
echo "  - Configure CORS settings"
echo "  - Enable HTTPS"
echo ""
echo "Installation log saved to: phase6_install.log"
echo ""
