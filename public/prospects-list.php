<?php
session_start();
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/core/Layout.php';
Auth::init($pdo);
Auth::requireLogin();
$user = Auth::user();

if ($user['role'] !== 'admin') {
    header('Location: /conversations.php');
    exit;
}

// Función para obtener la clase de color del estado
function getStatusColorClass($status) {
    switch ($status) {
        case 'interesado':
            return 'text-green-600';
        case 'no_interesa':
            return 'text-red-600';
        case 'vendido':
            return 'text-blue-600';
        case 'contactar_despues':
            return 'text-yellow-600';
        default:
            return 'text-gray-400';
    }
}

// Función para obtener el texto del estado
function getStatusText($status) {
    switch ($status) {
        case 'interesado':
            return 'INTERESADO';
        case 'no_interesa':
            return 'NO INTERESA';
        case 'vendido':
            return 'LE VENDÍ';
        case 'contactar_despues':
            return 'CONTACTAR MÁS ADELANTE';
        default:
            return 'Sin clasificar';
    }
}

// Obtener nombre de la empresa
$stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
$stmt->execute([$user['company_id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
$user['company_name'] = $company['name'] ?? 'Empresa';

// Paginación
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Filtro por estado
$selected_status = $_GET['status'] ?? 'all';
// Filtro por repartición
$selected_reparticion = $_GET['reparticion'] ?? 'all';

// Obtener reparticiones únicas para el filtro
$reparticiones = [];
$stmt = $pdo->prepare("SELECT DISTINCT reparticion FROM prospects WHERE company_id = ? AND reparticion IS NOT NULL ORDER BY reparticion");
$stmt->execute([$user['company_id']]);
while ($row = $stmt->fetch()) {
    if ($row['reparticion']) {
        $reparticiones[] = $row['reparticion'];
    }
}

// Construir condición SQL
$status_condition = '';
$reparticion_condition = '';
$params = [$user['company_id']];

if ($selected_status !== 'all' && in_array($selected_status, ['pendiente', 'interesado', 'no_interesa', 'vendido', 'contactar_despues'])) {
    $status_condition = ' AND status = ?';
    $params[] = $selected_status;
}

if ($selected_reparticion !== 'all') {
    $reparticion_condition = ' AND reparticion = ?';
    $params[] = $selected_reparticion;
}

// URL base para paginación
$pagination_base = '?status=' . urlencode($selected_status) . '&reparticion=' . urlencode($selected_reparticion);

// Contar total
$count_sql = "SELECT COUNT(*) FROM prospects WHERE company_id = ? $status_condition $reparticion_condition";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();
$total_pages = ceil($total / $limit);

// Obtener prospectos
$sql = "SELECT id, lastname, name, phone, dni, reparticion, last_contacted_at, status
        FROM prospects
        WHERE company_id = ? $status_condition $reparticion_condition
        ORDER BY created_at DESC
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prospects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$contenido = '
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-slate-800">Prospectos</h1>
            <a href="/prospects.php" class="text-blue-600 hover:text-blue-800 font-medium">Importar nuevos</a>
        </div>

        <div class="mb-4 flex gap-4">
            <div>
                <label class="text-sm font-medium text-slate-700">Filtrar por estado:</label>
                <select onchange="location = this.value" class="ml-2 text-sm border border-gray-300 rounded px-2 py-1">
                    <option value="' . $pagination_base . '&page=1" ' . ($selected_status === 'all' ? 'selected' : '') . '>Todos</option>
                    <option value="' . $pagination_base . '&page=1&status=pendiente" ' . ($selected_status === 'pendiente' ? 'selected' : '') . '>Sin clasificar</option>
                    <option value="' . $pagination_base . '&page=1&status=interesado" ' . ($selected_status === 'interesado' ? 'selected' : '') . '>INTERESADO</option>
                    <option value="' . $pagination_base . '&page=1&status=no_interesa" ' . ($selected_status === 'no_interesa' ? 'selected' : '') . '>NO LE INTERESA</option>
                    <option value="' . $pagination_base . '&page=1&status=vendido" ' . ($selected_status === 'vendido' ? 'selected' : '') . '>LE VENDÍ</option>
                    <option value="' . $pagination_base . '&page=1&status=contactar_despues" ' . ($selected_status === 'contactar_despues' ? 'selected' : '') . '>CONTACTAR MÁS ADELANTE</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Filtrar por repartición:</label>
                <select onchange="location = this.value" class="ml-2 text-sm border border-gray-300 rounded px-2 py-1">
                    <option value="?status=' . urlencode($selected_status) . '&reparticion=all&page=1" ' . ($selected_reparticion === 'all' ? 'selected' : '') . '>Todas</option>
                    ' . implode('', array_map(function($r) use ($selected_status, $selected_reparticion) {
        return '<option value="?status=' . urlencode($selected_status) . '&reparticion=' . urlencode($r) . '&page=1" ' . ($selected_reparticion === $r ? 'selected' : '') . '>' . htmlspecialchars($r) . '</option>';
    }, $reparticiones)) . '
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Apellido</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teléfono</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DNI</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Repartición</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Último contacto</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ' . (empty($prospects) ? '
                    <tr>
                        <td colspan="8" class="px-4 py-4 text-center text-gray-500">No hay prospectos importados.</td>
                    </tr>
                    ' : implode('', array_map(function($p) {
        $last_contact = $p['last_contacted_at']
            ? '<span title="' . htmlspecialchars($p['last_contacted_at']) . '">' . date('d/m H:i', strtotime($p['last_contacted_at'])) . '</span>'
            : '<span class="text-gray-400">Nunca</span>';

        return '
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap">' . htmlspecialchars($p['lastname']) . '</td>
                            <td class="px-4 py-3 whitespace-nowrap">' . htmlspecialchars($p['name']) . '</td>
                            <td class="px-4 py-3 whitespace-nowrap font-mono">' . htmlspecialchars($p['phone']) . '</td>
                            <td class="px-4 py-3 whitespace-nowrap">' . ($p['dni'] ? htmlspecialchars($p['dni']) : '<span class="text-gray-400">—</span>') . '</td>
                            <td class="px-4 py-3 whitespace-nowrap">' . ($p['reparticion'] ? htmlspecialchars($p['reparticion']) : '<span class="text-gray-400">—</span>') . '</td>
                            <td class="px-4 py-3 whitespace-nowrap">' . $last_contact . '</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="' . getStatusColorClass($p['status'] ?? 'pendiente') . ' font-medium">' .
                                        getStatusText($p['status'] ?? 'pendiente') .
                                        '</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="/conversations.php?phone=' . urlencode($p['phone']) . '" 
                                   class="text-blue-600 hover:text-blue-900 font-medium flex items-center gap-1 justify-end">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zM7 8H5v2h2V8zm2 0h2v2H9V8zm6 0h-2v2h2V8z" clip-rule="evenodd" />
                                    </svg>
                                    Contactar
                                </a>
                            </td>
                        </tr>';
    }, $prospects))) . '
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        ' . ($total_pages > 1 ? '
        <div class="mt-6 flex justify-between items-center">
            <div class="text-sm text-gray-500">
                Mostrando ' . (($page - 1) * $limit + 1) . '–' . min($page * $limit, $total) . ' de ' . $total . ' prospectos
            </div>
            <div class="flex space-x-2">
                ' . ($page > 1 ? '<a href="' . $pagination_base . '&page=' . ($page - 1) . '" class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50">Anterior</a>' : '<span class="px-3 py-1 text-gray-400">Anterior</span>') . '
                <span class="px-3 py-1 text-gray-700">Página ' . $page . ' de ' . $total_pages . '</span>
                ' . ($page < $total_pages ? '<a href="' . $pagination_base . '&page=' . ($page + 1) . '" class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50">Siguiente</a>' : '<span class="px-3 py-1 text-gray-400">Siguiente</span>') . '
            </div>
        </div>
        ' : '') . '
    </div>
</div>
';

Layout::render('Prospectos', $contenido, $user);
?>