<?php
// sync/config.php — Minimal DB config for headless IPOS sync agent
// No session, no admin helpers, no JSON file management

define('DB_HOST', '192.168.18.189');
define('DB_PORT', '5444');
define('DB_NAME', 'i4_ROYAL');
define('DB_USER', 'admin');
define('DB_PASS', '2356988');

date_default_timezone_set('Asia/Jakarta');

function getDBConnection() {
    $conn_string = "host=" . DB_HOST . " port=" . DB_PORT
        . " dbname=" . DB_NAME . " user=" . DB_USER
        . " password=" . DB_PASS . " connect_timeout=3";
    return @pg_connect($conn_string);
}
