<?php
/**
 * StudentHub - Process User Registration
 * Secure User Registration with MySQLi, Duplicate Email Check & Password Hashing
 */

declare(strict_types=1);

require_once __DIR__ . '/db_connect_mysqli.php';

function renderPage(string $title, string $heading, string $message, array $errors = [], bool $isSuccess = false): void
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeHeading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub | ' . $safeTitle . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-body-tertiary d-flex flex-column min-vh-100 justify-content-center py-5">
    <main class="container">
        <div class="card border-0 shadow-sm rounded-4 mx-auto overflow-hidden" style="max-width: 580px;">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex align-items-center gap-2 mb-4">
                    <span class="badge ' . ($isSuccess ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle') . ' px-3 py-2 rounded-pill fs-6">
                        <i class="bi ' . ($isSuccess ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill') . ' me-1"></i> ' . ($isSuccess ? 'Registration Completed' : 'Registration Alert') . '
                    </span>
                </div>
                <h1 class="h3 fw-bold mb-2">' . $safeHeading . '</h1>
                <p class="text-secondary mb-4">' . $safeMessage . '</p>';

    if (!empty($errors)) {
        echo '<div class="alert alert-danger rounded-3 mb-4">
                <ul class="mb-0 ps-3">';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '  </ul>
              </div>';
    }

    echo '      <div class="d-flex flex-wrap gap-2 pt-2">';
    if ($isSuccess) {
        echo '      <a class="btn btn-primary rounded-pill px-4" href="../pages/login.html"><i class="bi bi-box-arrow-in-right me-1"></i> Proceed to Login</a>
                    <a class="btn btn-outline-secondary rounded-pill px-4" href="../pages/register_user.html">Register Another User</a>';
    } else {
        echo '      <a class="btn btn-primary rounded-pill px-4" href="javascript:history.back()"><i class="bi bi-arrow-left me-1"></i> Go Back &amp; Correct</a>
                    <a class="btn btn-outline-secondary rounded-pill px-4" href="../pages/register_user.html">New Form</a>';
    }
    echo '      </div>
            </div>
        </div>
    </main>
</body>
</html>';
}

// 1. Check Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    renderPage('Invalid Request', 'Invalid Request Method', 'Please submit the registration form using POST.');
    exit;
}

// 2. Read and sanitize form inputs
$name = trim(strip_tags($_POST['name'] ?? $_POST['full_name'] ?? ''));
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$errors = [];

// 3. Server-side Validation
if ($name === '') {
    $errors[] = 'Full Name is required.';
} elseif (!preg_match("/^[A-Za-z]+(?:[ '-][A-Za-z]+)*$/", $name)) {
    $errors[] = 'Name can contain letters, spaces, apostrophes, and hyphens only.';
}

if ($email === '') {
    $errors[] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

if ($password === '') {
    $errors[] = 'Password is required.';
} elseif (strlen($password) < 8) {
    $errors[] = 'Password must contain at least 8 characters.';
}

if ($confirmPassword !== '' && $password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    http_response_code(422);
    renderPage('Validation Error', 'Registration Failed', 'Please fix the errors listed below and try again.', $errors);
    exit;
}

// 4. Duplicate Email Check using MySQLi Prepared Statement
$check_sql = 'SELECT id FROM users WHERE email = ?';
$check_stmt = $conn->prepare($check_sql);
if (!$check_stmt) {
    http_response_code(500);
    renderPage('Database Error', 'System Error', 'Database prepare statement failed: ' . $conn->error);
    exit;
}

$check_stmt->bind_param('s', $email);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    $check_stmt->close();
    http_response_code(409);
    renderPage(
        'Duplicate Account',
        'Email Already Registered',
        'An account with the email ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . ' already exists. Please sign in or use a different email address.'
    );
    exit;
}
$check_stmt->close();

// 5. Secure Password Hashing
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// 6. Insert User using MySQLi Prepared Statement
$insert_sql = 'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)';
$insert_stmt = $conn->prepare($insert_sql);
if (!$insert_stmt) {
    http_response_code(500);
    renderPage('Database Error', 'System Error', 'Database insert prepare statement failed: ' . $conn->error);
    exit;
}

$default_role = 'student';
$insert_stmt->bind_param('ssss', $name, $email, $hashed_password, $default_role);

if ($insert_stmt->execute()) {
    $insert_stmt->close();
    $conn->close();
    http_response_code(200);
    renderPage(
        'Registration Successful',
        'Welcome to StudentHub, ' . $name . '!',
        'Your user account has been securely created in the database with password hashing.',
        [],
        true
    );
} else {
    // Check for duplicate key error code 1062 as fail-safe
    if ($conn->errno === 1062) {
        $insert_stmt->close();
        $conn->close();
        http_response_code(409);
        renderPage('Duplicate Account', 'Email Already Registered', 'This email is already registered. Please log in.');
    } else {
        $errorMsg = $insert_stmt->error;
        $insert_stmt->close();
        $conn->close();
        http_response_code(500);
        renderPage('Database Error', 'Registration Failed', 'Could not complete registration: ' . $errorMsg);
    }
}
?>
