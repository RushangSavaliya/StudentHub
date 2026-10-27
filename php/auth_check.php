<?php
declare(strict_types=1);

const SESSION_TIMEOUT_SECONDS = 900;

function requireRole(string $requiredRole): void
{
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();

    if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
        header('Location: ../pages/login.html');
        exit;
    }

    if (
        isset($_SESSION['last_activity'])
        && time() - (int) $_SESSION['last_activity'] > SESSION_TIMEOUT_SECONDS
    ) {
        $_SESSION = [];
        session_destroy();
        header('Location: ../pages/login.html?timeout=1');
        exit;
    }

    $_SESSION['last_activity'] = time();

    if ($_SESSION['user_role'] !== $requiredRole) {
        $dashboard = $_SESSION['user_role'] === 'admin'
            ? 'admin_dashboard.php'
            : 'student_dashboard.php';
        header('Location: ../pages/' . $dashboard);
        exit;
    }
}

function requireApiRole(string $requiredRole): void
{
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();

    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication is required.']);
        exit;
    }

    if (
        isset($_SESSION['last_activity'])
        && time() - (int) $_SESSION['last_activity'] > SESSION_TIMEOUT_SECONDS
    ) {
        $_SESSION = [];
        session_destroy();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Your session expired. Please sign in again.']);
        exit;
    }

    $_SESSION['last_activity'] = time();

    if ($_SESSION['user_role'] !== $requiredRole) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not authorized for this operation.']);
        exit;
    }
}
