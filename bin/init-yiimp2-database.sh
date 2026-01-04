#!/bin/bash
#
# Yiimp2 Database Initialization Script
# 
# This script performs complete database initialization for Yiimp2:
# 1. Creates the yiimp2 database
# 2. Creates database user with appropriate privileges
# 3. Imports the consolidated schema (sql/yiimp2-init.sql)
# 4. Verifies all tables were created successfully
# 5. Runs data migration from old-yiimp.sql
# 6. Runs validation to ensure data integrity
# 7. Generates initialization report
#
# Usage: ./bin/init-yiimp2-database.sh [OPTIONS]
#
# Options:
#   --db-host HOST          Database host (default: localhost)
#   --db-name NAME          Database name (default: yiimp2)
#   --db-user USER          Database user (default: yiimp2)
#   --db-pass PASS          Database password (required)
#   --root-pass PASS        MySQL root password (required for db/user creation)
#   --schema-file FILE      Schema SQL file (default: sql/yiimp2-init.sql)
#   --source-file FILE      Source data file (default: sql/old-yiimp.sql)
#   --include-benchmarks    Include benchmarks tables in migration
#   --include-markets       Include markets tables in migration
#   --migration-mode MODE   Migration mode: skip, update, replace (default: skip)
#   --skip-migration        Skip data migration step
#   --skip-validation       Skip validation step
#   --report-file FILE      Report output file (default: log/yiimp2-init-report.txt)
#   --help                  Show this help message

set -e

# Default configuration
DB_HOST="localhost"
DB_NAME="yiimp2"
DB_USER="yiimp2"
DB_PASS=""
ROOT_PASS=""
SCHEMA_FILE="sql/yiimp2-init.sql"
SOURCE_FILE="sql/old-yiimp.sql"
INCLUDE_BENCHMARKS=0
INCLUDE_MARKETS=0
MIGRATION_MODE="skip"
SKIP_MIGRATION=0
SKIP_VALIDATION=0
REPORT_FILE="log/yiimp2-init-report.txt"
START_TIME=$(date +%s)

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --db-host)
            DB_HOST="$2"
            shift 2
            ;;
        --db-name)
            DB_NAME="$2"
            shift 2
            ;;
        --db-user)
            DB_USER="$2"
            shift 2
            ;;
        --db-pass)
            DB_PASS="$2"
            shift 2
            ;;
        --root-pass)
            ROOT_PASS="$2"
            shift 2
            ;;
        --schema-file)
            SCHEMA_FILE="$2"
            shift 2
            ;;
        --source-file)
            SOURCE_FILE="$2"
            shift 2
            ;;
        --include-benchmarks)
            INCLUDE_BENCHMARKS=1
            shift
            ;;
        --include-markets)
            INCLUDE_MARKETS=1
            shift
            ;;
        --migration-mode)
            MIGRATION_MODE="$2"
            shift 2
            ;;
        --skip-migration)
            SKIP_MIGRATION=1
            shift
            ;;
        --skip-validation)
            SKIP_VALIDATION=1
            shift
            ;;
        --report-file)
            REPORT_FILE="$2"
            shift 2
            ;;
        --help)
            grep '^#' "$0" | grep -v '#!/bin/bash' | sed 's/^# //'
            exit 0
            ;;
        *)
            echo -e "${RED}Error: Unknown option $1${NC}"
            exit 1
            ;;
    esac
done

# Validate required parameters
if [ -z "$DB_PASS" ]; then
    echo -e "${RED}Error: Database password is required (--db-pass)${NC}"
    exit 1
fi

if [ -z "$ROOT_PASS" ]; then
    echo -e "${RED}Error: MySQL root password is required (--root-pass)${NC}"
    exit 1
fi

if [ ! -f "$SCHEMA_FILE" ]; then
    echo -e "${RED}Error: Schema file not found: $SCHEMA_FILE${NC}"
    exit 1
fi

if [ $SKIP_MIGRATION -eq 0 ] && [ ! -f "$SOURCE_FILE" ]; then
    echo -e "${RED}Error: Source data file not found: $SOURCE_FILE${NC}"
    exit 1
fi

# Validate migration mode
if [[ ! "$MIGRATION_MODE" =~ ^(skip|update|replace)$ ]]; then
    echo -e "${RED}Error: Invalid migration mode '$MIGRATION_MODE'. Must be: skip, update, or replace${NC}"
    exit 1
fi

# Create log directory if it doesn't exist
mkdir -p "$(dirname "$REPORT_FILE")"

# Initialize report file
cat > "$REPORT_FILE" << EOF
================================================================================
Yiimp2 Database Initialization Report
================================================================================
Start Time: $(date)
Host: $DB_HOST
Database: $DB_NAME
User: $DB_USER
Schema File: $SCHEMA_FILE
Source File: $SOURCE_FILE
Migration Mode: $MIGRATION_MODE
Include Benchmarks: $INCLUDE_BENCHMARKS
Include Markets: $INCLUDE_MARKETS
Skip Migration: $SKIP_MIGRATION
Skip Validation: $SKIP_VALIDATION
================================================================================

EOF

# Logging function
log_report() {
    echo "$@" | tee -a "$REPORT_FILE"
}

log_step() {
    local step="$1"
    shift
    local message="$@"
    echo -e "\n${BOLD}${CYAN}[$step]${NC} ${message}"
    echo "" >> "$REPORT_FILE"
    echo "[$step] $message" >> "$REPORT_FILE"
    echo "----------------------------------------" >> "$REPORT_FILE"
}

log_success() {
    echo -e "${GREEN}✓${NC} $@"
    echo "✓ $@" >> "$REPORT_FILE"
}

log_error() {
    echo -e "${RED}✗${NC} $@"
    echo "✗ $@" >> "$REPORT_FILE"
}

log_info() {
    echo -e "${BLUE}→${NC} $@"
    echo "→ $@" >> "$REPORT_FILE"
}

log_warn() {
    echo -e "${YELLOW}⚠${NC} $@"
    echo "⚠ $@" >> "$REPORT_FILE"
}

# MySQL command wrappers
mysql_root() {
    mysql -h "$DB_HOST" -u root -p"$ROOT_PASS" -e "$1" 2>&1
}

mysql_db() {
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$1" 2>&1
}

mysql_import() {
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$1" 2>&1
}

# Get list of tables in database
get_tables() {
    mysql_db "SHOW TABLES" | tail -n +2
}

# Count tables in database
count_tables() {
    get_tables | wc -l
}

# Count records in a table
count_records() {
    local table="$1"
    mysql_db "SELECT COUNT(*) FROM \`$table\`" | tail -n 1
}

# Main initialization process
main() {
    echo -e "${BOLD}${BLUE}=========================================${NC}"
    echo -e "${BOLD}${BLUE}Yiimp2 Database Initialization${NC}"
    echo -e "${BOLD}${BLUE}=========================================${NC}"
    
    # Step 1: Test MySQL root connection
    log_step "STEP 1" "Testing MySQL root connection..."
    
    if ! mysql_root "SELECT 1" > /dev/null 2>&1; then
        log_error "Failed to connect to MySQL as root"
        log_report "Error: Could not connect to MySQL server with root credentials"
        exit 1
    fi
    
    log_success "MySQL root connection successful"
    
    # Step 2: Create database
    log_step "STEP 2" "Creating database: $DB_NAME..."
    
    # Check if database already exists
    DB_EXISTS=$(mysql_root "SHOW DATABASES LIKE '$DB_NAME'" | grep -c "$DB_NAME" || echo "0")
    
    if [ "$DB_EXISTS" -eq 1 ]; then
        log_warn "Database '$DB_NAME' already exists"
        log_info "Continuing with existing database..."
    else
        if mysql_root "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" > /dev/null 2>&1; then
            log_success "Database '$DB_NAME' created successfully"
        else
            log_error "Failed to create database '$DB_NAME'"
            exit 1
        fi
    fi
    
    # Step 3: Create database user
    log_step "STEP 3" "Creating database user: $DB_USER..."
    
    # Check if user already exists
    USER_EXISTS=$(mysql_root "SELECT COUNT(*) FROM mysql.user WHERE user='$DB_USER' AND host='%'" | tail -n 1)
    
    if [ "$USER_EXISTS" -gt 0 ]; then
        log_warn "User '$DB_USER' already exists"
        log_info "Updating user privileges..."
    else
        log_info "Creating new user '$DB_USER'..."
    fi
    
    # Create or update user with privileges
    if mysql_root "CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS'" > /dev/null 2>&1; then
        log_success "User created/verified"
    else
        log_error "Failed to create user"
        exit 1
    fi
    
    if mysql_root "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%'" > /dev/null 2>&1; then
        log_success "Privileges granted"
    else
        log_error "Failed to grant privileges"
        exit 1
    fi
    
    if mysql_root "FLUSH PRIVILEGES" > /dev/null 2>&1; then
        log_success "Privileges flushed"
    else
        log_warn "Failed to flush privileges (non-critical)"
    fi
    
    # Step 4: Test user connection
    log_step "STEP 4" "Testing database user connection..."
    
    if ! mysql_db "SELECT 1" > /dev/null 2>&1; then
        log_error "Failed to connect as user '$DB_USER'"
        exit 1
    fi
    
    log_success "User connection successful"
    
    # Step 5: Import schema
    log_step "STEP 5" "Importing database schema from: $SCHEMA_FILE..."
    
    log_info "Schema file size: $(du -h "$SCHEMA_FILE" | cut -f1)"
    
    BEFORE_TABLES=$(count_tables)
    log_info "Tables before import: $BEFORE_TABLES"
    
    log_info "Importing schema (this may take a moment)..."
    
    if mysql_import "$SCHEMA_FILE" > /dev/null 2>&1; then
        log_success "Schema imported successfully"
    else
        log_error "Failed to import schema"
        log_report "Error details: Check MySQL error log"
        exit 1
    fi
    
    AFTER_TABLES=$(count_tables)
    log_info "Tables after import: $AFTER_TABLES"
    log_success "Created $((AFTER_TABLES - BEFORE_TABLES)) tables"
    
    # Step 6: Verify tables
    log_step "STEP 6" "Verifying table creation..."
    
    # List of required tables
    REQUIRED_TABLES=("algos" "coins" "settings" "shares" "workers" "accounts" "blocks" "payouts")
    
    MISSING_TABLES=0
    for table in "${REQUIRED_TABLES[@]}"; do
        if get_tables | grep -q "^${table}$"; then
            log_success "Table exists: $table"
        else
            log_error "Missing table: $table"
            ((MISSING_TABLES++))
        fi
    done
    
    if [ $MISSING_TABLES -gt 0 ]; then
        log_error "Schema verification failed: $MISSING_TABLES missing table(s)"
        exit 1
    fi
    
    log_success "All required tables verified"
    
    # List all tables in report
    log_report ""
    log_report "Complete table list:"
    get_tables | while read table; do
        log_report "  - $table"
    done
    
    # Step 7: Run data migration
    if [ $SKIP_MIGRATION -eq 0 ]; then
        log_step "STEP 7" "Running data migration..."
        
        MIGRATION_ARGS=(
            "--db-host" "$DB_HOST"
            "--db-name" "$DB_NAME"
            "--db-user" "$DB_USER"
            "--db-pass" "$DB_PASS"
            "--source-file" "$SOURCE_FILE"
            "--mode" "$MIGRATION_MODE"
            "--log-file" "log/yiimp2-migration.log"
        )
        
        if [ $INCLUDE_BENCHMARKS -eq 1 ]; then
            MIGRATION_ARGS+=("--include-benchmarks")
        fi
        
        if [ $INCLUDE_MARKETS -eq 1 ]; then
            MIGRATION_ARGS+=("--include-markets")
        fi
        
        log_info "Running: ./bin/migrate-yiimp2-data.sh ${MIGRATION_ARGS[*]}"
        
        if ./bin/migrate-yiimp2-data.sh "${MIGRATION_ARGS[@]}"; then
            log_success "Data migration completed successfully"
            
            # Append migration log to report
            log_report ""
            log_report "Migration log excerpt:"
            tail -n 20 "log/yiimp2-migration.log" >> "$REPORT_FILE"
        else
            log_error "Data migration failed"
            log_report "See log/yiimp2-migration.log for details"
            exit 1
        fi
    else
        log_step "STEP 7" "Skipping data migration (--skip-migration)"
    fi
    
    # Step 8: Run validation
    if [ $SKIP_VALIDATION -eq 0 ] && [ $SKIP_MIGRATION -eq 0 ]; then
        log_step "STEP 8" "Running validation..."
        
        log_info "Validation is performed by the migration script"
        log_success "Validation completed (see migration log)"
    else
        log_step "STEP 8" "Skipping validation"
    fi
    
    # Step 9: Generate final report
    log_step "STEP 9" "Generating initialization report..."
    
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    log_report ""
    log_report "========================================"
    log_report "Final Database Statistics"
    log_report "========================================"
    log_report "Total tables: $(count_tables)"
    log_report ""
    log_report "Configuration tables:"
    log_report "  algos: $(count_records "algos")"
    log_report "  coins: $(count_records "coins")"
    log_report "  settings: $(count_records "settings")"
    log_report ""
    log_report "Operational tables (should be empty):"
    log_report "  shares: $(count_records "shares")"
    log_report "  workers: $(count_records "workers")"
    log_report "  accounts: $(count_records "accounts")"
    log_report "  blocks: $(count_records "blocks")"
    log_report "  payouts: $(count_records "payouts")"
    
    if [ $INCLUDE_BENCHMARKS -eq 1 ]; then
        log_report ""
        log_report "Optional benchmark tables:"
        log_report "  benchmarks: $(count_records "benchmarks")"
        log_report "  bench_chips: $(count_records "bench_chips")"
    fi
    
    if [ $INCLUDE_MARKETS -eq 1 ]; then
        log_report ""
        log_report "Optional market tables:"
        log_report "  markets: $(count_records "markets")"
        log_report "  market_history: $(count_records "market_history")"
    fi
    
    log_report ""
    log_report "========================================"
    log_report "Initialization Summary"
    log_report "========================================"
    log_report "Status: SUCCESS"
    log_report "Duration: ${DURATION}s"
    log_report "End Time: $(date)"
    log_report "========================================"
    
    # Display summary
    echo ""
    echo -e "${BOLD}${GREEN}=========================================${NC}"
    echo -e "${BOLD}${GREEN}Initialization Completed Successfully!${NC}"
    echo -e "${BOLD}${GREEN}=========================================${NC}"
    echo ""
    echo -e "${BLUE}Database:${NC} $DB_NAME"
    echo -e "${BLUE}User:${NC} $DB_USER"
    echo -e "${BLUE}Tables:${NC} $(count_tables)"
    echo -e "${BLUE}Duration:${NC} ${DURATION}s"
    echo ""
    echo -e "${BLUE}Configuration records:${NC}"
    echo -e "  Algorithms: $(count_records "algos")"
    echo -e "  Coins: $(count_records "coins")"
    echo -e "  Settings: $(count_records "settings")"
    echo ""
    echo -e "${BLUE}Report saved to:${NC} $REPORT_FILE"
    echo ""
    
    log_success "Yiimp2 database is ready for use!"
}

# Run main function
main

