<?php
// Mock PHP server handling in a simple script to allow login
// Since we cannot run the full app, we need to bypass login
session_start();
$_SESSION["admin_logged_in"] = true;
$_SESSION["admin_id"] = "1";
$_SESSION["admin_username"] = "superadmin";
$_SESSION["admin_role"] = "super_admin";
header("Location: admin.php");
exit;
?>
