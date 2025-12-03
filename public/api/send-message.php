<?php
session_start();
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/Auth.php';
require_once __DIR__ . '/../../app/config/whatsapp.php';
Auth::init($pdo);

if (!Auth::check()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$user = Auth::user();

// 🔥 OBTENER LA INSTANCIA DEL USUARIO
$stmt = $pdo->prepare("SELECT evolution_instance FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$instance_row = $stmt->fetch();

if (!$instance_row || !$instance_row['evolution_instance']) {
    // Si no tiene instancia guardada, usar una por defecto o fallar
    echo json_encode(['success' => false, 'message' => 'Usuario no tiene instancia de WhatsApp configurada']);
    exit;
}

$instance_name = $instance_row['evolution_instance'];

$input = json_decode(file_get_contents('php://input'), true);
$phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
$text = trim($input['text'] ?? '');

if (!$phone || !$text) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Número y mensaje requeridos']);
    exit;
}

//Procesar saltos de línea
$text = str_replace('\r\n', "\n", $text);
$text = str_replace('\r', "\n", $text);
$text = str_replace('\n', "\n", $text);
$text = implode("\n", array_map('trim', explode("\n", $text)));

$whatsapp_config = require __DIR__ . '/../../app/config/whatsapp.php';
$BASE_URL = $whatsapp_config['base_url'];
$API_TOKEN = $whatsapp_config['api_token'];

// ✅ FORMATO SIMPLE Y COMPATIBLE
$data = [
    'number' => $phone,
    'text' => $text
];

// 🔥 USAR LA INSTANCIA DEL USUARIO EN EL ENDPOINT
$ch = curl_init("$BASE_URL/message/sendText/$instance_name");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $API_TOKEN",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 201 || $http_code === 200) {
    // Guardar mensaje en la BD
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE company_id = ? AND user_id = ? AND contact_phone = ?");
    $stmt->execute([$user['company_id'], $user['id'], $phone]);
    $conv = $stmt->fetch();

    if ($conv) {
        $conversation_id = $conv['id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO conversations (company_id, user_id, contact_phone) VALUES (?, ?, ?)");
        $stmt->execute([$user['company_id'], $user['id'], $phone]);
        $conversation_id = $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, company_id, user_id, direction, content) VALUES (?, ?, ?, 'out', ?)");
    $stmt->execute([$conversation_id, $user['company_id'], $user['id'], $text]);

    // ✅ ACTUALIZAR last_contacted_at en prospects (si existe)
    $stmt = $pdo->prepare("
        UPDATE prospects 
        SET last_contacted_at = NOW() 
        WHERE company_id = ? AND phone = ?
    ");
    $stmt->execute([$user['company_id'], $phone]);

    echo json_encode(['success' => true]);
} else {
    error_log("Evolution API error ($http_code): " . $response);
    echo json_encode(['success' => false, 'message' => 'Error al enviar: ' . substr($response, 0, 100)]);
}
?>