<?php
session_start();
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/core/Layout.php';
Auth::init($pdo);
Auth::requireLogin();
$user = Auth::user();

$stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
$stmt->execute([$user['company_id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
$user['company_name'] = $company['name'] ?? 'Empresa';

$contenido = '
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h1 class="text-2xl font-bold text-slate-800 mb-2">Bienvenido a CarteraWA</h1>
        <p class="text-slate-600">Gestiona tus conversaciones de cartera desde un solo lugar.</p>
        
        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-4">
            <a href="/conversations.php" class="block p-4 border border-border rounded-lg hover:bg-slate-50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zM7 8H5v2h2V8zm2 0h2v2H9V8zm6 0h-2v2h2V8z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-medium">Conversaciones</h3>
                        <p class="text-sm text-slate-500">Responde y gestiona mensajes</p>
                    </div>
                </div>
            </a>
            
            ' . ($user['role'] === 'admin' ? '
            <a href="/templates.php" class="block p-4 border border-border rounded-lg hover:bg-slate-50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4a2 2 0 012 2v2h2a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V8a2 2 0 012-2h2V4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-medium">Plantillas</h3>
                        <p class="text-sm text-slate-500">Gestiona respuestas predefinidas</p>
                    </div>
                </div>
            </a>
            ' : '') . '
        </div>
    </div>
</div>
';

Layout::render('Inicio', $contenido, $user);
?>