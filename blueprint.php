<?php
declare(strict_types=1);
session_start();
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
// Configure a persistent private directory OUTSIDE the document root in production.
$root = getenv('ARCHISPACE_BLUEPRINT_DIR') ?: sys_get_temp_dir() . '/archispace-blueprints';
$key = $_SESSION['blueprint_key'] ?? ($_SESSION['blueprint_key'] = bin2hex(random_bytes(24)));
$dir = $root . '/' . $key;
function failUpload(int $status, string $message): never { http_response_code($status); header('Content-Type: application/json'); echo json_encode(['error'=>$message]); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = $_GET['id'] ?? '';
    if (!is_string($id) || !preg_match('/^[a-f0-9]{48}$/D', $id)) failUpload(400, 'Invalid image ID');
    $path = $dir . '/' . $id . '.png';
    if (!is_file($path)) failUpload(404, 'Blueprint unavailable in this session');
    header('Content-Type: image/png'); header('Content-Length: ' . filesize($path)); readfile($path); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') failUpload(405, 'Use POST');
if (!isset($_SESSION['csrf']) || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) failUpload(403, 'Reload the project and try again');
$f = $_FILES['file'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) failUpload(400, 'Upload failed; check PHP upload limits');
if ($f['size'] > 12 * 1024 * 1024) failUpload(413, 'Image exceeds 12 MB');
$info = @getimagesize($f['tmp_name']);
if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] > 8000 || $info[1] > 8000) failUpload(415, 'Only raster PNG blueprints are accepted');
if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) failUpload(500, 'Private image storage is unavailable');
$size=0; foreach (glob($dir . '/*.png') ?: [] as $file) $size += filesize($file);
if ($size + $f['size'] > 100 * 1024 * 1024) failUpload(413, 'Session storage quota reached (100 MB)');
$id = bin2hex(random_bytes(24));
if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $id . '.png')) failUpload(500, 'Could not save image');
chmod($dir . '/' . $id . '.png', 0600);
header('Content-Type: application/json'); echo json_encode(['url'=>'blueprint.php?id=' . $id]);
