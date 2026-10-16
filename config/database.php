<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';

$dbHost = 'localhost';
$dbName = 'devdb';
$dbUser = 'devuser';
$dbPass = 'devuser'; 
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    $db->set_charset('utf8mb4');
} 
catch (mysqli_sql_exception $e) 
{
    error_log('DevGigs DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Could not connect to the database. Check the settings in config/database.php.');
}
