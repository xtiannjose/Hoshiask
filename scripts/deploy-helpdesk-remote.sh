#!/usr/bin/env bash

set -euo pipefail

BRANCH="${1:-main}"
EXPECTED_PATH="/opt/helpdesk"
PREVIOUS_HEAD=""
MAINTENANCE_ACTIVE=0
DEPLOY_SUCCEEDED=0
# Latest migration batch before this deploy's `migrate`; empty until that step is reached.
BATCH_BEFORE_MIGRATE=""

log() {
    echo "[deploy $(date '+%Y-%m-%d %H:%M:%S')] $*"
}

latest_migration_batch() {
    php artisan tinker --execute='echo \Illuminate\Support\Facades\Schema::hasTable("migrations") ? (int) \Illuminate\Support\Facades\DB::table("migrations")->max("batch") : 0;' 2>/dev/null \
        | tail -n 1 \
        | tr -dc '0-9'
}

rollback_and_exit() {
    local exit_code=$?
    trap - ERR

    if [[ "$DEPLOY_SUCCEEDED" == "1" ]]; then
        log "Deploy itself succeeded, but a post-deploy step failed (exit $exit_code)."
        log "NOT rolling back a working deploy. Check maintenance-mode state and"
        log "run 'php artisan up' manually once verified."
        exit "$exit_code"
    fi

    log "Deploy step failed (exit $exit_code)."

    if [[ -n "$PREVIOUS_HEAD" ]]; then
        log "Rolling back to previous commit $PREVIOUS_HEAD..."
        set +e

        # Only undo migrations this deploy applied. Rolling back when this deploy
        # never migrated would revert the previous (working) release's migrations.
        if [[ -z "$BATCH_BEFORE_MIGRATE" ]]; then
            log "Deploy failed before the migrate step; leaving the database untouched."
        else
            CURRENT_BATCH="$(latest_migration_batch)"
            if [[ -z "$CURRENT_BATCH" ]]; then
                log "WARNING: could not read the current migration batch; skipping migrate:rollback."
                log "Review database state manually -- do not assume it matches $PREVIOUS_HEAD."
            elif (( CURRENT_BATCH > BATCH_BEFORE_MIGRATE )); then
                php artisan migrate:rollback --force
                if [[ $? -ne 0 ]]; then
                    log "WARNING: migrate:rollback reported a failure."
                    log "Review database state manually -- do not assume it matches $PREVIOUS_HEAD."
                fi
            else
                log "This deploy applied no migrations; nothing to roll back in the database."
            fi
        fi

        git checkout "$PREVIOUS_HEAD"
        composer install --no-dev --optimize-autoloader --no-interaction
        npm ci && npm run build
        rm -rf node_modules
        php artisan optimize:clear
        php artisan optimize

        set -e
    else
        log "No previous commit was captured yet -- nothing to roll back."
    fi

    if [[ "$MAINTENANCE_ACTIVE" == "1" ]]; then
        if php artisan helpdesk:ops-status; then
            php artisan up
            log "Rollback verified healthy. Site is back up on $PREVIOUS_HEAD."
        else
            log "ROLLBACK HEALTH CHECK FAILED. Leaving the site in maintenance mode."
            log "Manual investigation required before running 'php artisan up'."
        fi
    fi

    exit "$exit_code"
}

trap rollback_and_exit ERR

if [[ "$(pwd -P)" != "$EXPECTED_PATH" ]]; then
    echo "This deploy script is restricted to $EXPECTED_PATH." >&2
    exit 1
fi

if [[ ! -f artisan || ! -f composer.json ]]; then
    echo "Expected a Laravel app checkout in $EXPECTED_PATH." >&2
    exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Refusing to deploy from a dirty working tree in $EXPECTED_PATH." >&2
    echo "Commit, stash, or discard local changes on the server before deploying." >&2
    exit 1
fi

git fetch origin "$BRANCH"
PREVIOUS_HEAD="$(git rev-parse HEAD)"

MAINTENANCE_SECRET="$(openssl rand -hex 16)"
php artisan down --retry=60 --secret="$MAINTENANCE_SECRET"
MAINTENANCE_ACTIVE=1
log "Maintenance mode on. Bypass secret generated (not logged) -- check the artisan down command's own output on this terminal if you need it."

git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

php ./scripts/check-php-platform.php
composer validate --no-check-publish --no-interaction
composer install --no-dev --optimize-autoloader --no-interaction
composer audit --locked --no-interaction

FRONTEND_CHANGED=0
# Tailwind generates CSS from class names found in Blade views and PHP files
# too, so view/app changes can require a rebuild even when no JS/CSS changed.
if [[ ! -f public/build/manifest.json ]]; then
    FRONTEND_CHANGED=1
elif ! git diff --quiet "$PREVIOUS_HEAD" HEAD -- \
    package.json \
    package-lock.json \
    vite.config.js \
    tailwind.config.js \
    postcss.config.js \
    eslint.config.js \
    resources/js \
    resources/css \
    resources/views \
    app; then
    FRONTEND_CHANGED=1
fi

if [[ "$FRONTEND_CHANGED" == "1" ]]; then
    npm ci
    npm run build
    # The live app serves the generated assets from public/build, so keep the
    # deploy host tidy by removing the transient frontend toolchain afterward.
    rm -rf node_modules
else
    log "Skipping frontend build; no tracked asset inputs changed."
fi

php artisan helpdesk:backup-database
BATCH_BEFORE_MIGRATE="$(latest_migration_batch)"
if [[ -z "$BATCH_BEFORE_MIGRATE" ]]; then
    log "Could not read the current migration batch; refusing to migrate without a safe rollback point."
    false   # deliberately trigger the ERR trap/rollback (database untouched)
fi
php artisan migrate --force
php artisan route:list --except-vendor > /dev/null
php artisan optimize:clear
php artisan helpdesk:cleanup-runtime
php artisan optimize
php artisan queue:restart || true

HEALTHY=0
for attempt in 1 2 3 4 5; do
    if php artisan helpdesk:ops-status --fail-on-warning; then
        HEALTHY=1
        break
    fi
    log "Health check attempt $attempt/5 failed."
    if [[ "$attempt" != "5" ]]; then
        sleep 3
    fi
done

if [[ "$HEALTHY" != "1" ]]; then
    false   # deliberately trigger the ERR trap/rollback
fi

DEPLOY_SUCCEEDED=1
php artisan up
MAINTENANCE_ACTIVE=0
log "Deploy succeeded. $PREVIOUS_HEAD -> $(git rev-parse HEAD). Site is back up."
