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
$input = json_decode(file_get_contents('php://input'), true);
$phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
$status = $input['status'] ?? 'pendiente';

// Validar estado
$allowed = ['pendiente', 'interesado', 'no_interesa', 'vendido', 'contactar_despues'];
if (!in_array($status, $allowed)) {
    http_response_code(400);
    exit;
}

// 1. Actualizar estado en prospects
$stmt = $pdo->prepare("
    UPDATE prospects 
    SET status = ? 
    WHERE company_id = ? AND phone = ?
");
$stmt->execute([$status, $user['company_id'], $phone]);

// 2. Si no existe conversación, crearla con datos de prospects
$stmt = $pdo->prepare("
    SELECT id FROM conversations 
    WHERE company_id = ? AND user_id = ? AND contact_phone = ?
");
$stmt->execute([$user['company_id'], $user['id'], $phone]);
$exists = $stmt->fetch();

if (!$exists) {
    // Obtener datos de prospects
    $stmt = $pdo->prepare("
        SELECT name, lastname 
        FROM prospects 
        WHERE company_id = ? AND phone = ?
    ");
    $stmt->execute([$user['company_id'], $phone]);
    $prospect = $stmt->fetch();

    if ($prospect) {
        $stmt = $pdo->prepare("
            INSERT INTO conversations (company_id, user_id, contact_phone, contact_name, contact_lastname)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['company_id'],
            $user['id'],
            $phone,
            $prospect['name'] ?: null,
            $prospect['lastname'] ?: null
        ]);
    }
}

echo json_encode(['success' => true]);
?>