<?php
// sync/config.php — DB config + VPS connection for direct sync

define('DB_HOST', '192.168.18.189');
define('DB_PORT', '5444');
define('DB_NAME', 'i4_ROYAL');
define('DB_USER', 'admin');
define('DB_PASS', '2356988');

// VPS direct sync (rsync via SSH) — gantikan git push
define('VPS_HOST', '103.93.133.60');
define('VPS_USER', 'royaladmin');
define('VPS_SSH_KEY', 'C:\royalserver.pem');
define('VPS_TARGET_DIR', '/var/www/royalkomputer');
define('VPS_SSH_PORT', 22);

date_default_timezone_set('Asia/Jakarta');

function getDBConnection() {
    $conn_string = "host=" . DB_HOST . " port=" . DB_PORT
        . " dbname=" . DB_NAME . " user=" . DB_USER
        . " password=" . DB_PASS . " connect_timeout=3";
    return @pg_connect($conn_string);
}
