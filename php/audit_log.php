<?php
declare(strict_types=1);

function ensureAuditLogTable(mysqli $conn): bool
{
    return $conn->query(
        'CREATE TABLE IF NOT EXISTS audit_logs (
            log_id INT NOT NULL AUTO_INCREMENT,
            user_id INT NULL,
            action VARCHAR(100) NOT NULL,
            details VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (log_id),
            INDEX (created_at),
            CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    ) !== false;
}

function recordAudit(mysqli $conn, ?int $userId, string $action, string $details): bool
{
    if (!ensureAuditLogTable($conn)) {
        return false;
    }

    $statement = $conn->prepare(
        'INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)'
    );
    if ($statement === false) {
        return false;
    }
    $statement->bind_param('iss', $userId, $action, $details);
    $success = $statement->execute();
    $statement->close();
    return $success;
}
