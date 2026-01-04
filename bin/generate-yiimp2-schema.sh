#!/bin/bash

# Yiimp2 Schema Generation Script
# This script generates a consolidated SQL initialization file for Yiimp2
# by combining the base schema, all migrations, and the decimal column definition

set -e

# Configuration
BASE_SCHEMA="sql/2024-03-06-complete_export.sql.gz"
OLD_YIIMP_SQL="sql/old-yiimp.sql"
OUTPUT_FILE="sql/yiimp2-init.sql"
SQL_DIR="sql"
TEMP_DIR="/tmp/yiimp2-schema-$$"
LOG_FILE="log/yiimp2-schema-generation.log"
START_TIME=$(date +%s)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Create log directory if it doesn't exist
mkdir -p "$(dirname "$LOG_FILE")"

# Initialize log file with header
cat > "$LOG_FILE" << EOF
================================================================================
Yiimp2 Schema Generation Log
================================================================================
Start Time: $(date)
Base Schema: $BASE_SCHEMA
Old Yiimp SQL: $OLD_YIIMP_SQL
Output File: $OUTPUT_FILE
SQL Directory: $SQL_DIR
================================================================================

EOF

# Logging functions that write to both console and log file
log_info() {
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo -e "${GREEN}[INFO]${NC} $1"
    echo "[$timestamp] [INFO] $1" >> "$LOG_FILE"
}

log_warn() {
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo -e "${YELLOW}[WARN]${NC} $1"
    echo "[$timestamp] [WARN] $1" >> "$LOG_FILE"
}

log_error() {
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo -e "${RED}[ERROR]${NC} $1"
    echo "[$timestamp] [ERROR] $1" >> "$LOG_FILE"
}

log_debug() {
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo -e "${BLUE}[DEBUG]${NC} $1"
    echo "[$timestamp] [DEBUG] $1" >> "$LOG_FILE"
}

# Cleanup function
cleanup() {
    if [ -d "$TEMP_DIR" ]; then
        log_debug "Cleaning up temporary directory: $TEMP_DIR"
        rm -rf "$TEMP_DIR"
    fi
}

trap cleanup EXIT

# Create temporary directory
log_debug "Creating temporary directory: $TEMP_DIR"
mkdir -p "$TEMP_DIR"

log_info "Starting Yiimp2 schema generation..."
log_info "Configuration parameters:"
log_info "  Base schema: $BASE_SCHEMA"
log_info "  Old Yiimp SQL: $OLD_YIIMP_SQL"
log_info "  Output file: $OUTPUT_FILE"
log_info "  SQL directory: $SQL_DIR"
log_info "  Log file: $LOG_FILE"

# Check if base schema exists
log_info "Validating input files..."
if [ ! -f "$BASE_SCHEMA" ]; then
    log_error "Base schema file not found: $BASE_SCHEMA"
    log_error "Please ensure the file exists before running this script"
    exit 1
fi
log_info "✓ Base schema file found: $BASE_SCHEMA"

# Check if old-yiimp.sql exists
if [ ! -f "$OLD_YIIMP_SQL" ]; then
    log_error "Old Yiimp SQL file not found: $OLD_YIIMP_SQL"
    log_error "Please ensure the file exists before running this script"
    exit 1
fi
log_info "✓ Old Yiimp SQL file found: $OLD_YIIMP_SQL"

# Extract base schema
log_info "Extracting base schema from $BASE_SCHEMA..."
if gunzip -c "$BASE_SCHEMA" > "$TEMP_DIR/base_schema.sql" 2>> "$LOG_FILE"; then
    BASE_SCHEMA_SIZE=$(wc -l < "$TEMP_DIR/base_schema.sql")
    log_info "✓ Base schema extracted successfully ($BASE_SCHEMA_SIZE lines)"
else
    log_error "Failed to extract base schema"
    exit 1
fi

# Get list of migration files in chronological order
log_info "Finding migration files in chronological order..."
MIGRATION_FILES=$(find "$SQL_DIR" -maxdepth 1 -name "20*.sql" -type f | sort)

if [ -z "$MIGRATION_FILES" ]; then
    log_warn "No migration files found in $SQL_DIR"
    MIGRATION_COUNT=0
else
    MIGRATION_COUNT=$(echo "$MIGRATION_FILES" | wc -l)
    log_info "Found $MIGRATION_COUNT migration file(s)"
    echo "$MIGRATION_FILES" | while read -r mf; do
        log_debug "  - $(basename "$mf")"
    done
fi

# Extract decimal column definition from old-yiimp.sql
log_info "Extracting decimal column definition from $OLD_YIIMP_SQL..."

# Find the line with decimals column in the coins table CREATE statement
DECIMAL_LINE=$(grep -n "^\s*\`decimals\`" "$OLD_YIIMP_SQL" | head -1 | cut -d: -f1)

if [ -z "$DECIMAL_LINE" ]; then
    log_error "Could not find decimals column definition in $OLD_YIIMP_SQL"
    log_error "Expected to find a line matching pattern: ^\s*\`decimals\`"
    exit 1
fi

# Extract the decimal column definition
DECIMAL_DEFINITION=$(sed -n "${DECIMAL_LINE}p" "$OLD_YIIMP_SQL" | sed 's/,$//')

log_info "✓ Found decimal column definition at line $DECIMAL_LINE"
log_debug "  Definition: $DECIMAL_DEFINITION"

# Start building the consolidated SQL file
log_info "Building consolidated SQL file: $OUTPUT_FILE..."

cat > "$OUTPUT_FILE" << 'EOF'
-- ============================================================================
-- Yiimp2 Database Initialization Script
-- Generated automatically by bin/generate-yiimp2-schema.sh
-- 
-- This file contains:
-- 1. Base schema from sql/2024-03-06-complete_export.sql.gz
-- 2. All migration files applied in chronological order
-- 3. Decimal column addition to coins table from sql/old-yiimp.sql
--
-- Usage: mysql -u <user> -p <database> < sql/yiimp2-init.sql
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================================================
-- SECTION 1: Base Schema
-- Source: sql/2024-03-06-complete_export.sql.gz
-- ============================================================================

EOF

# Append base schema (skip the header comments and SET statements as we already added them)
log_info "Appending base schema..."

# First, extract all table names from the base schema and add DROP TABLE IF EXISTS statements
log_info "Adding DROP TABLE IF EXISTS statements for idempotency..."
TABLE_NAMES=$(grep -oP "CREATE TABLE( IF NOT EXISTS)? \`\K[^\`]+" "$TEMP_DIR/base_schema.sql" | sort -u)
TABLE_COUNT=$(echo "$TABLE_NAMES" | wc -w)

log_info "Found $TABLE_COUNT tables in base schema"

for table_name in $TABLE_NAMES; do
    echo "DROP TABLE IF EXISTS \`$table_name\`;" >> "$OUTPUT_FILE"
    log_debug "  Added DROP statement for table: $table_name"
done

echo "" >> "$OUTPUT_FILE"
echo "-- Base schema tables" >> "$OUTPUT_FILE"
echo "" >> "$OUTPUT_FILE"

# Now append the base schema (remove database qualifiers like `yiimp`.`table`)
log_info "Appending base schema content (removing database qualifiers)..."
grep -v "^--" "$TEMP_DIR/base_schema.sql" | \
    grep -v "^/\*!" | \
    grep -v "^SET SQL_MODE" | \
    grep -v "^START TRANSACTION" | \
    grep -v "^SET time_zone" | \
    sed 's/`yiimp`\.//g' | \
    sed 's/`yaamp`\.//g' >> "$OUTPUT_FILE"

log_info "✓ Base schema appended successfully"

# Add migrations section header
cat >> "$OUTPUT_FILE" << 'EOF'

-- ============================================================================
-- SECTION 2: Migration Files (Applied in Chronological Order)
-- ============================================================================

EOF

# Process each migration file
log_info "Processing migration files..."
MIGRATION_COUNTER=0
for migration_file in $MIGRATION_FILES; do
    MIGRATION_COUNTER=$((MIGRATION_COUNTER + 1))
    filename=$(basename "$migration_file")
    
    log_info "[$MIGRATION_COUNTER/$MIGRATION_COUNT] Processing migration: $filename"
    
    # Get file size for logging
    MIGRATION_SIZE=$(wc -l < "$migration_file")
    log_debug "  Migration file size: $MIGRATION_SIZE lines"
    
    cat >> "$OUTPUT_FILE" << EOF

-- ----------------------------------------------------------------------------
-- Migration: $filename
-- ----------------------------------------------------------------------------

EOF
    
    # Append migration content (skip comments, remove database qualifiers)
    if grep -v "^--" "$migration_file" | \
        sed 's/`yiimp`\.//g' | \
        sed 's/`yaamp`\.//g' >> "$OUTPUT_FILE" 2>> "$LOG_FILE"; then
        log_info "  ✓ Migration $filename processed successfully"
    else
        log_warn "  ⚠ Migration $filename processed with warnings (check log)"
    fi
done

log_info "✓ Processed $MIGRATION_COUNTER migration file(s)"

# Add decimal column section
cat >> "$OUTPUT_FILE" << 'EOF'

-- ============================================================================
-- SECTION 3: Decimal Column Addition to Coins Table
-- Source: sql/old-yiimp.sql
-- ============================================================================

-- Add decimals column to coins table if it doesn't exist
-- This column stores the number of decimal places for each coin

EOF

# Check if the base schema already has the decimals column
log_info "Checking if decimals column already exists in base schema..."
if grep -q "decimals" "$TEMP_DIR/base_schema.sql"; then
    log_info "✓ Decimals column already exists in base schema, skipping ALTER statement"
    cat >> "$OUTPUT_FILE" << 'EOF'
-- Note: decimals column already exists in base schema, no ALTER needed

EOF
else
    log_info "Decimals column not found in base schema, adding ALTER statement"
    cat >> "$OUTPUT_FILE" << EOF
ALTER TABLE \`coins\` ADD COLUMN $DECIMAL_DEFINITION AFTER \`symbol\`;

EOF
    log_info "✓ ALTER statement added for decimals column"
fi

# Add footer
cat >> "$OUTPUT_FILE" << 'EOF'

-- ============================================================================
-- Schema Generation Complete
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
EOF

# Calculate duration and file statistics
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))
FILE_SIZE=$(du -h "$OUTPUT_FILE" | cut -f1)
FILE_LINES=$(wc -l < "$OUTPUT_FILE")

# Log summary
log_info "========================================="
log_info "Schema generation complete!"
log_info "========================================="
log_info "Summary:"
log_info "  Output file: $OUTPUT_FILE"
log_info "  File size: $FILE_SIZE"
log_info "  Total lines: $FILE_LINES"
log_info "  Tables: $TABLE_COUNT"
log_info "  Migrations applied: $MIGRATION_COUNTER"
log_info "  Duration: ${DURATION}s"
log_info "  Log file: $LOG_FILE"
log_info "========================================="

# Write summary to log file
cat >> "$LOG_FILE" << EOF

================================================================================
Schema Generation Summary
================================================================================
Status: SUCCESS
End Time: $(date)
Duration: ${DURATION}s
Output File: $OUTPUT_FILE
File Size: $FILE_SIZE
Total Lines: $FILE_LINES
Tables: $TABLE_COUNT
Migrations Applied: $MIGRATION_COUNTER
================================================================================
EOF

exit 0
