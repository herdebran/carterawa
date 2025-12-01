<?php
session_start();
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/core/Auth.php';
Auth::init($pdo);

if (!Auth::check() || Auth::user()['role'] !== 'admin') {
    http_response_code(403);
    exit;
}

$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    $_SESSION['error'] = 'Archivo no recibido';
    header('Location: /prospects.php?error=' . urlencode('Archivo no recibido'));
    exit;
}

$file = $_FILES['file'];

// Validar tipo de archivo
if ($file['type'] !== 'text/csv' && $file['name'] !== preg_match('/\.csv$/i', $file['name'])) {
    $_SESSION['error'] = 'Solo se permiten archivos CSV';
    header('Location: /prospects.php?error=' . urlencode('Formato inválido. Solo CSV.'));
    exit;
}

// Abrir archivo
$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    $_SESSION['error'] = 'No se pudo leer el archivo';
    header('Location: /prospects.php?error=' . urlencode('Error al leer el archivo.'));
    exit;
}


$first_line = fgets($handle);
rewind($handle);
$delimiter = strpos($first_line, ';') !== false ? ';' : ',';

// Leer encabezados
$headers = fgetcsv($handle, 0, $delimiter);
if (!$headers || !in_array('TELEFONO', array_map('strtoupper', $headers))) {
    fclose($handle);
    $_SESSION['error'] = 'El archivo debe tener columna TELEFONO';
    header('Location: /prospects.php?error=' . urlencode('Falta la columna TELEFONO.'));
    exit;
}

// Mapear columnas (case-insensitive)
$col_map = [];
$required = ['APELLIDO', 'NOMBRE', 'TELEFONO'];
foreach ($required as $col) {
    $index = array_search(strtolower($col), array_map('strtolower', $headers));
    if ($index === false) {
        fclose($handle);
        $_SESSION['error'] = "Falta columna: $col";
        header('Location: /prospects.php?error=' . urlencode("Falta columna: $col"));
        exit;
    }
    $col_map[$col] = $index;
}

// Opcionales
$col_map['DNI'] = array_search('dni', array_map('strtolower', $headers));
$col_map['REPARTICION'] = array_search('reparticion', array_map('strtolower', $headers));

// Preparar inserción
$stmt = $pdo->prepare("
    INSERT IGNORE INTO prospects (company_id, lastname, name, phone, dni, reparticion)
    VALUES (?, ?, ?, ?, ?, ?)
");

$imported = 0;
while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
    // Saltar fila vacía
    if (empty(array_filter($row))) continue;

    // Normalizar teléfono: solo dígitos, asegurar formato internacional
    $phone_raw = $row[$col_map['TELEFONO']] ?? '';
    $phone = preg_replace('/[^0-9]/', '', $phone_raw);

    // Validación mínima: al menos 10 dígitos
    if (strlen($phone) < 10) continue;

    // Asegurar prefijo internacional (ej: 54 para Argentina)
    // Ajusta según tu país o hazlo configurable
    if (substr($phone, 0, 2) !== '54' && strlen($phone) === 10) {
        $phone = '54' . $phone; // asumimos Argentina
    }

    $lastname = trim($row[$col_map['APELLIDO']] ?? '');
    $name = trim($row[$col_map['NOMBRE']] ?? '');
    $dni = isset($col_map['DNI']) && $col_map['DNI'] !== false ? trim($row[$col_map['DNI']]) : null;
    $reparticion = isset($col_map['REPARTICION']) && $col_map['REPARTICION'] !== false ? trim($row[$col_map['REPARTICION']]) : null;

    if (!$lastname || !$name) continue;

    try {
        $result = $stmt->execute([
            $user['company_id'],
            $lastname,
            $name,
            $phone,
            $dni ?: null,
            $reparticion ?: null
        ]);
        if ($result && $stmt->rowCount() > 0) {
            $imported++;
        }
    } catch (Exception $e) {
        error_log("Error importando prospecto: " . $e->getMessage());
    }
}

fclose($handle);

$_SESSION['import_stats'] = ['imported' => $imported];
header('Location: /prospects.php?success=1');
exit;
?>