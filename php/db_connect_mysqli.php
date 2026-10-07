<?php
/**
 * StudentHub - Database Connection Script (MySQLi)
 * MySQLi Database Connection
 */

declare(strict_types=1);

$host = 'localhost';
$username = 'root';
$password = 'root';
$dbname = 'studenthub';

// Establish MySQL connection using MySQLi (Object-Oriented style)
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection and handle errors gracefully
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Set character set to utf8mb4 for full Unicode support
$conn->set_charset('utf8mb4');
?>
