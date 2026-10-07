<?php
/**
 * StudentHub - Database Connection Script
 * MySQL Database Connection using MySQL Statements
 */

declare(strict_types=1);

$host = 'localhost';
$username = 'root';
$password = 'root';
$dbname = 'studenthub';

// Establish MySQL connection using MySQLi
$conn = mysqli_connect($host, $username, $password, $dbname);

// Check connection and handle errors gracefully
if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

// Set character set to utf8mb4 for full Unicode support
mysqli_set_charset($conn, 'utf8mb4');
?>