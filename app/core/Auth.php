<?php
// app/core/Auth.php

class Auth {
    private static $pdo;

    public static function init($pdo_instance) {
        self::$pdo = $pdo_instance;
    }

    // Obtiene el company_id desde el subdominio
	public static function getCompanyIdFromRequest() {
		// Si es una API llamada desde localhost (webhook), no usamos subdominio
		if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0 && $_SERVER['REMOTE_ADDR'] === '127.0.0.1') {
			return null; // No se necesita company_id aquí; se obtiene por session_id
		}

        // Opción 1: parámetro ?empresa= en la URL
        if (isset($_GET['empresa'])) {
            $subdomain = $_GET['empresa'];
        }
        // Opción 2: subdominio (para cuando puedas usarlo en producción)
        else {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $parts = explode('.', $host);
            if (count($parts) > 2 || (count($parts) === 2 && $parts[0] !== 'www')) {
                $subdomain = $parts[0];
            } else {
                return null;
            }
        }

		$stmt = self::$pdo->prepare("SELECT id FROM companies WHERE subdomain = ?");
		$stmt->execute([$subdomain]);
		$company = $stmt->fetch(PDO::FETCH_ASSOC);
		return $company ? (int)$company['id'] : null;
	}

    // Iniciar sesión
    public static function login($email, $password) {
        $company_id = self::getCompanyIdFromRequest();
        if (!$company_id) return false;

        $stmt = self::$pdo->prepare("SELECT id, company_id, email, password, role FROM users 
                                     WHERE email = ? AND company_id = ?");
        $stmt->execute([$email, $company_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['company_id'] = $user['company_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            return true;
        }
        return false;
    }

    // Verifica si hay una sesión activa
    public static function check() {
        return isset($_SESSION['user_id']) && isset($_SESSION['company_id']);
    }

    // Redirige si no está logueado
    public static function requireLogin() {
        if (!self::check()) {
            header('Location: /login.php');
            exit;
        }
    }

    // Obtener datos del usuario actual
    public static function user() {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'company_id' => $_SESSION['company_id'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'email' => $_SESSION['email'] ?? null
        ];
    }

    // Cerrar sesión
    public static function logout() {
        session_destroy();
    }
}