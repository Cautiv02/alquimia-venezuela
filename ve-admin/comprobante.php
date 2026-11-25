<?php
/** ve-admin/comprobante.php?id=N — muestra el archivo de un comprobante (solo con sesión de admin). */
require_once __DIR__ . '/_auth.php';
$st = ve_pdo()->prepare('SELECT file_name FROM ve_proofs WHERE id = :id'); $st->execute([':id' => (int) ($_GET['id'] ?? 0)]);
$f = (string) $st->fetchColumn();
$path = ve_proofs_dir() . '/' . basename($f);
if ($f === '' || !is_file($path)) { http_response_code(404); echo 'Archivo no encontrado.'; exit; }
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0');
readfile($path);
