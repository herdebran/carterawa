<?php
session_start();
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/core/Layout.php';
Auth::init($pdo);
Auth::requireLogin();
$user = Auth::user();

// Obtener nombre de la empresa
$stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
$stmt->execute([$user['company_id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
$user['company_name'] = $company['name'] ?? 'Empresa';

// Filtro por usuario (solo para admin)
$selected_user_id = $user['role'] === 'admin' ? ($_GET['user'] ?? $user['id']) : $user['id'];

// Si es admin, obtener lista de operadores
$operators = [];
if ($user['role'] === 'admin') {
    $stmt = $pdo->prepare("
        SELECT id, email, role 
        FROM users 
        WHERE company_id = ? AND role = 'operador' 
        ORDER BY email
    ");
    $stmt->execute([$user['company_id']]);
    $operators = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener métricas
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM messages WHERE company_id = ? AND user_id = ? AND direction = 'in' AND DATE(sent_at) = CURDATE()) as mensajes_recibidos_hoy,
        (SELECT COUNT(*) FROM messages WHERE company_id = ? AND user_id = ? AND direction = 'out' AND DATE(sent_at) = CURDATE()) as mensajes_enviados_hoy,
        (SELECT COUNT(*) FROM conversations WHERE company_id = ? AND user_id = ?) as total_conversaciones,
        (SELECT COUNT(*) FROM prospects p
         INNER JOIN conversations c ON p.company_id = c.company_id AND p.phone = c.contact_phone
         WHERE p.company_id = ? AND c.user_id = ? AND p.last_contacted_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)) as contactados_24h
");

$stmt->execute([
    $user['company_id'], $selected_user_id,
    $user['company_id'], $selected_user_id,
    $user['company_id'], $selected_user_id,
    $user['company_id'], $selected_user_id
]);
$metrics = $stmt->fetch(PDO::FETCH_ASSOC);

// Métricas por estado (filtrar por user_id en conversations, no en prospects)
$stmt = $pdo->prepare("
    SELECT p.status, COUNT(*) as count
    FROM prospects p
    INNER JOIN conversations c ON p.company_id = c.company_id AND p.phone = c.contact_phone
    WHERE p.company_id = ? AND c.user_id = ?
    GROUP BY p.status
");

$stmt->execute([$user['company_id'], $selected_user_id]);
$estado_counts = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $estado_counts[$row['status']] = $row['count'];
}

$contenido = '
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
            ' . ($user['role'] === 'admin' ? '
            <div>
                <label class="text-sm font-medium text-slate-700 mr-2">Filtrar por operador:</label>
                <select onchange="location = this.value" class="text-sm border border-gray-300 rounded px-2 py-1">
                    <option value="?user=' . $user['id'] . '" ' . ($selected_user_id === $user['id'] ? 'selected' : '') . '>Yo (' . htmlspecialchars($user['email']) . ')</option>
                    ' . implode('', array_map(function($op) use ($selected_user_id) {
                        return '<option value="?user=' . $op['id'] . '" ' . ($selected_user_id === $op['id'] ? 'selected' : '') . '>' . htmlspecialchars($op['email']) . '</option>';
                    }, $operators)) . '
                </select>
            </div>
            ' : '') . '
        </div>

        <!-- Métricas principales -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                <h3 class="text-sm font-medium text-blue-800">Mensajes hoy</h3>
                <p class="text-2xl font-bold text-blue-900">' . ($metrics['mensajes_recibidos_hoy'] ?? 0) . '</p>
                <p class="text-xs text-blue-600">recibidos</p>
            </div>
            <div class="bg-green-50 p-4 rounded-lg border border-green-100">
                <h3 class="text-sm font-medium text-green-800">Mensajes hoy</h3>
                <p class="text-2xl font-bold text-green-900">' . ($metrics['mensajes_enviados_hoy'] ?? 0) . '</p>
                <p class="text-xs text-green-600">enviados</p>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg border border-purple-100">
                <h3 class="text-sm font-medium text-purple-800">Conversaciones</h3>
                <p class="text-2xl font-bold text-purple-900">' . ($metrics['total_conversaciones'] ?? 0) . '</p>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-100">
                <h3 class="text-sm font-medium text-yellow-800">Contactados 24h</h3>
                <p class="text-2xl font-bold text-yellow-900">' . ($metrics['contactados_24h'] ?? 0) . '</p>
            </div>
        </div>

        <!-- Gráfico de estados -->
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Prospectos por estado (de mis conversaciones)</h3>
            <div class="flex flex-wrap gap-4">
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-green-500 rounded mr-2"></div>
                    <span class="text-sm">INTERESADO: ' . ($estado_counts['interesado'] ?? 0) . '</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-red-500 rounded mr-2"></div>
                    <span class="text-sm">NO LE INTERESA: ' . ($estado_counts['no_interesa'] ?? 0) . '</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-blue-700 rounded mr-2"></div>
                    <span class="text-sm">LE VENDÍ: ' . ($estado_counts['vendido'] ?? 0) . '</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-yellow-500 rounded mr-2"></div>
                    <span class="text-sm">CONTACTAR MÁS ADELANTE: ' . ($estado_counts['contactar_despues'] ?? 0) . '</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-gray-400 rounded mr-2"></div>
                    <span class="text-sm">Sin clasificar: ' . ($estado_counts['pendiente'] ?? 0) . '</span>
                </div>
            </div>
        </div>

        <!-- Últimas conversaciones -->
        <div>
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Últimas conversaciones</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contacto</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teléfono</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Último mensaje</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ' . (function() use ($pdo, $user, $selected_user_id) {
                            $stmt = $pdo->prepare("
                                SELECT 
                                    c.contact_name, 
                                    c.contact_lastname, 
                                    c.contact_phone, 
                                    c.updated_at,
                                    p.status
                                FROM conversations c
                                LEFT JOIN prospects p ON c.company_id = p.company_id AND c.contact_phone = p.phone
                                WHERE c.company_id = ? AND c.user_id = ?
                                ORDER BY c.updated_at DESC
                                LIMIT 10
                            ");
                            $stmt->execute([$user['company_id'], $selected_user_id]);
                            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            $html = '';
                            foreach ($conversations as $conv) {
                                $name = trim(($conv['contact_name'] ?? '') . ' ' . ($conv['contact_lastname'] ?? ''));
                                if (!$name) $name = $conv['contact_phone'];
                                
                                switch ($conv['status'] ?? 'pendiente') {
                                    case 'interesado':
                                        $status_label = '<span class="text-green-600">INTERESADO</span>';
                                        break;
                                    case 'no_interesa':
                                        $status_label = '<span class="text-red-600">NO LE INTERESA</span>';
                                        break;
                                    case 'vendido':
                                        $status_label = '<span class="text-blue-700">LE VENDÍ</span>';
                                        break;
                                    case 'contactar_despues':
                                        $status_label = '<span class="text-yellow-600">CONTACTAR MÁS ADELANTE</span>';
                                        break;
                                    default:
                                        $status_label = '<span class="text-gray-400">Sin clasificar</span>';
                                }

                                $html .= '
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap">' . htmlspecialchars($name) . '</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono">' . htmlspecialchars($conv['contact_phone']) . '</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">' . date('d/m H:i', strtotime($conv['updated_at'])) . '</td>
                                    <td class="px-4 py-3 whitespace-nowrap">' . $status_label . '</td>
                                </tr>';
                            }
                            return $html ?: '<tr><td colspan="4" class="px-4 py-4 text-center text-gray-500">No hay conversaciones</td></tr>';
                        })() . '
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
';

Layout::render('Dashboard', $contenido, $user);
?>