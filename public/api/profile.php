<?php
session_start();
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/Auth.php';
Auth::init($pdo);

if (!Auth::check()) {
    http_response_code(403);
    exit;
}

$user = Auth::user();
$action = $_GET['action'] ?? '';

if ($action === 'whatsapp') {
    $phone = preg_replace('/[^0-9]/', '', $_POST['whatsapp_phone'] ?? '');
    $instance = trim($_POST['evolution_instance'] ?? '');

    if ($phone && $instance) {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET whatsapp_phone = ?, evolution_instance = ? 
            WHERE id = ?
        ");
        $stmt->execute([$phone, $instance, $user['id']]);
    }

    header('Location: /profile.php?msg=whatsapp_updated');
    exit;
}

if ($action === 'password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new !== $confirm) {
        header('Location: /profile.php?error=password_mismatch');
        exit;
    }

    // Verificar contraseña actual
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $db_pass = $stmt->fetchColumn();

    if (!password_verify($current, $db_pass)) {
        header('Location: /profile.php?error=wrong_password');
        exit;
    }

    // Actualizar contraseña
    $new_hash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$new_hash, $user['id']]);

    header('Location: /profile.php?msg=password_updated');
    exit;
}

http_response_code(400);
?>