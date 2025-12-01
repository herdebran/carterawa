<?php
session_start();
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/core/Layout.php';
Auth::init($pdo);
Auth::requireLogin();
$user = Auth::user();

// Solo admins
if ($user['role'] !== 'admin') {
    header('Location: /conversations.php');
    exit;
}

// Obtener nombre de la empresa
$stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
$stmt->execute([$user['company_id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
$user['company_name'] = $company['name'] ?? 'Empresa';

// Manejar resultado de importación
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;
$stats = $_SESSION['import_stats'] ?? null;
unset($_SESSION['import_stats']);

$contenido = '
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h1 class="text-2xl font-bold text-slate-800 mb-2">Importar Prospectos</h1>
        <p class="text-slate-600 mb-6">Sube un archivo CSV con las columnas: <code>APELLIDO, NOMBRE, TELEFONO, DNI (opcional), REPARTICION (opcional)</code></p>';

if ($error): 
    $contenido .= '<div class="mb-4 p-3 bg-red-50 text-red-700 rounded-lg">' . htmlspecialchars($error) . '</div>';
elseif ($success): 
    $contenido .= '<div class="mb-4 p-3 bg-green-50 text-green-700 rounded-lg">✅ Importación exitosa: ' . $stats['imported'] . ' nuevos prospectos.</div>';
endif;

$contenido .= '
        <form action="/api/import-prospects.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Archivo CSV</label>
                <input type="file" name="file" accept=".csv" required 
                       class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <p class="mt-1 text-xs text-slate-500">Formato: UTF-8, separado por comas. Primera fila = encabezados.</p>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                Importar Prospectos
            </button>
        </form>

        <div class="mt-8">
            <h2 class="text-lg font-semibold mb-3">Formato de ejemplo (CSV)</h2>
            <pre class="bg-slate-800 text-green-400 p-4 rounded-lg text-sm overflow-x-auto">
APELLIDO,NOMBRE,TELEFONO,DNI,REPARTICION
Gonzalez,Juan,5491122334455,30123456,Administración
Pérez,María,5491199887766,,Ventas
            </pre>
        </div>
    </div>
</div>
';

Layout::render('Importar Prospectos', $contenido, $user);
?>