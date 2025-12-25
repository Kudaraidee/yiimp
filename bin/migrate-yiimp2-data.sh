#!/bin/bash
#
# Yiimp2 Data Migration Script
# Migrates configuration data from old-yiimp.sql to yiimp2 database
#
# This script migrates ONLY configuration tables (coins, algos, settings).
# Operational/runtime data (shares, workers, accounts, blocks, payouts, etc.) 
# is explicitly EXCLUDED to provide a clean slate for the Yiimp2 system.
#
# Usage: ./bin/migrate-yiimp2-data.sh [OPTIONS]
#
# Options:
#   --db-host HOST          Database host (default: localhost)
#   --db-name NAME          Database name (default: yiimp2)
#   --db-user USER          Database user (default: yiimp2)
#   --db-pass PASS          Database password (required)
#   --source-file FILE      Source SQL file (default: sql/old-yiimp.sql)
#   --include-benchmarks    Include benchmarks and bench_chips tables
#   --include-markets       Include markets and market_history tables
#   --mode MODE             Idempotency mode: skip, update, replace (default: skip)
#   --log-file FILE         Log file path (default: log/yiimp2-migration.log)
#   --help                  Show this help message

set -e

# Default configuration
DB_HOST="localhost"
DB_NAME="yiimp2"
DB_USER="yiimp2"
DB_PASS=""
SOURCE_FILE="sql/old-yiimp.sql"
INCLUDE_BENCHMARKS=0
INCLUDE_MARKETS=0
MODE="skip"
LOG_FILE="log/yiimp2-migration.log"
START_TIME=$(date +%s)

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
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
        --mode)
            MODE="$2"
            shift 2
            ;;
        --log-file)
            LOG_FILE="$2"
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

if [ ! -f "$SOURCE_FILE" ]; then
    echo -e "${RED}Error: Source file not found: $SOURCE_FILE${NC}"
    exit 1
fi

# Validate mode
if [[ ! "$MODE" =~ ^(skip|update|replace)$ ]]; then
    echo -e "${RED}Error: Invalid mode '$MODE'. Must be: skip, update, or replace${NC}"
    exit 1
fi

# Create log directory if it doesn't exist
mkdir -p "$(dirname "$LOG_FILE")"

# Logging function
log() {
    local level="$1"
    shift
    local message="$@"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [$level] $message" | tee -a "$LOG_FILE"
}

log_console() {
    local color="$1"
    shift
    local message="$@"
    echo -e "${color}${message}${NC}"
    log "INFO" "$message"
}

# MySQL command wrapper
mysql_exec() {
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$1" 2>&1
}

# Extract INSERT statements for a specific table from SQL file
extract_table_inserts() {
    local table_name="$1"
    local sql_file="$2"
    
    # Extract INSERT statements for the table
    # This handles multi-line INSERT statements
    awk -v table="$table_name" '
        /INSERT INTO `'"$table_name"'`/ {
            in_insert=1
            insert_stmt=$0
            next
        }
        in_insert {
            insert_stmt=insert_stmt "\n" $0
            if ($0 ~ /;$/) {
                print insert_stmt
                in_insert=0
                insert_stmt=""
            }
        }
    ' "$sql_file"
}

# Count records in a table
count_records() {
    local table="$1"
    mysql_exec "SELECT COUNT(*) FROM \`$table\`" | tail -n 1
}

# Count records in source SQL file for a table
count_source_records() {
    local table="$1"
    local sql_file="$2"
    
    # Count INSERT statements for the table
    extract_table_inserts "$table" "$sql_file" | grep -c "^INSERT" || echo "0"
}

# Get all IDs from a table
get_table_ids() {
    local table="$1"
    local id_column="${2:-id}"
    
    mysql_exec "SELECT \`$id_column\` FROM \`$table\` ORDER BY \`$id_column\`" | tail -n +2
}

# Get all IDs from source SQL file for a table
get_source_ids() {
    local table="$1"
    local sql_file="$2"
    
    # Extract INSERT statements and parse IDs (first value in VALUES clause)
    extract_table_inserts "$table" "$sql_file" | \
        grep -oP "VALUES\s*\(\s*\K\d+" | sort -n | uniq
}

# Get all setting parameters from source SQL file
get_source_settings() {
    local sql_file="$1"
    
    # Extract setting parameters (first string value in VALUES clause for settings table)
    extract_table_inserts "settings" "$sql_file" | \
        grep -oP "VALUES\s*\(\s*'\K[^']+(?=')" | sort | uniq
}

# Get all setting parameters from database
get_db_settings() {
    mysql_exec "SELECT param FROM settings ORDER BY param" | tail -n +2
}

# Check foreign key integrity for coins referencing algos
check_foreign_key_integrity() {
    log_console "$BLUE" "\nChecking foreign key integrity..."
    
    # Find coins that reference non-existent algorithms
    local invalid_refs=$(mysql_exec "
        SELECT c.id, c.name, c.algo 
        FROM coins c 
        LEFT JOIN algos a ON c.algo = a.name 
        WHERE a.name IS NULL AND c.algo IS NOT NULL AND c.algo != ''
    " | tail -n +2)
    
    if [ -n "$invalid_refs" ]; then
        log "ERROR" "Foreign key integrity violation detected:"
        log "ERROR" "$invalid_refs"
        return 1
    else
        log_console "$GREEN" "✓ Foreign key integrity check passed"
        return 0
    fi
}

# Validate migration results
validate_migration() {
    local source_file="$1"
    local validation_errors=0
    
    log_console "$BLUE" "\n========================================="
    log_console "$BLUE" "Validation Report"
    log_console "$BLUE" "========================================="
    
    # Validate required tables
    local tables=("algos" "coins" "settings")
    
    # Add optional tables if they were migrated
    if [ $INCLUDE_BENCHMARKS -eq 1 ]; then
        tables+=("benchmarks" "bench_chips")
    fi
    
    if [ $INCLUDE_MARKETS -eq 1 ]; then
        tables+=("markets" "market_history")
    fi
    
    # 1. Compare record counts
    log_console "$BLUE" "\n1. Record Count Validation"
    log_console "$BLUE" "-------------------------------------------"
    
    for table in "${tables[@]}"; do
        local source_count=$(count_source_records "$table" "$source_file")
        local dest_count=$(count_records "$table")
        
        log_console "$BLUE" "Table: $table"
        log_console "$BLUE" "  Source records: $source_count"
        log_console "$BLUE" "  Destination records: $dest_count"
        
        if [ "$source_count" -eq "$dest_count" ]; then
            log_console "$GREEN" "  ✓ Count matches"
        else
            log_console "$YELLOW" "  ⚠ Count mismatch (difference: $((dest_count - source_count)))"
            if [ "$MODE" != "replace" ]; then
                log "WARN" "Count mismatch for $table may be expected in '$MODE' mode"
            else
                ((validation_errors++))
                log "ERROR" "Count mismatch for $table in replace mode"
            fi
        fi
        echo ""
    done
    
    # 2. Verify coin IDs exist
    log_console "$BLUE" "2. Coin ID Validation"
    log_console "$BLUE" "-------------------------------------------"
    
    local source_coin_ids=$(get_source_ids "coins" "$source_file")
    local dest_coin_ids=$(get_table_ids "coins" "id")
    
    local missing_coins=0
    for coin_id in $source_coin_ids; do
        if ! echo "$dest_coin_ids" | grep -q "^${coin_id}$"; then
            log "ERROR" "Missing coin ID: $coin_id"
            ((missing_coins++))
            ((validation_errors++))
        fi
    done
    
    if [ $missing_coins -eq 0 ]; then
        log_console "$GREEN" "✓ All coin IDs from source exist in destination"
    else
        log_console "$RED" "✗ Missing $missing_coins coin ID(s)"
    fi
    
    # 3. Verify algorithm IDs exist
    log_console "$BLUE" "\n3. Algorithm ID Validation"
    log_console "$BLUE" "-------------------------------------------"
    
    local source_algo_ids=$(get_source_ids "algos" "$source_file")
    local dest_algo_ids=$(get_table_ids "algos" "id")
    
    local missing_algos=0
    for algo_id in $source_algo_ids; do
        if ! echo "$dest_algo_ids" | grep -q "^${algo_id}$"; then
            log "ERROR" "Missing algorithm ID: $algo_id"
            ((missing_algos++))
            ((validation_errors++))
        fi
    done
    
    if [ $missing_algos -eq 0 ]; then
        log_console "$GREEN" "✓ All algorithm IDs from source exist in destination"
    else
        log_console "$RED" "✗ Missing $missing_algos algorithm ID(s)"
    fi
    
    # 4. Verify setting parameters exist
    log_console "$BLUE" "\n4. Settings Parameter Validation"
    log_console "$BLUE" "-------------------------------------------"
    
    local source_settings=$(get_source_settings "$source_file")
    local dest_settings=$(get_db_settings)
    
    local missing_settings=0
    for param in $source_settings; do
        if ! echo "$dest_settings" | grep -q "^${param}$"; then
            log "ERROR" "Missing setting parameter: $param"
            ((missing_settings++))
            ((validation_errors++))
        fi
    done
    
    if [ $missing_settings -eq 0 ]; then
        log_console "$GREEN" "✓ All setting parameters from source exist in destination"
    else
        log_console "$RED" "✗ Missing $missing_settings setting parameter(s)"
    fi
    
    # 5. Check foreign key integrity
    log_console "$BLUE" "\n5. Foreign Key Integrity Validation"
    log_console "$BLUE" "-------------------------------------------"
    
    if ! check_foreign_key_integrity; then
        ((validation_errors++))
    fi
    
    # 6. Generate validation summary
    log_console "$BLUE" "\n========================================="
    if [ $validation_errors -eq 0 ]; then
        log_console "$GREEN" "✓ Validation completed successfully!"
        log_console "$GREEN" "All data integrity checks passed."
        log "INFO" "Validation: PASSED"
        return 0
    else
        log_console "$RED" "✗ Validation completed with $validation_errors error(s)"
        log_console "$RED" "Please review the log file for details: $LOG_FILE"
        log "ERROR" "Validation: FAILED with $validation_errors error(s)"
        return 1
    fi
}

# Migrate a table
migrate_table() {
    local table="$1"
    local source_file="$2"
    
    log_console "$BLUE" "Migrating table: $table"
    
    # Extract INSERT statements
    local temp_file=$(mktemp)
    extract_table_inserts "$table" "$source_file" > "$temp_file"
    
    if [ ! -s "$temp_file" ]; then
        log "WARN" "No INSERT statements found for table: $table"
        rm "$temp_file"
        return 0
    fi
    
    # Count source records
    local source_count=$(grep -c "^INSERT" "$temp_file" || echo "0")
    log "INFO" "Found $source_count INSERT statement(s) for table: $table"
    
    # Handle idempotency based on mode
    case "$MODE" in
        replace)
            log "INFO" "Mode: replace - Truncating table $table"
            mysql_exec "TRUNCATE TABLE \`$table\`" || {
                log "ERROR" "Failed to truncate table: $table"
                rm "$temp_file"
                return 1
            }
            ;;
        skip)
            log "INFO" "Mode: skip - Will skip existing records"
            ;;
        update)
            log "INFO" "Mode: update - Will update existing records"
            ;;
    esac
    
    # Import data
    local before_count=$(count_records "$table")
    log "INFO" "Records before migration: $before_count"
    
    # Process INSERT statements
    local success_count=0
    local error_count=0
    
    while IFS= read -r insert_stmt; do
        if [ -z "$insert_stmt" ]; then
            continue
        fi
        
        # For skip mode, use INSERT IGNORE
        # For update mode, use REPLACE INTO
        if [ "$MODE" = "skip" ]; then
            insert_stmt=$(echo "$insert_stmt" | sed 's/^INSERT INTO/INSERT IGNORE INTO/')
        elif [ "$MODE" = "update" ]; then
            insert_stmt=$(echo "$insert_stmt" | sed 's/^INSERT INTO/REPLACE INTO/')
        fi
        
        if mysql_exec "$insert_stmt" > /dev/null 2>&1; then
            ((success_count++))
        else
            ((error_count++))
            log "ERROR" "Failed to execute INSERT for table $table"
        fi
    done < "$temp_file"
    
    local after_count=$(count_records "$table")
    local migrated=$((after_count - before_count))
    
    log "INFO" "Records after migration: $after_count"
    log "INFO" "Successfully migrated: $success_count statement(s)"
    if [ $error_count -gt 0 ]; then
        log "WARN" "Failed: $error_count statement(s)"
    fi
    log "INFO" "Net records added: $migrated"
    
    rm "$temp_file"
    
    log_console "$GREEN" "✓ Completed migration for table: $table"
    return 0
}

# Main migration process
main() {
    log_console "$BLUE" "========================================="
    log_console "$BLUE" "Yiimp2 Data Migration"
    log_console "$BLUE" "========================================="
    log "INFO" "Start time: $(date)"
    log "INFO" "Configuration:"
    log "INFO" "  Database Host: $DB_HOST"
    log "INFO" "  Database Name: $DB_NAME"
    log "INFO" "  Database User: $DB_USER"
    log "INFO" "  Source File: $SOURCE_FILE"
    log "INFO" "  Include Benchmarks: $INCLUDE_BENCHMARKS"
    log "INFO" "  Include Markets: $INCLUDE_MARKETS"
    log "INFO" "  Idempotency Mode: $MODE"
    log "INFO" "  Log File: $LOG_FILE"
    
    # Test database connection
    log_console "$BLUE" "Testing database connection..."
    if ! mysql_exec "SELECT 1" > /dev/null 2>&1; then
        log_console "$RED" "✗ Failed to connect to database"
        log "ERROR" "Database connection failed"
        exit 1
    fi
    log_console "$GREEN" "✓ Database connection successful"
    
    # Migrate required tables
    log_console "$BLUE" "\nMigrating required tables..."
    
    migrate_table "algos" "$SOURCE_FILE" || exit 1
    migrate_table "coins" "$SOURCE_FILE" || exit 1
    migrate_table "settings" "$SOURCE_FILE" || exit 1
    
    # Migrate optional tables
    if [ $INCLUDE_BENCHMARKS -eq 1 ]; then
        log_console "$BLUE" "\nMigrating optional benchmark tables..."
        migrate_table "benchmarks" "$SOURCE_FILE" || exit 1
        migrate_table "bench_chips" "$SOURCE_FILE" || exit 1
    fi
    
    if [ $INCLUDE_MARKETS -eq 1 ]; then
        log_console "$BLUE" "\nMigrating optional market tables..."
        migrate_table "markets" "$SOURCE_FILE" || exit 1
        migrate_table "market_history" "$SOURCE_FILE" || exit 1
    fi
    
    # Run validation
    if ! validate_migration "$SOURCE_FILE"; then
        log_console "$RED" "\nMigration completed but validation failed!"
        log_console "$RED" "Please review the validation report above."
        exit 1
    fi
    
    # Generate summary
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    log_console "$BLUE" "\n========================================="
    log_console "$GREEN" "Migration completed successfully!"
    log_console "$BLUE" "========================================="
    log "INFO" "End time: $(date)"
    log "INFO" "Duration: ${DURATION}s"
    
    log_console "$BLUE" "\nFinal record counts:"
    for table in algos coins settings; do
        count=$(count_records "$table")
        log_console "$BLUE" "  $table: $count"
    done
    
    if [ $INCLUDE_BENCHMARKS -eq 1 ]; then
        for table in benchmarks bench_chips; do
            count=$(count_records "$table")
            log_console "$BLUE" "  $table: $count"
        done
    fi
    
    if [ $INCLUDE_MARKETS -eq 1 ]; then
        for table in markets market_history; do
            count=$(count_records "$table")
            log_console "$BLUE" "  $table: $count"
        done
    fi
    
    log_console "$BLUE" "\nLog file: $LOG_FILE"
}

# Run main function
main
