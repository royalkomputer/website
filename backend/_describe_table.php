<?php
require_once 'config.php';
session_start();
// Bypass auth for testing
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = '1';
$_SESSION['admin_username'] = 'superadmin';
$_SESSION['admin_role'] = 'super_admin';

$db = getDB();
if (!$db) { echo json_encode(['error'=>'No DB']); exit; }

// Describe tbl_ikhd
$r = pg_query($db, "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'tbl_ikhd' ORDER BY ordinal_position");
echo "=== tbl_ikhd columns ===\n";
while ($row = pg_fetch_assoc($r)) {
    echo "{$row['column_name']} ({$row['data_type']})\n";
}

// Describe tbl_ikdt
$r2 = pg_query($db, "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'tbl_ikdt' ORDER BY ordinal_position");
echo "\n=== tbl_ikdt columns ===\n";
if ($r2) {
    while ($row = pg_fetch_assoc($r2)) {
        echo "{$row['column_name']} ({$row['data_type']})\n";
    }
} else {
    echo "Table not found or error: " . pg_last_error($db) . "\n";
}

// Also check if there's a status/cancel column
$r3 = pg_query($db, "SELECT column_name, data_type FROM information_schema.columns 
    WHERE table_name = 'tbl_ikhd' 
    AND (column_name ILIKE '%batal%' OR column_name ILIKE '%cancel%' OR column_name ILIKE '%status%' OR column_name ILIKE '%void%')");
echo "\n=== tbl_ikhd cancellation-related columns ===\n";
if ($r3 && pg_num_rows($r3) > 0) {
    while ($row = pg_fetch_assoc($r3)) {
        echo "{$row['column_name']} ({$row['data_type']})\n";
    }
} else {
    echo "No cancellation-related columns found. Trying sample data...\n";
    // Get first 3 rows to see all columns with sample data
    $r4 = pg_query($db, "SELECT * FROM tbl_ikhd LIMIT 3");
    if ($r4 && pg_num_rows($r4) > 0) {
        $cols = [];
        for ($i = 0; $i < pg_num_fields($r4); $i++) {
            $cols[] = pg_field_name($r4, $i);
        }
        echo "All columns: " . implode(', ', $cols) . "\n";
        while ($row = pg_fetch_assoc($r4)) {
            echo json_encode($row) . "\n";
        }
    }
}
