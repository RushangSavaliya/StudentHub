<?php
declare(strict_types=1);

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/login.html');
    exit;
}

$email = trim((string) ($_POST['email'] ?? $_POST['login_id'] ?? ''));
$loginPassword = (string) ($_POST['password'] ?? '');

if ($email === '' || $loginPassword === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../pages/login.html?error=1');
    exit;
}

require_once __DIR__ . '/db_connect_mysqli.php';
require_once __DIR__ . '/audit_log.php';

$stmt = $conn->prepare('SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1');
if ($stmt === false) {
    $conn->close();
    http_response_code(500);
    exit('Unable to process login.');
}

$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows !== 1) {
    $stmt->close();
    $conn->close();
    header('Location: ../pages/login.html?error=1');
    exit;
}

$stmt->bind_result($userId, $userName, $userEmail, $passwordHash, $userRole);
$stmt->fetch();

if (!password_verify($loginPassword, $passwordHash)) {
    $stmt->close();
    $conn->close();
    header('Location: ../pages/login.html?error=1');
    exit;
}

if (!in_array($userRole, ['admin', 'student'], true)) {
    $stmt->close();
    $conn->close();
    header('Location: ../pages/login.html?error=1');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['user_name'] = $userName;
$_SESSION['user_email'] = $userEmail;
$_SESSION['user_role'] = $userRole;
$_SESSION['last_activity'] = time();
recordAudit($conn, (int) $userId, 'LOGIN', 'User signed in successfully.');

$stmt->close();
$conn->close();

$dashboard = $userRole === 'admin' ? 'admin_dashboard.php' : 'student_dashboard.php';
header('Location: ../pages/' . $dashboard);
exit;
