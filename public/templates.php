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

// Obtener empresa
$stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
$stmt->execute([$user['company_id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
$user['company_name'] = $company['name'] ?? 'Empresa';

// Manejar mensajes
$message = $_GET['msg'] ?? null;

// Modo: crear o editar
$edit_id = $_GET['edit'] ?? null;
$template = null;
if ($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ? AND company_id = ?");
    $stmt->execute([$edit_id, $user['company_id']]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Listar plantillas
$stmt = $pdo->prepare("SELECT * FROM templates WHERE company_id = ? ORDER BY name");
$stmt->execute([$user['company_id']]);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$contenido = '
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-slate-800">Plantillas de Mensajes</h1>
            <button onclick="document.getElementById(\'form-section\').classList.remove(\'hidden\')" 
                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                ' . ($edit_id ? 'Editar' : 'Nueva plantilla') . '
            </button>
        </div>';

// Mostrar mensaje
if ($message) {
    $msg_text = match($message) {
        'created' => 'Plantilla creada exitosamente.',
        'updated' => 'Plantilla actualizada.',
        'deleted' => 'Plantilla eliminada.',
        default => ''
    };
    $contenido .= '<div class="mb-4 p-3 bg-green-50 text-green-700 rounded-lg">' . $msg_text . '</div>';
}

// Formulario
$contenido .= '
        <div id="form-section" class="' . ($edit_id ? '' : 'hidden ') . 'mb-8 p-4 border border-gray-200 rounded-lg">
            <h2 class="text-lg font-semibold mb-4">' . ($edit_id ? 'Editar plantilla' : 'Nueva plantilla') . '</h2>
            <form method="POST" action="/api/template.php" class="space-y-4">
                <input type="hidden" name="id" value="' . ($edit_id ?? '') . '">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nombre de la plantilla</label>
                    <input type="text" name="name" required 
                           value="' . htmlspecialchars($template['name'] ?? '') . '"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <p class="mt-1 text-xs text-slate-500">Ej: Recordatorio de pago, Bienvenida, etc.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Contenido del mensaje</label>
                    <textarea name="content" rows="4" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">' . htmlspecialchars($template['content'] ?? '') . '</textarea>
                    <p class="mt-1 text-xs text-slate-500">
                        Usa <code>{nombre}</code> o <code>{apellido}</code> para personalizar. 
                        Ej: <em>"Hola {nombre}, su deuda vence hoy."</em>
                    </p>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        ' . ($edit_id ? 'Actualizar' : 'Guardar') . '
                    </button>
                    <button type="button" onclick="document.getElementById(\'form-section\').classList.add(\'hidden\')" 
                            class="bg-gray-200 text-slate-800 px-4 py-2 rounded-md hover:bg-gray-300">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>

        <!-- Listado -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vista previa</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ' . (empty($templates) ? '
                    <tr>
                        <td colspan="3" class="px-4 py-4 text-center text-gray-500">No hay plantillas creadas.</td>
                    </tr>
                    ' : '');
                    foreach ($templates as $t) {
                        $preview = htmlspecialchars(substr($t['content'], 0, 60)) . (strlen($t['content']) > 60 ? '...' : '');
                        $contenido .= '
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium">' . htmlspecialchars($t['name']) . '</td>
                            <td class="px-4 py-3 text-sm text-gray-600">' . $preview . '</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="?edit=' . $t['id'] . '" 
                                   class="text-blue-600 hover:text-blue-900 mr-3">Editar</a>
                                <a href="/api/template.php?action=delete&id=' . $t['id'] . '" 
                                   class="text-red-600 hover:text-red-800"
                                   onclick="return confirm(\'¿Eliminar esta plantilla?\')">Eliminar</a>
                            </td>
                        </tr>';
                    }
                $contenido .= '
                </tbody>
            </table>
        </div>
    </div>
</div>
';

Layout::render('Plantillas', $contenido, $user);
?>