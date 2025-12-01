<?php
// app/core/Layout.php

class Layout {
    public static function render($title, $content, $user = null) {
        $company_name = $user['company_name'] ?? 'CarteraWA';
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= htmlspecialchars($title) ?> • <?= htmlspecialchars($company_name) ?></title>
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = {
                    theme: {
                        extend: {
                            colors: {
                                primary: '#2563eb', // blue-600
                                surface: '#f8fafc', // slate-50
                                border: '#e2e8f0',  // slate-200
                            }
                        }
                    }
                }
            </script>
            <style>
                .scrollbar-hide::-webkit-scrollbar { display: none; }
                .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
            </style>
        </head>
        <body class="bg-surface text-slate-800">
            <div class="flex h-screen overflow-hidden">
                <!-- Sidebar -->
                <aside class="w-64 bg-white border-r border-border hidden md:block">
                    <div class="p-4 border-b border-border">
                        <h2 class="text-lg font-bold text-primary">CarteraWA</h2>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars($company_name) ?></p>
                    </div>
                    <nav class="p-2">
                        <a href="/conversations.php" 
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 <?= strpos($_SERVER['REQUEST_URI'], 'conversations') !== false ? 'bg-blue-50 text-primary font-medium' : 'text-slate-700' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zM7 8H5v2h2V8zm2 0h2v2H9V8zm6 0h-2v2h2V8z" clip-rule="evenodd" />
                            </svg>
                            Conversaciones
                        </a>
                        <?php if ($user['role'] === 'admin'): ?>
                            <a href="/templates.php"
                               class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 <?= strpos($_SERVER['REQUEST_URI'], 'templates') !== false ? 'bg-blue-50 text-primary font-medium' : 'text-slate-700' ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4a2 2 0 012 2v2h2a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V8a2 2 0 012-2h2V4z" clip-rule="evenodd" />
                                </svg>
                                Plantillas
                            </a>
                            <a href="/prospects-list.php"
                               class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 <?= strpos($_SERVER['REQUEST_URI'], 'prospects-list') !== false ? 'bg-blue-50 text-primary font-medium' : 'text-slate-700' ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                                </svg>
                                Prospectos
                            </a>
                            <a href="/analytics.php"
                               class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 <?= strpos($_SERVER['REQUEST_URI'], 'analytics') !== false ? 'bg-blue-50 text-primary font-medium' : 'text-slate-700' ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                                </svg>
                                Métricas
                            </a>
                        <?php endif; ?>
                        <a href="/profile.php"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 <?= strpos($_SERVER['REQUEST_URI'], 'profile') !== false ? 'bg-blue-50 text-primary font-medium' : 'text-slate-700' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                            </svg>
                            Mi Perfil
                        </a>
                    </nav>
                </aside>

                <!-- Main content -->
                <div class="flex flex-col flex-1 overflow-hidden">
                    <!-- Header -->
                    <header class="bg-white border-b border-border p-3 flex items-center justify-between">
                        <div class="flex items-center gap-3 md:hidden">
                            <!-- Botón para sidebar móvil (opcional) -->
                        </div>
                        <div class="text-sm font-medium">
                            Bienvenido,
                            <a href="/profile.php" class="text-primary hover:underline">
                                <?= htmlspecialchars($user['email'] ?? '') ?>
                            </a>
                        </div>
                        <a href="/logout.php" class="text-slate-500 hover:text-slate-700 flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd" />
                            </svg>
                            Salir
                        </a>
                    </header>

                    <!-- Content -->
                    <main class="flex-1 overflow-y-auto p-3 md:p-4">
                        <?= $content ?>
                    </main>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}