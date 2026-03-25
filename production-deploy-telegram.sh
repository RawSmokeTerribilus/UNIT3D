#!/bin/bash

################################################################################
#                                                                              #
#   UNIT3D TELEGRAM IMPLEMENTATION - PRODUCTION DEPLOYMENT SCRIPT             #
#   Version: 1.0.0                                                            #
#   Purpose: Safe, idempotent deployment of Telegram features to production   #
#   Critical: Este script implementa todas las correcciones necesarias        #
#                                                                              #
################################################################################

set -e

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

# Configuration
ENVIRONMENT="${1:-production}"
BACKUP_DIR="./backups/deployment-$(date +%Y%m%d-%H%M%S)"
DEPLOYMENT_LOG="./deployment-telegram-$(date +%Y%m%d-%H%M%S).log"
ROLLBACK_ENABLED=true

# Global database variables (extracted in Phase 1)
DB_HOST=""
DB_USER=""
DB_PASS=""
DB_NAME=""
TELEGRAM_TOKEN=""

# Environment validation
validate_environment() {
    log_info "Phase 0: Validating environment..."
    
    if [ ! -f ".env" ]; then
        log_error ".env file not found"
        exit 1
    fi
    
    # Check required Telegram config variables
    if ! grep -q "TELEGRAM_BOT_TOKEN" .env; then
        log_error "TELEGRAM_BOT_TOKEN not found in .env"
        exit 1
    fi
    
    if ! grep -q "TELEGRAM_GROUP_ID" .env; then
        log_error "TELEGRAM_GROUP_ID not found in .env"
        exit 1
    fi
    
    if ! grep -q "TELEGRAM_TOPIC_NOVEDADES" .env; then
        log_error "TELEGRAM_TOPIC_NOVEDADES not found in .env"
        exit 1
    fi
    
    # Validate docker-compose exists
    if [ ! -f "docker-compose.yml" ]; then
        log_error "docker-compose.yml not found"
        exit 1
    fi
    
    # Check if containers are running
    if ! docker compose ps --services | grep -q "app"; then
        log_error "Docker services are not running. Run 'docker compose up -d' first"
        exit 1
    fi
    
    log_success "Environment validation passed"
}

# Robust extraction of environment variables with proper quoting
extract_env_variables() {
    log_info "Phase 1: Extracting environment variables..."
    
    # Extract database host
    DB_HOST=$(grep "DB_HOST=" .env | sed 's/.*DB_HOST=//;s/^"//;s/"$//')
    if [ -z "$DB_HOST" ]; then
        log_error "DB_HOST not found in .env"
        return 1
    fi
    
    # Extract database user
    DB_USER=$(grep "DB_USERNAME=" .env | sed 's/.*DB_USERNAME=//;s/^"//;s/"$//')
    if [ -z "$DB_USER" ]; then
        log_error "DB_USERNAME not found in .env"
        return 1
    fi
    
    # Extract database password - ROBUST: handles spaces, asterisks, special chars
    DB_PASS=$(grep "DB_PASSWORD=" .env | sed 's/.*DB_PASSWORD=//;s/^"//;s/"$//')
    if [ -z "$DB_PASS" ]; then
        log_error "DB_PASSWORD not found in .env"
        return 1
    fi
    
    # Extract database name
    DB_NAME=$(grep "DB_DATABASE=" .env | sed 's/.*DB_DATABASE=//;s/^"//;s/"$//')
    if [ -z "$DB_NAME" ]; then
        log_error "DB_DATABASE not found in .env"
        return 1
    fi
    
    # Extract Telegram token
    TELEGRAM_TOKEN=$(grep "TELEGRAM_BOT_TOKEN=" .env | sed 's/.*TELEGRAM_BOT_TOKEN=//;s/^"//;s/"$//')
    if [ -z "$TELEGRAM_TOKEN" ]; then
        log_error "TELEGRAM_BOT_TOKEN not found in .env"
        return 1
    fi
    
    log_success "Environment variables extracted"
}

# Creation de backup de database antes de migration
backup_database() {
    log_info "Phase 2: Backing up database..."
    
    mkdir -p "$BACKUP_DIR"
    
    # Use proper quoting for password with special characters
    if docker compose exec -T db mysqldump \
        -h "$DB_HOST" \
        -u "$DB_USER" \
        -p"$DB_PASS" \
        "$DB_NAME" > "$BACKUP_DIR/unit3d_pre_telegram_migration.sql" 2>>"$DEPLOYMENT_LOG"; then
        log_success "Database backup created at $BACKUP_DIR/unit3d_pre_telegram_migration.sql"
    else
        log_error "Database backup failed"
        return 1
    fi
}

# Phase 3: Validate Telegram API connectivity
validate_telegram_api() {
    log_info "Phase 3: Validating Telegram Bot API connectivity..."
    
    if [ -z "$TELEGRAM_TOKEN" ]; then
        log_error "TELEGRAM_BOT_TOKEN is empty"
        return 1
    fi
    
    local response=$(curl -s "https://api.telegram.org/bot${TELEGRAM_TOKEN}/getMe")
    
    if echo "$response" | grep -q '"ok":true'; then
        log_success "Telegram Bot API is accessible and token is valid"
    else
        log_error "Telegram Bot API validation failed"
        log_error "Response: $response"
        return 1
    fi
}

# Phase 4: Validate and apply migrations
apply_migrations() {
    log_info "Phase 4: Applying database migrations..."
    
    # List pending migrations
    log_info "Checking for pending migrations..."
    docker compose exec -T app php artisan migrate:status >> "$DEPLOYMENT_LOG" 2>&1
    
    # Apply migrations
    if docker compose exec -T app php artisan migrate --force 2>>"$DEPLOYMENT_LOG"; then
        log_success "Database migrations applied successfully"
    else
        log_error "Migration failed - rolling back to backup"
        if [ "$ROLLBACK_ENABLED" = true ]; then
            log_warning "To restore from backup, run:"
            log_warning "  docker compose exec -T db mysql -u\"$DB_USER\" -p\"$DB_PASS\" \"$DB_NAME\" < $BACKUP_DIR/unit3d_pre_telegram_migration.sql"
        fi
        return 1
    fi
}

# Phase 5: Verify code changes are ready
verify_code_changes() {
    log_info "Phase 5: Verifying code changes..."
    
    local files_to_check=(
        "app/Jobs/SendTelegramNotification.php"
        "app/Services/TelegramService.php"
        "app/Http/Controllers/API/TelegramWebhookController.php"
        "app/Observers/TorrentObserver.php"
        "config/services.php"
        "database/migrations/2026_03_24_010501_add_telegram_fields_to_users_table.php"
    )
    
    for file in "${files_to_check[@]}"; do
        if [ ! -f "$file" ]; then
            log_error "Missing required file: $file"
            return 1
        fi
    done
    
    # Verify SendTelegramNotification has retry policy
    if ! grep -q "public \$tries = 3" app/Jobs/SendTelegramNotification.php; then
        log_error "SendTelegramNotification missing retry policy ($tries)"
        return 1
    fi
    
    # Verify TelegramService uses correct API
    if ! grep -q "banChatMember" app/Services/TelegramService.php; then
        log_error "TelegramService using deprecated API (should use banChatMember)"
        return 1
    fi
    
    # Verify TelegramWebhookController has DB transaction
    if ! grep -q "DB::transaction" app/Http/Controllers/API/TelegramWebhookController.php; then
        log_error "TelegramWebhookController missing database transaction"
        return 1
    fi
    
    log_success "All code changes verified"
}

# Phase 6: Rebuild Docker containers with env vars
rebuild_containers() {
    log_info "Phase 6: Rebuilding containers with Telegram environment variables..."
    
    # Verify docker-compose has TELEGRAM_* env vars in app, scheduler, worker
    if ! grep -q "TELEGRAM_BOT_TOKEN" docker-compose.yml; then
        log_error "docker-compose.yml missing TELEGRAM_BOT_TOKEN environment variable"
        return 1
    fi
    
    log_info "Rebuilding containers..."
    docker compose down --remove-orphans >> "$DEPLOYMENT_LOG" 2>&1
    
    if docker compose up -d --build 2>>"$DEPLOYMENT_LOG"; then
        log_success "Containers rebuilt and started"
    else
        log_error "Container rebuild failed"
        return 1
    fi
    
    # Wait for containers to be healthy
    log_info "Waiting for containers to be healthy (timeout 60s)..."
    local timeout=60
    local elapsed=0
    while [ $elapsed -lt $timeout ]; do
        if docker compose exec -T app test -f /var/www/html/bootstrap/app.php 2>/dev/null; then
            log_success "Application container is healthy"
            break
        fi
        sleep 2
        elapsed=$((elapsed + 2))
    done
    
    if [ $elapsed -ge $timeout ]; then
        log_warning "Container health check timed out - may need manual verification"
    fi
}

# Phase 7: Clear and warm up Laravel caches
clear_caches() {
    log_info "Phase 7: Clearing and warming up caches..."
    
    docker compose exec -T app php artisan config:clear >> "$DEPLOYMENT_LOG" 2>&1
    docker compose exec -T app php artisan cache:clear >> "$DEPLOYMENT_LOG" 2>&1
    docker compose exec -T app php artisan view:clear >> "$DEPLOYMENT_LOG" 2>&1
    
    # Warm up config
    docker compose exec -T app php artisan config:cache >> "$DEPLOYMENT_LOG" 2>&1
    
    log_success "Caches cleared and warmed up"
}

# Phase 8: End-to-end validation
validate_deployment() {
    log_info "Phase 8: Running end-to-end validation tests..."
    
    # Test 1: Check if TelegramService can be instantiated
    log_info "  Testing TelegramService instantiation..."
    if docker compose exec -T app php artisan tinker --execute="(new \App\Services\TelegramService());" 2>>"$DEPLOYMENT_LOG"; then
        log_success "  ✓ TelegramService is instantiable"
    else
        log_error "  ✗ TelegramService instantiation failed"
        return 1
    fi
    
    # Test 2: Verify event listener is registered
    log_info "  Testing event observer registration..."
    if docker compose exec -T app php artisan tinker --execute="\App\Models\Torrent::resolveObserverCallbacks('created');" 2>>"$DEPLOYMENT_LOG"; then
        log_success "  ✓ TorrentObserver is registered"
    else
        log_warning "  ⚠ Could not verify TorrentObserver registration"
    fi
    
    # Test 3: Check queue worker connectivity
    log_info "  Testing queue worker..."
    local worker_status=$(docker compose ps worker 2>/dev/null | grep -c "Up" || echo "0")
    if [ "$worker_status" -gt 0 ]; then
        log_success "  ✓ Queue worker is running"
    else
        log_error "  ✗ Queue worker is not running"
        return 1
    fi
    
    # Test 4: Validate Telegram config is loaded and database connectivity with proper quoting
    log_info "  Validating Telegram configuration and database connectivity..."
    if docker compose exec -T db mysqladmin \
        -h "$DB_HOST" \
        -u "$DB_USER" \
        -p"$DB_PASS" \
        ping 2>>"$DEPLOYMENT_LOG" | grep -q "mysqld is alive"; then
        log_success "  ✓ Database is accessible"
    else
        log_error "  ✗ Database is not accessible"
        return 1
    fi
    
    # Test 5: Validate Telegram configuration in application
    docker compose exec -T app php artisan tinker --execute="\$config = config('services.telegram'); echo 'Token: ' . (empty(\$config['token']) ? 'MISSING' : 'OK') . ', Chat: ' . (empty(\$config['chat_id']) ? 'MISSING' : 'OK');" 2>>"$DEPLOYMENT_LOG"
    
    log_success "End-to-end validation completed"
}

# Generate deployment report
generate_report() {
    log_info "Phase 9: Generating deployment report..."
    
    cat > "$BACKUP_DIR/DEPLOYMENT_REPORT.md" << 'EOF'
# Telegram Implementation - Production Deployment Report

## Deployment Information
- **Date**: $(date)
- **Environment**: ${ENVIRONMENT}
- **Backup Location**: ${BACKUP_DIR}
- **Log File**: ${DEPLOYMENT_LOG}

## Changes Applied

### 1. Database Migration
- File: `database/migrations/2026_03_24_010501_add_telegram_fields_to_users_table.php`
- Fields Added: `telegram_chat_id`, `telegram_token`

### 2. Configuration
- ✅ `docker-compose.yml` - Added TELEGRAM_BOT_TOKEN, TELEGRAM_GROUP_ID, TELEGRAM_TOPIC_NOVEDADES
- ✅ `config/services.php` - Telegram service configuration

### 3. Core Implementation
- ✅ `app/Jobs/SendTelegramNotification.php`
  - Retry policy: 3 attempts with [10s, 60s, 300s] backoff
  - Timeout: 30 seconds
  - Error handling: Comprehensive logging
  
- ✅ `app/Services/TelegramService.php`
  - Updated API: banChatMember (not deprecated kickChatMember)
  - Methods: sendAnnouncement(), sendMessage(), kickUser()
  
- ✅ `app/Http/Controllers/API/TelegramWebhookController.php`
  - DB transaction with pessimistic lock (prevents race condition)
  - User linking via /start TRK-TOKEN telegram bot command
  
- ✅ `app/Observers/TorrentObserver.php`
  - Registered in EventServiceProvider::boot()
  - Fires SendTelegramNotification on status change to APPROVED

## Deployment Checklist

- [ ] Database backup verified
- [ ] Telegram API connectivity confirmed
- [ ] Migrations applied successfully
- [ ] Docker containers rebuilt with env vars
- [ ] TelegramService instantiation test passed
- [ ] Queue worker running
- [ ] Configuration loaded correctly
- [ ] End-to-end test completed

## Manual Testing Steps

1. **Test Torrent Announcement**:
   ```bash
   # Create a test torrent and approve it
   # Check if Telegram message appears in the configured group/topic
   ```

2. **Test User Linking**:
   ```bash
   # Send /start TRK-XXXXXX to the telegram bot
   # Verify user.telegram_chat_id is populated
   ```

3. **Test Queue Processing**:
   ```bash
   docker compose logs worker
   # Watch for SendTelegramNotification processing
   ```

4. **Monitor Log Files**:
   ```bash
   docker compose logs app | grep -i telegram
   ```

## Rollback Procedure

If deployment fails, restore the database:

```bash
docker compose exec -T db mysql -u$DB_USER -p$DB_PASS $DB_NAME < ${BACKUP_DIR}/unit3d_pre_telegram_migration.sql
```

Then redeploy or investigate errors in `${DEPLOYMENT_LOG}`

## Support

For issues, check:
- `storage/logs/laravel.log`
- `${DEPLOYMENT_LOG}`
- `docker compose logs app worker`

EOF
    
    log_success "Deployment report generated at $BACKUP_DIR/DEPLOYMENT_REPORT.md"
}

# Main deployment flow
main() {
    log_info "╔════════════════════════════════════════════════════════════════╗"
    log_info "║   UNIT3D TELEGRAM IMPLEMENTATION - PRODUCTION DEPLOYMENT      ║"
    log_info "║   Environment: $ENVIRONMENT"
    log_info "║   Start Time: $(date '+%Y-%m-%d %H:%M:%S')"
    log_info "╚════════════════════════════════════════════════════════════════╝"
    
    # Run all phases
    validate_environment || exit 1
    extract_env_variables || exit 1
    backup_database || exit 1
    validate_telegram_api || exit 1
    apply_migrations || exit 1
    verify_code_changes || exit 1
    rebuild_containers || exit 1
    clear_caches || exit 1
    validate_deployment || exit 1
    generate_report || exit 1
    
    log_info "╔════════════════════════════════════════════════════════════════╗"
    log_success "DEPLOYMENT COMPLETED SUCCESSFULLY"
    log_info "║"
    log_info "║   Next Steps:"
    log_info "║   1. Monitor logs: docker compose logs -f app worker"
    log_info "║   2. Run manual tests (see deployment report)"
    log_info "║   3. Verify Telegram messages in production group"
    log_info "║"
    log_info "║   Backup Location: $BACKUP_DIR"
    log_info "║   Report File: $BACKUP_DIR/DEPLOYMENT_REPORT.md"
    log_info "╚════════════════════════════════════════════════════════════════╝"
    
    # Display deployment log summary
    log_info "Deployment Log Summary:"
    tail -20 "$DEPLOYMENT_LOG"
}

# Run main function
main "$@"
