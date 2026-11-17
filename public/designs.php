<?php
declare(strict_types=1);
// "My designs" JSON API used by the studio. Only the signed-in owner can reach their designs.
//   GET  designs.php                          list designs
//   GET  designs.php?id=ID[&version=N]        open a design (latest, or a kept version)
//   GET  designs.php?id=ID&versions=1         list kept versions
//   POST action=save|rename|delete, csrf, ... save a new version, rename, delete
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Projects.php';
require dirname(__DIR__) . '/app/Designs.php';
$user = requireUser(true);
header('Content-Type: application/json');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
function reply(int $status, array $data): never { http_response_code($status); echo json_encode($data, JSON_THROW_ON_ERROR); exit; }
if (!$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='designs'")->fetchColumn()) {
    reply(503, ['error' => 'My designs is not set up yet. The administrator should run: php bin/migrate.php']);
}
$library = new DesignLibrary($db, new ProjectSubmission($db, getenv('ARCHISPACE_BLUEPRINT_DIR') ?: dirname(__DIR__) . '/storage/blueprints'));
$actor = (int)$user['id'];
$str = fn(array $src, string $key): string => is_string($src[$key] ?? null) ? $src[$key] : '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = $str($_GET, 'id');
        if ($id === '') reply(200, ['designs' => $library->list($actor), 'limit' => DesignLibrary::MAX_DESIGNS]);
        if (isset($_GET['versions'])) reply(200, ['versions' => $library->versions($actor, $id)]);
        $version = $str($_GET, 'version');
        reply(200, $library->get($actor, $id, $version === '' ? null : (ctype_digit($version) ? (int)$version : throw new InvalidArgumentException('Invalid version.'))));
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: GET, POST'); reply(405, ['error' => 'Use GET or POST.']); }
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) reply(403, ['error' => 'Your page expired. Reload the studio and save again; your design is still in this browser.']);
    $action = $str($_POST, 'action');
    if ($action === 'save') {
        $auth->limit('design-save:user:' . $actor, 120, 3600);
        $id = $str($_POST, 'id'); $base = $str($_POST, 'base_version');
        $saved = $library->save($actor, $id === '' ? null : $id, $str($_POST, 'name'), $str($_POST, 'kind'), $str($_POST, 'snapshot'),
            $base === '' ? null : (ctype_digit($base) ? (int)$base : throw new InvalidArgumentException('Invalid version.')));
        reply(200, $saved);
    }
    if ($action === 'rename') { $library->rename($actor, $str($_POST, 'id'), $str($_POST, 'name')); reply(200, ['ok' => true]); }
    if ($action === 'delete') { $library->delete($actor, $str($_POST, 'id')); reply(200, ['ok' => true]); }
    reply(400, ['error' => 'Unknown action.']);
} catch (StaleChange $e) { reply(409, ['error' => $e->getMessage(), 'stale' => true]); }
catch (AccessDenied $e) { reply(404, ['error' => $e->getMessage()]); }
catch (InvalidArgumentException | DomainException $e) { reply(422, ['error' => $e->getMessage()]); }
catch (Throwable $e) { error_log('ARCHIspace designs failed: ' . get_class($e)); reply(503, ['error' => 'Design storage is unavailable. Your design is still in this browser; try again shortly.']); }
