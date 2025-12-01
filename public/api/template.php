<?php
session_start();
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/Auth.php';
Auth::init($pdo);

if (!Auth::check() || Auth::user()['role'] !== 'admin') {
    http_response_code(403);
    exit;
}

$user = Auth::user();

// Eliminar
if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM templates WHERE id = ? AND company_id = ?");
    $stmt->execute([$_GET['id'], $user['company_id']]);
    header('Location: /templates.php?msg=deleted');
    exit;
}

// Crear o actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!$name || !$content) {
        http_response_code(400);
        exit;
    }

    if ($id) {
        // Actualizar
        $stmt = $pdo->prepare("UPDATE templates SET name = ?, content = ? WHERE id = ? AND company_id = ?");
        $stmt->execute([$name, $content, $id, $user['company_id']]);
        header('Location: /templates.php?msg=updated');
    } else {
        // Crear
        $stmt = $pdo->prepare("INSERT INTO templates (company_id, name, content) VALUES (?, ?, ?)");
        $stmt->execute([$user['company_id'], $name, $content]);
        header('Location: /templates.php?msg=created');
    }
    exit;
}

http_response_code(400);
?>