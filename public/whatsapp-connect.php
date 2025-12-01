<?php
session_start();
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/config/whatsapp.php';
Auth::init($pdo);
Auth::requireLogin();
$user = Auth::user();

// Obtener nombre de la empresa
$stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
$stmt->execute([$user['company_id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
$user['company_name'] = $company['name'] ?? 'Empresa';

$whatsapp_config = require __DIR__ . '/../app/config/whatsapp.php';
$BASE_URL = $whatsapp_config['base_url'];
$API_TOKEN = $whatsapp_config['api_token'];

// Obtener instancia guardada
$stmt = $pdo->prepare("SELECT evolution_instance FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$instance_row = $stmt->fetch();

if (!$instance_row || !$instance_row['evolution_instance']) {
    die('<p style="color:red;">❌ Error: No tienes una instancia de Evolution API configurada. Configura tu perfil primero.</p>');
}

$instance_name = $instance_row['evolution_instance'];

//Verifico el estado de la instancia
$ch = curl_init("$BASE_URL/instance/fetchInstances?instance=$instance_name");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $API_TOKEN",
    "Content-Type: application/json"
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    die('<p style="color:red;">❌ Error al verificar el estado de la instancia. Código: ' . $http_code . '</p>');
}

$instances = json_decode($response, true);

// Buscar la instancia específica en la lista
$found_instance = null;
foreach ($instances as $inst) {
    if ($inst['name'] === $instance_name) {
        $found_instance = $inst;
        break;
    }
}

if (!$found_instance) {
    die('<p style="color:red;">❌ Error: La instancia "' . htmlspecialchars($instance_name) . '" no existe.</p>');
}

if ($found_instance['connectionStatus'] === 'open') {
    // ✅ Instancia conectada
    $phone = $found_instance['ownerJid'] ? preg_replace('/@.*/', '', $found_instance['ownerJid']) : null;

    $stmt = $pdo->prepare("UPDATE users SET is_whatsapp_connected = 1, whatsapp_phone = ? WHERE id = ?");
    $stmt->execute([$phone, $user['id']]);

    echo '<p>✅ WhatsApp ya está conectado como: <strong>' . htmlspecialchars($phone) . '</strong></p>';
    echo '<a href="/conversations.php">Ver conversaciones</a>';
    exit;
} else {
    // ❌ No conectada, mostrar QR
    $stmt = $pdo->prepare("UPDATE users SET is_whatsapp_connected = 0 WHERE id = ?");
    $stmt->execute([$user['id']]);
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Conectar WhatsApp</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: sans-serif; padding: 1rem; background: #f5f7fa; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        #qr-code { max-width: 100%; border: 1px solid #eee; border-radius: 8px; margin: 1rem 0; }
        .status { text-align: center; margin: 1rem 0; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Conectar WhatsApp</h2>
        <p>Escanea el código QR con tu celular.</p>
        <div class="status" id="status">Verificando instancia...</div>
        <img id="qr-code" style="display:none;">

        <script>
        const instanceName = '<?= $instance_name ?>';
        const baseUrl = '<?= $BASE_URL ?>';
        const apiToken = '<?= $API_TOKEN ?>';

        function getQrCode() {
            fetch(`${baseUrl}/instance/qrcode/${instanceName}`, {
                headers: { 'apikey': apiToken }
            })
            .then(res => {
                if (res.status === 200) {
                    return res.json();
                } else if (res.status === 404) {
                    document.getElementById('status').innerHTML = '<span style="color:red">La instancia no existe</span>';
                    return null;
                } else {
                    throw new Error('Error QR');
                }
            })
            .then(data => {
                if (data && data.qrCode) {
                    document.getElementById('qr-code').src = 'image/png;base64,' + data.qrCode;
                    document.getElementById('qr-code').style.display = 'block';
                    document.getElementById('status').textContent = 'Escanea el QR';
                    setTimeout(getQrCode, 5000); // poll cada 5s
                } else if (data === null) {
                    setTimeout(getQrCode, 2000);
                }
            })
            .catch(err => {
                document.getElementById('status').innerHTML = '<span style="color:red">Error al obtener QR</span>';
                console.error(err);
            });
        }

        function checkStatus() {
            fetch(`${baseUrl}/instance/status/${instanceName}`, {
                headers: { 'apikey': apiToken }
            })
            .then(res => res.json())
            .then(status => {
                if (status.status === 'open') {
                    // Actualizar estado en BD
                    fetch('/api/whatsapp-status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ connected: true, phone: status.wid?.user })
                    }).then(() => {
                        window.location.href = '/conversations.php';
                    });
                } else {
                    setTimeout(getQrCode, 2000);
                }
            });
        }

        // Iniciar
        checkStatus();
        </script>
    </div>
</body>
</html>