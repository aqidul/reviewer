#!/bin/bash
# Phase 5 Database Setup Script
# This script creates all tables and initial data for Phase 5 features

echo "=========================================="
echo "Phase 5 Database Setup for ReviewFlow"
echo "=========================================="
echo ""

# Database credentials
# IMPORTANT: Update these values or use environment variables
DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-reviewflow_user}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-reviewflow}"

# Check if password is set
if [ -z "$DB_PASS" ]; then
    echo "ERROR: Database password not set."
    echo "Set DB_PASS environment variable: export DB_PASS='your_password'"
    exit 1
fi

# MySQL command
MYSQL_CMD="mysql -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME"

echo "Applying Phase 5 migrations..."
echo ""

# Function to run SQL file
run_migration() {
    local file=$1
    echo "Running migration: $file"
    if $MYSQL_CMD < "$file"; then
        echo "✓ $file applied successfully"
    else
        echo "✗ Error applying $file"
        return 1
    fi
    echo ""
}

# Run all Phase 5 migrations
run_migration "migrations/phase5_quality.sql"
run_migration "migrations/phase5_2fa.sql"
run_migration "migrations/phase5_pwa.sql"
run_migration "migrations/phase5_reports.sql"
run_migration "migrations/phase5_languages.sql"

echo "=========================================="
echo "Database setup completed!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Verify all tables were created successfully"
echo "2. Review the admin panels:"
echo "   - admin/review-quality.php"
echo "   - admin/2fa-settings.php"
echo "   - admin/languages.php"
echo "   - admin/report-builder.php"
echo "3. Test 2FA setup at user/security-settings.php"
echo "4. Configure PWA settings if needed"
echo ""
