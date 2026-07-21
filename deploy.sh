#!/usr/bin/env bash
#
# deploy.sh — Royal Komputer VPS Deployment Script
# Target: Ubuntu 22.04, 1GB RAM, Manual Install (Nginx + PHP + PostgreSQL)
#
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/royalkomputer/website/main/deploy.sh | sudo bash
#
# Atau:
#   sudo bash deploy.sh
#
set -euo pipefail

# ─── Color ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()  { echo -e "${CYAN}[INFO]${NC}  $1"; }
ok()    { echo -e "${GREEN}[OK]${NC}    $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $1"; }
fail()  { echo -e "${RED}[FAIL]${NC}  $1"; exit 1; }

# ─── Root check ─────────────────────────────────────────────────────────────
[[ $EUID -eq 0 ]] || fail "Jalankan dengan sudo: sudo bash deploy.sh"

# ─── Input variables ────────────────────────────────────────────────────────
DOMAIN="${DOMAIN:-}"
ADMIN_DOMAIN="${ADMIN_DOMAIN:-}"
REPO_URL="${REPO_URL:-}"
DB_PASS="${DB_PASS:-}"
EMAIL="${EMAIL:-}"
APP_DIR="${APP_DIR:-/var/www/royalkomputer}"

# ─── Prompt for missing values ──────────────────────────────────────────────
prompt() {
    local var_name="$1" prompt_text="$2" default_val="${3:-}"
    local current_val="${!var_name:-}"
    if [[ -z "$current_val" ]]; then
        if [[ -n "$default_val" ]]; then
            read -rp "$prompt_text [$default_val]: " input
            printf -v "$var_name" '%s' "${input:-$default_val}"
        else
            read -rp "$prompt_text: " input
            printf -v "$var_name" '%s' "$input"
        fi
    fi
}

echo ""
echo "============================================"
echo "   Royal Komputer — VPS Deployment Script"
echo "   Ubuntu 22.04 · 1GB RAM · Manual Install"
echo "============================================"
echo ""

prompt DOMAIN        "Domain storefront"        "royalkomputer.com"
prompt ADMIN_DOMAIN  "Domain admin panel"       "admin.royalkomputer.com"
prompt REPO_URL      "Git repository URL"       "https://github.com/royalkomputer/website.git"
prompt DB_PASS       "Database password (min 8 karakter)"
prompt EMAIL         "Email untuk Let's Encrypt" "admin@${DOMAIN}"

# ─── Summary ────────────────────────────────────────────────────────────────
echo ""
info "Ringkasan:"
echo "  Storefront  : https://$DOMAIN"
echo "  Admin Panel : https://$ADMIN_DOMAIN"
echo "  Repo        : $REPO_URL"
echo "  Install dir : $APP_DIR"
echo ""
echo -e "${YELLOW}Akan menginstal: Nginx, PHP 8.2-FPM, PostgreSQL 16, Git, Certbot${NC}"
echo -e "${YELLOW}Sebagian perintah menggunakan sudo dan akan memicu restart service.${NC}"
echo ""
read -rp "Lanjutkan? (y/N) " confirm
[[ "$confirm" =~ ^[Yy]$ ]] || fail "Dibatalkan."

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 1: Install Dependencies
# ═══════════════════════════════════════════════════════════════════════════════
echo ""
info "Step 1/10: Install dependencies..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq nginx php8.2-fpm php8.2-pgsql php8.2-gd php8.2-mbstring php8.2-curl postgresql postgresql-contrib git curl wget certbot python3-certbot-nginx
ok "Dependencies installed"

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 2: Clone Repository
# ═══════════════════════════════════════════════════════════════════════════════
echo ""
info "Step 2/10: Clone repository..."
if [[ -d "$APP_DIR" ]]; then
    warn "$APP_DIR already exists — skipping clone"
    if [[ ! -d "$APP_DIR/.git" ]]; then
        fail "$APP_DIR exists but is not a git repository. Remove it manually: rm -rf $APP_DIR"
    fi
else
    mkdir -p "$(dirname "$APP_DIR")"
    git clone "$REPO_URL" "$APP_DIR"
    ok "Repository cloned to $APP_DIR"
fi

chown -R www-data:www-data "$APP_DIR"

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 3: Setup Database
# ═══════════════════════════════════════════════════════════════════════════════
echo ""
info "Step 3/10: Setup PostgreSQL database..."
DB_USER="${DB_USER:-royal_owner}"
DB_NAME="${DB_NAME:-royalkomputer}"

if sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'" | grep -q 1; then
    warn "User '$DB_USER' already exists — skipping"
else
    sudo -u postgres psql -c "CREATE USER $DB_USER WITH PASSWORD '$DB_PASS';"
    ok "User $DB_USER created"
fi

if sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" | grep -q 1; then
    warn "Database '$DB_NAME' already exists — skipping"
else
    sudo -u postgres psql -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;"
    sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;"
    ok "Database $DB_NAME created"
fi

if sudo -u postgres psql -d "$DB_NAME" -tAc "SELECT 1 FROM information_schema.tables WHERE table_name='tbl_item'" | grep -q 1; then
    warn "Tables already exist — skipping schema import"
else
    sudo -u postgres psql -d "$DB_NAME" -f "$APP_DIR/database/init.sql"
    ok "Schema imported"
fi

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 4: Environment Variables
# ═══════════════════════════════════════════════════════════════════════════════
echo ""
info "Step 4/10: Create .env files..."
cat > "$APP_DIR/backend/.env" <<EOF
DB_PASSWORD=$DB_PASS
APP_ENV=production
EOF
ok "backend/.env created"

cat > "$APP_DIR/frontend/.env" <<EOF
DB_PASSWORD=$DB_PASS
EOF
ok "frontend/.env created"

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 5: Setup Uploads & Permissions
# ═══════════════════════════════════════════════════════════════════════════════
echo ""
info "Step 5/10: Setup uploads & permissions..."
mkdir -p "$APP_DIR/backend/uploads" "$APP_DIR/backend/data"
chown -R www-data:www-data "$APP_DIR/backend/uploads" "$APP_DIR/backend/data"
chmod -R 755 "$APP_DIR/backend/uploads" "$APP_DIR/backend/data"
ok "Uploads directory ready"

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 6: Nginx — Frontend
# ═══════════════════════════════════════════════════════════════════════════════
echo ""
info "Step 6/10: Configure Nginx — frontend ($DOMAIN)..."

if [[ -f "/etc/nginx/sites-enabled/$DOMAIN" ]]; then
    warn "frontend site already enabled — skipping"
else
    cat > "/etc/nginx/sites-available/$DOMAIN" <<NGINX
server {
    listen 80;
    server_name $DOMAIN;
    root $APP_DIR/frontend;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location /uploads/ {
        expires 7d;
        add_header Cache-Control "public";
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}
NGINX
    ln -sf "/etc/nginx/sites-available/$DOMAIN" "/etc/nginx/sites-enabled/"
    ok "frontend Nginx config created"
fi

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 7: Nginx — Admin Panel
# ═══════════════════════════════════════════════════════════════════════════════
echo ""
info "Step 7/10: Configure Nginx — admin ($ADMIN_DOMAIN)..."

if [[ -f "/etc/nginx/sites-enabled/$ADMIN_DOMAIN" ]]; then
    warn "admin site already enabled — skipping"
else
    cat > "/etc/nginx/sites-available/$ADMIN_DOMAIN" <<NGINX
server {
    listen 80;
    server_name $ADMIN_DOMAIN;
    root $APP_DIR/backend;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location /uploads/ {
        expires 7d;
        add_header Cache-Control "public";
    }

    location /data/ {
        deny all;
        return 404;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}
NGINX
    ln -sf "/etc/nginx/sites-available/$ADMIN_DOMAIN" "/etc/nginx/sites-enabled/"
    ok "admin Nginx config created"
fi

nginx -t && systemctl reload nginx
ok "Nginx reloaded"

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 8: SSL Let's Encrypt
# ═══════════════════════════════════════════════════════════════════════════════
echo ""
info "Step 8/10: SSL Let's Encrypt..."
if [[ -d "/etc/letsencrypt/live/$DOMAIN" ]]; then
    warn "SSL certificate for $DOMAIN already exists — skipping"
else
    certbot --nginx -d "$DOMAIN" -d "$ADMIN_DOMAIN" --non-interactive --agree-tos --email "$EMAIL" || {
        warn "Certbot gagal. Domain mungkin belum pointing ke VPS ini. Jalankan manual nanti:"
        warn "  certbot --nginx -d $DOMAIN -d $ADMIN_DOMAIN"
    }
fi

# ═══════════════════════════════════════════════════════════════════════════════
# STEP 9: Optimasi PostgreSQL untuk 1GB RAM
# ═══════════════════════════════════════════════════════════════════════════════
echo ""
info "Step 9/10: Optimasi PostgreSQL untuk 1GB RAM..."
PG_CONF="/etc/postgresql/16/main/postgresql.conf"
if [[ -f "$PG_CONF" ]]; then
    sed -i "s/^shared_buffers\s*=.*/shared_buffers = 256MB/" "$PG_CONF" 2>/dev/null || \
        echo "shared_buffers = 256MB" >> "$PG_CONF"
    sed -i "s/^effective_cache_size\s*=.*/effective_cache_size = 512MB/" "$PG_CONF" 2>/dev/null || \
        echo "effective_cache_size = 512MB" >> "$PG_CONF"
    sed -i "s/^work_mem\s*=.*/work_mem = 8MB/" "$PG_CONF" 2>/dev/null || \
        echo "work_mem = 8MB" >> "$PG_CONF"
    sed -i "s/^maintenance_work_mem\s*=.*/maintenance_work_mem = 64MB/" "$PG_CONF" 2>/dev/null || \
        echo "maintenance_work_mem = 64MB" >> "$PG_CONF"
    systemctl restart postgresql
    ok "PostgreSQL optimized for 1GB RAM"
else
    warn "PostgreSQL config not found at $PG_CONF — skipping optimization"
fi

# ═══════════════════════════════════════════════════════════════════════════════
# DONE
# ═══════════════════════════════════════════════════════════════════════════════
echo ""
echo "============================================"
echo -e "  ${GREEN}Deployment selesai!${NC}"
echo "============================================"
echo ""
echo "  Storefront  : https://$DOMAIN"
echo "  Admin Panel : https://$ADMIN_DOMAIN/login.php"
echo "  Username    : superadmin"
echo "  Password    : royal2026"
echo ""
echo "  ⚠️  Segera ganti password admin setelah login pertama!"
echo ""
info "Beberapa hal yang perlu dicek manual:"
echo "  1. Pastikan DNS domain $DOMAIN dan $ADMIN_DOMAIN mengarah ke IP VPS ini"
echo "  2. Jika SSL gagal, jalankan: certbot --nginx -d $DOMAIN -d $ADMIN_DOMAIN"
echo "  3. Untuk upload foto: gunakan admin panel atau sync via git dari PC toko"
echo "  4. Lihat log jika ada error: journalctl -u nginx -u php8.2-fpm -u postgresql"
echo ""
