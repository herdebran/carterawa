<?php
// Guardar el cuerpo crudo para depuración
file_put_contents('php://stderr', "Webhook recibido: " . file_get_contents('php://input') . "\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

require_once __DIR__ . '/../../app/config/db.php';

$input = json_decode(file_get_contents('php://input'), true);

// Log del input
error_log("Input recibido: " . print_r($input, true));

if (!isset($input['data']['key']['remoteJid'])) {
    http_response_code(400);
    error_log("Formato inválido");
    exit;
}

$remoteJid = $input['data']['key']['remoteJid'];
$contact_phone = preg_replace('/[^0-9]/', '', explode('@', $remoteJid)[0]);
$content = $input['data']['message']['conversation'] ??
    $input['data']['message']['extendedTextMessage']['text'] ??
    '[Archivo]';

error_log("Mensaje de $contact_phone: $content");

// 🔥 OBTENER user_id Y company_id POR LA INSTANCIA
$instance_name = $input['instance'] ?? null; // Evolución API puede enviar el nombre de la instancia

if ($instance_name) {
    // Buscar usuario por instancia
    $stmt = $pdo->prepare("
        SELECT id, company_id 
        FROM users 
        WHERE evolution_instance = ?
    ");
    $stmt->execute([$instance_name]);
    $user_data = $stmt->fetch();

    if ($user_data) {
        $user_id = $user_data['id'];
        $company_id = $user_data['company_id'];
    } else {
        error_log("Instancia no encontrada: $instance_name");
        http_response_code(404);
        exit;
    }
} else {
    // Fallback: usar un valor por defecto o fallar
    error_log("No se recibió instancia en el webhook");
    http_response_code(400);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE company_id = ? AND user_id = ? AND contact_phone = ?");
    $stmt->execute([$company_id, $user_id, $contact_phone]);
    $conv = $stmt->fetch();

    if ($conv) {
        $conversation_id = $conv['id'];
        $stmt = $pdo->prepare("UPDATE conversations SET unread_count = unread_count + 1, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$conversation_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO conversations (company_id, user_id, contact_phone) VALUES (?, ?, ?)");
        $stmt->execute([$company_id, $user_id, $contact_phone]);
        $conversation_id = $pdo->lastInsertId();
    }

    // Obtener user_id de la conversación
    $stmt = $pdo->prepare("SELECT user_id FROM conversations WHERE id = ?");
    $stmt->execute([$conversation_id]);
    $conv = $stmt->fetch();
    $user_id_from_conv = $conv['user_id'];

    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, company_id, user_id, direction, content) VALUES (?, ?, ?, 'in', ?)");
    $stmt->execute([$conversation_id, $company_id, $user_id_from_conv, $content]);

    error_log("Mensaje guardado en BD para user_id=$user_id, company_id=$company_id");
    http_response_code(200);
} catch (Exception $e) {
    error_log("Error en BD: " . $e->getMessage());
    http_response_code(500);
}
?>