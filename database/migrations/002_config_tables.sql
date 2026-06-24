-- Migration 002: Configuration tables for persistent admin data
-- Replaces JSON file storage with DB-backed storage for Render deployment
-- (JSON files are ephemeral on Render and lost on container restart)

-- ============================================================
-- 1. Admin accounts
-- ============================================================
CREATE TABLE IF NOT EXISTS admins (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'admin',
    nama VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed default superadmin if table is empty
INSERT INTO admins (username, password_hash, role, nama, created_at)
SELECT 'superadmin', '$2y$10$dummy_placeholder_will_be_replaced_on_first_use', 'super_admin', 'Super Admin', NOW()
WHERE NOT EXISTS (SELECT 1 FROM admins);

-- ============================================================
-- 2. Operating hours
-- ============================================================
CREATE TABLE IF NOT EXISTS jam_operasional (
    day VARCHAR(20) PRIMARY KEY,
    buka VARCHAR(5) DEFAULT '09:00',
    tutup VARCHAR(5) DEFAULT '21:00',
    indo VARCHAR(20) NOT NULL,
    libur BOOLEAN DEFAULT FALSE
);

-- Seed default hours
INSERT INTO jam_operasional (day, buka, tutup, indo, libur) VALUES
    ('Monday', '09:00', '21:00', 'Senin', FALSE),
    ('Tuesday', '09:00', '21:00', 'Selasa', FALSE),
    ('Wednesday', '09:00', '21:00', 'Rabu', FALSE),
    ('Thursday', '09:00', '21:00', 'Kamis', FALSE),
    ('Friday', '13:30', '22:00', 'Jumat', FALSE),
    ('Saturday', '09:00', '21:00', 'Sabtu', FALSE),
    ('Sunday', '09:00', '21:00', 'Minggu', FALSE)
ON CONFLICT (day) DO NOTHING;

-- ============================================================
-- 3. Closure schedules
-- ============================================================
CREATE TABLE IF NOT EXISTS jadwal_tutup (
    id VARCHAR(50) PRIMARY KEY,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    note TEXT DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 4. Store manual status
-- ============================================================
CREATE TABLE IF NOT EXISTS status_toko (
    id INTEGER PRIMARY KEY DEFAULT 1,
    status VARCHAR(10) NOT NULL DEFAULT 'buka'
);

INSERT INTO status_toko (id, status) VALUES (1, 'buka')
ON CONFLICT (id) DO NOTHING;

-- ============================================================
-- 5. Tagline
-- ============================================================
CREATE TABLE IF NOT EXISTS tagline (
    id INTEGER PRIMARY KEY DEFAULT 1,
    text TEXT NOT NULL DEFAULT 'Bingung mau rakit atau upgrade komputer? Ke Royal Komputer aja. Bisa tukar tambah loh.'
);

INSERT INTO tagline (id, text) VALUES (1, 'Bingung mau rakit atau upgrade komputer? Ke Royal Komputer aja. Bisa tukar tambah loh.')
ON CONFLICT (id) DO NOTHING;

-- ============================================================
-- 6. Product info text
-- ============================================================
CREATE TABLE IF NOT EXISTS product_info (
    id INTEGER PRIMARY KEY DEFAULT 1,
    text TEXT NOT NULL DEFAULT 'Menampilkan {count} produk tersedia. Harga tidak selalu update, dan bisa berubah sewaktu-waktu. Hubungi kami di WhatsApp.'
);

INSERT INTO product_info (id, text) VALUES (1, 'Menampilkan {count} produk tersedia. Harga tidak selalu update, dan bisa berubah sewaktu-waktu. Hubungi kami di WhatsApp.')
ON CONFLICT (id) DO NOTHING;

-- ============================================================
-- 7. Heading config
-- ============================================================
CREATE TABLE IF NOT EXISTS heading (
    id INTEGER PRIMARY KEY DEFAULT 1,
    prefix VARCHAR(255) NOT NULL DEFAULT 'Solusi Hardware di',
    brand VARCHAR(255) NOT NULL DEFAULT 'Royal Komputer'
);

INSERT INTO heading (id, prefix, brand) VALUES (1, 'Solusi Hardware di', 'Royal Komputer')
ON CONFLICT (id) DO NOTHING;
