<?php
/**
 * Database connection using PDO
 */

$host = 'localhost';
$db   = 'event_site_db';
$user = 'root'; // Default XAMPP user
$pass = '';     // Default XAMPP password
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
    // In a real production environment, you should log the error and show a generic message.
    // For this development setup, we will exit with the error message.
    die("Database connection failed: " . $e->getMessage());
}

// The $pdo object is now ready for use in other files.
?>
