<?php
// ✅ Iniciar sesión al principio (solo si no es una acción interna)
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Acciones internas (desde localhost) no requieren autenticación ni sesión
if ($action === 'connected') {
    require_once __DIR__ . '/../../app/config/db.php';
    if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
        http_response_code(403);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $session_id = (int)($input['session_id'] ?? 0);
    $phone = $input['phone'] ?? '';

    $stmt = $pdo->prepare("SELECT id FROM whatsapp_sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE whatsapp_sessions SET is_connected = TRUE, phone = ? WHERE id = ?");
        $stmt->execute([$phone, $session_id]);
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Sesión no encontrada']);
    }
    exit;
}

// ✅ Para el resto de acciones, se requiere sesión
session_start(); // ← ¡ESTO FALTABA!

require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/Auth.php';
Auth::init($pdo);

$user = Auth::user();
if (!$user || !$user['id'] || !$user['company_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($action === 'request_qr') {
    $input = json_decode(file_get_contents('php://input'), true);
    $session_id = (int)($input['session_id'] ?? 0);

    // Validar que la sesión pertenece al usuario y empresa
    $stmt = $pdo->prepare("SELECT id FROM whatsapp_sessions WHERE id = ? AND user_id = ? AND company_id = ?");
    $stmt->execute([$session_id, $user['id'], $user['company_id']]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
        exit;
    }

    // Llamar al microservicio de Node.js
    $node_url = 'http://127.0.0.1:3001/qr/' . $session_id;
    $ch = curl_init($node_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        echo json_encode(['success' => true, 'qr' => base64_encode($response)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo generar el QR']);
    }

} elseif ($action === 'check_status') {
    $session_id = (int)($_GET['session_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT is_connected, phone FROM whatsapp_sessions WHERE id = ? AND user_id = ? AND company_id = ?");
    $stmt->execute([$session_id, $user['id'], $user['company_id']]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        echo json_encode(['connected' => false]);
    } else {
        echo json_encode([
            'connected' => (bool)$session['is_connected'],
            'phone' => $session['phone']
        ]);
    }
// ... después de 'check_status'

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}