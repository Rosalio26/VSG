<?php

function tryActivateAccount(mysqli $db, int $userId): void
{
    $stmt = $db->prepare("
        SELECT public_id, email_verified_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        return;
    }

    // ✅ CONDIÇÃO FINAL
    if (!empty($user['public_id']) && !empty($user['email_verified_at'])) {
        $stmt = $db->prepare("
            UPDATE users
            SET status = 'active',
                registration_step = 'completed'
            WHERE id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    }
}
