<?php
require_once 'config.php';
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = '1';
$_SESSION['admin_username'] = 'superadmin';
$_SESSION['admin_role'] = 'super_admin';

$db = getDB();
if (!$db) { echo json_encode(['error'=>'No DB']); exit; }

// Sample data from tbl_ikdt
$r = pg_query($db, "SELECT * FROM tbl_ikdt LIMIT 5");
echo "=== tbl_ikdt sample data ===\n";
if ($r && pg_num_rows($r) > 0) {
    while ($row = pg_fetch_assoc($r)) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "No data or error: " . pg_last_error($db) . "\n";
}

// Check tbl_ikhd - look for notrsretur usage and see sample data
$r2 = pg_query($db, "SELECT notransaksi, tanggal, totalakhir, notrsretur FROM tbl_ikhd WHERE notrsretur IS NOT NULL LIMIT 5");
echo "\n=== tbl_ikhd with returns ===\n";
if ($r2 && pg_num_rows($r2) > 0) {
    while ($row = pg_fetch_assoc($r2)) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "No returned transactions found or error\n";
}

// Check some normal transactions
$r3 = pg_query($db, "SELECT notransaksi, tanggal, totalakhir, notrsretur FROM tbl_ikhd WHERE notrsretur IS NULL LIMIT 3");
echo "\n=== tbl_ikhd normal (no return) ===\n";
if ($r3 && pg_num_rows($r3) > 0) {
    while ($row = pg_fetch_assoc($r3)) {
        echo json_encode($row) . "\n";
    }
}

// Check total count
$r4 = pg_query($db, "SELECT COUNT(*) FROM tbl_ikhd WHERE notrsretur IS NULL");
if ($r4) {
    $row = pg_fetch_row($r4);
    echo "\n=== Total valid transactions (no return): {$row[0]} ===\n";
}

// Check if there's a purchase cost table or column for calculating profit
$r5 = pg_query($db, "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'tbl_ikdt'");
echo "\n=== tbl_ikdt all columns ===\n";
while ($row = pg_fetch_assoc($r5)) {
    echo "{$row['column_name']} ({$row['data_type']})\n";
}

// Also check tbl_imdt (purchase detail) for cost comparison
$r6 = pg_query($db, "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'tbl_imdt' ORDER BY ordinal_position");
echo "\n=== tbl_imdt columns (purchase detail) ===\n";
if ($r6 && pg_num_rows($r6) > 0) {
    while ($row = pg_fetch_assoc($r6)) {
        echo "{$row['column_name']} ({$row['data_type']})\n";
    }
} else {
    echo "Table not found\n";
}
