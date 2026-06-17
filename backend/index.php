<?php
/**
 * Health check endpoint for Render.
 * Render pings this every 5 minutes to verify the service is running.
 *
 * Returns JSON with service status, DB connection health, and timestamp.
 */

header('Content-Type: application/json');

$status = 'ok';
$db_status = 'unknown';
$error = null;

// Test DB connection
require_once __DIR__ . '/config.php';
$conn = @getDBConnection();
if ($conn) {
    $db_status = 'connected';
    pg_close($conn);
} else {
    $db_status = 'disconnected';
}

echo json_encode([
    'status' => $status,
    'service' => 'royal-backend',
    'version' => '2.2',
    'database' => $db_status,
    'time' => date('c'),
], JSON_PRETTY_PRINT);
