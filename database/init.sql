-- Royal Komputer — Database Init Script
-- Dijalankan otomatis oleh PostgreSQL container saat pertama kali start
-- (file ini di-mount ke /docker-entrypoint-initdb.d/init.sql)

-- ============================================================
-- 1. Product master table (dari IPOS)
-- ============================================================
CREATE TABLE IF NOT EXISTS tbl_item (
    kodeitem VARCHAR(50) PRIMARY KEY,
    namaitem VARCHAR(255) NOT NULL,
    jenis VARCHAR(100) DEFAULT '',
    hargajual1 NUMERIC(15,0) DEFAULT 0
);

-- ============================================================
-- 2. Product stock table (dari IPOS)
-- ============================================================
CREATE TABLE IF NOT EXISTS tbl_itemstok (
    id SERIAL PRIMARY KEY,
    kodeitem VARCHAR(50) REFERENCES tbl_item(kodeitem) ON DELETE CASCADE,
    stok NUMERIC(15,0) DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_itemstok_kodeitem ON tbl_itemstok(kodeitem);

-- ============================================================
-- 3. Custom product descriptions (admin panel)
-- ============================================================
CREATE TABLE IF NOT EXISTS tbl_web_deskripsi (
    kodeitem VARCHAR(50) PRIMARY KEY,
    deskripsi TEXT DEFAULT ''
);

-- ============================================================
-- 4. Admin accounts
-- ============================================================
CREATE TABLE IF NOT EXISTS admins (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'admin',
    nama VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed default superadmin (password: royal2026 — harus diganti setelah login pertama)
INSERT INTO admins (username, password_hash, role, nama, created_at)
SELECT 'superadmin', '$2y$10$Q6I5JUyogQq8uJtOu/BrH.HhtKUL7l/b/UonmVcQexE9dNtl7bUhq', 'super_admin', 'Super Admin', NOW()
WHERE NOT EXISTS (SELECT 1 FROM admins);

-- ============================================================
-- 5. Closure schedules
-- ============================================================
CREATE TABLE IF NOT EXISTS jadwal_tutup (
    id VARCHAR(50) PRIMARY KEY,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    note TEXT DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 6. Store manual status
-- ============================================================
CREATE TABLE IF NOT EXISTS status_toko (
    id INTEGER PRIMARY KEY DEFAULT 1,
    status VARCHAR(10) NOT NULL DEFAULT 'buka'
);

INSERT INTO status_toko (id, status) VALUES (1, 'buka')
ON CONFLICT (id) DO NOTHING;

-- ============================================================
-- 7. Tagline
-- ============================================================
CREATE TABLE IF NOT EXISTS tagline (
    id INTEGER PRIMARY KEY DEFAULT 1,
    text TEXT NOT NULL DEFAULT 'Bingung mau rakit atau upgrade komputer? Ke Royal Komputer aja. Bisa tukar tambah loh.'
);

INSERT INTO tagline (id, text) VALUES (1, 'Bingung mau rakit atau upgrade komputer? Ke Royal Komputer aja. Bisa tukar tambah loh.')
ON CONFLICT (id) DO NOTHING;

-- ============================================================
-- 8. Product info text
-- ============================================================
CREATE TABLE IF NOT EXISTS product_info (
    id INTEGER PRIMARY KEY DEFAULT 1,
    text TEXT NOT NULL DEFAULT 'Perhatian! Harga tidak selalu update. Silahkan hubungi Kami di WhatsApp.'
);

INSERT INTO product_info (id, text) VALUES (1, 'Perhatian! Harga tidak selalu update. Silahkan hubungi Kami di WhatsApp.')
ON CONFLICT (id) DO NOTHING;

-- ============================================================
-- 9. Heading config
-- ============================================================
CREATE TABLE IF NOT EXISTS heading (
    id INTEGER PRIMARY KEY DEFAULT 1,
    prefix VARCHAR(255) NOT NULL DEFAULT 'Solusi Hardware di',
    brand VARCHAR(255) NOT NULL DEFAULT 'Royal Komputer'
);

INSERT INTO heading (id, prefix, brand) VALUES (1, 'Solusi Hardware di', 'Royal Komputer')
ON CONFLICT (id) DO NOTHING;

-- ============================================================
-- 10. Admin history log
-- ============================================================
CREATE TABLE IF NOT EXISTS admin_history (
    id SERIAL PRIMARY KEY,
    admin_id INTEGER NOT NULL,
    admin_username VARCHAR(100) NOT NULL,
    admin_nama VARCHAR(255) NOT NULL DEFAULT '',
    action VARCHAR(50) NOT NULL,
    target_type VARCHAR(50) DEFAULT '',
    target_id VARCHAR(100) DEFAULT '',
    detail TEXT DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
