<?php
// ─── Secure file download (admin only) ───────────────────────────────────────
session_start();

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    exit('Accès non autorisé.');
}

require __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Paramètre invalide.');
}

// Fetch record from DB
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$stmt = $pdo->prepare('SELECT fichier_nom, fichier FROM inscriptions WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || !$row['fichier']) {
    http_response_code(404);
    exit('Document introuvable.');
}

// Build and verify path — ensure file stays within UPLOAD_DIR
$filePath = realpath(UPLOAD_DIR . $row['fichier']);
$uploadDir = realpath(UPLOAD_DIR);

if ($filePath === false || strpos($filePath, $uploadDir) !== 0 || !is_file($filePath)) {
    http_response_code(404);
    exit('Fichier introuvable sur le serveur.');
}

// Detect MIME
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($filePath);

// Only serve allowed types
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(403);
    exit('Type de fichier non autorisé.');
}

// Serve
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . rawurlencode(basename($row['fichier_nom'])) . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;
