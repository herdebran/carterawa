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

// Cargar datos del perfil
$stmt = $pdo->prepare("
    SELECT whatsapp_phone, evolution_instance, is_whatsapp_connected 
    FROM users 
    WHERE id = ?
");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

$contenido = '
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h1 class="text-2xl font-bold text-slate-800 mb-6">Mi Perfil</h1>
        
        <div class="mb-6 p-4 bg-blue-50 rounded-lg">
            <p class="text-blue-800"><strong>Usuario:</strong> ' . htmlspecialchars($user['email']) . '</p>
            <p class="text-blue-800"><strong>Rol:</strong> ' . htmlspecialchars($user['role']) . '</p>
            <p class="text-blue-800"><strong>Empresa:</strong> ' . htmlspecialchars($user['company_name']) . '</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Formulario de WhatsApp -->
            <div class="border border-gray-200 rounded-lg p-4">
                <h2 class="text-lg font-semibold mb-4">WhatsApp</h2>
                
                <form method="POST" action="/api/profile.php?action=whatsapp">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Número de WhatsApp</label>
                        <input type="text" name="whatsapp_phone" 
                               value="' . htmlspecialchars($profile['whatsapp_phone'] ?? '') . '"
                               placeholder="5491122334455" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <p class="mt-1 text-xs text-slate-500">Formato: código de país + número (solo dígitos)</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nombre de instancia</label>
                        <input type="text" name="evolution_instance" 
                               value="' . htmlspecialchars($profile['evolution_instance'] ?? '') . '"
                               placeholder="empresa_1_operador_' . $user['id'] . '" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <p class="mt-1 text-xs text-slate-500">Ej: empresa_1_operador_1</p>
                    </div>
                    
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        Guardar datos
                    </button>
                </form>

                <div class="mt-4">
                    <a href="/whatsapp-connect.php" 
                       class="inline-block bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        Conectar WhatsApp
                    </a>
                    <p class="mt-2 text-sm text-gray-600">
                        Estado: 
                        <span class="font-medium ' . ($profile['is_whatsapp_connected'] ? 'text-green-600">Conectado' : 'text-red-600">Desconectado') . '</span>
                    </p>
                </div>
            </div>

            <!-- Formulario de contraseña -->
            <div class="border border-gray-200 rounded-lg p-4">
                <h2 class="text-lg font-semibold mb-4">Cambiar Contraseña</h2>
                
                <form method="POST" action="/api/profile.php?action=password">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Contraseña actual</label>
                        <input type="password" name="current_password" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nueva contraseña</label>
                        <input type="password" name="new_password" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Confirmar nueva contraseña</label>
                        <input type="password" name="confirm_password" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        Cambiar contraseña
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
';

Layout::render('Mi Perfil', $contenido, $user);
?>