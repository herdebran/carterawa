<?php
session_start();
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/Auth.php';
Auth::init($pdo);

if (!Auth::check()) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$user = Auth::user();
$input = json_decode(file_get_contents('php://input'), true);
$phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
$name = trim($input['name'] ?? '');
$lastname = trim($input['lastname'] ?? '');

if (!$phone) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE conversations 
    SET contact_name = ?, contact_lastname = ? 
    WHERE company_id = ? AND user_id = ? AND contact_phone = ?
");
$stmt->execute([$name ?: null, $lastname ?: null, $user['company_id'], $user['id'], $phone]);

echo json_encode(['success' => true]);
?>