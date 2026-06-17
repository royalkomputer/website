-- Royal Komputer - Full PostgreSQL Schema
-- Target: Neon (cloud database) + local IPOS database reference

-- Product master table (from IPOS)
CREATE TABLE IF NOT EXISTS tbl_item (
    kodeitem VARCHAR(50) PRIMARY KEY,
    namaitem VARCHAR(255) NOT NULL,
    jenis VARCHAR(100) DEFAULT '',
    hargajual1 NUMERIC(15,0) DEFAULT 0
);

-- Product stock table (from IPOS)
CREATE TABLE IF NOT EXISTS tbl_itemstok (
    id SERIAL PRIMARY KEY,
    kodeitem VARCHAR(50) REFERENCES tbl_item(kodeitem) ON DELETE CASCADE,
    stok NUMERIC(15,0) DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_itemstok_kodeitem ON tbl_itemstok(kodeitem);

-- Custom product descriptions (created by admin panel)
CREATE TABLE IF NOT EXISTS tbl_web_deskripsi (
    kodeitem VARCHAR(50) PRIMARY KEY REFERENCES tbl_item(kodeitem) ON DELETE CASCADE,
    deskripsi TEXT DEFAULT ''
);
