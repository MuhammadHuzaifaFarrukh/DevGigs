<?php
$host    = 'localhost';
$db      = 'devdb';
$user    = 'devuser';
$pass    = 'devuser'; // Change to your actual devuser password if different
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Log the real reason, show visitors a safe message.
    error_log('DevGigs DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Could not connect to the database. Check the settings in config/database.php.');
}
