<?php
session_start();
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/core/Auth.php';
Auth::init($pdo);

// Si ya está logueado, redirigir al dashboard
if (Auth::check()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
if ($_POST) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (Auth::login($email, $password)) {
        header('Location: /dashboard.php');
        exit;
    } else {
        $error = "Email o contraseña incorrectos.";
    }
}

// Verificar que la empresa exista (por subdominio)
$company_id = Auth::getCompanyIdFromRequest();
if (!$company_id) {
    die("Empresa no encontrada. Usa tu subdominio.");
}

// Obtener nombre de la empresa para mostrar
$stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - <?= htmlspecialchars($company['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: sans-serif; background: #f5f7fa; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 90%; max-width: 400px; }
        input { width: 100%; padding: 0.75rem; margin: 0.5rem 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 0.75rem; background: #2563eb; color: white; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .error { color: #e11d48; margin: 0.5rem 0; }
        h2 { margin-top: 0; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Iniciar sesión</h2>
        <p><?= htmlspecialchars($company['name']) ?></p>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>