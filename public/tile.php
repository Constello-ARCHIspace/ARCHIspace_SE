<?php
/**
 * Cached OpenStreetMap tile proxy for the studio lot map.
 *
 * The browser only talks to this server; PHP fetches each tile once from the
 * OpenStreetMap tile server and keeps it in storage/tiles for 7 days, as the
 * OSM tile usage policy asks (https://operations.osmfoundation.org/policies/tiles/).
 * The map must show "© OpenStreetMap contributors"; index.php does.
 *
 * Optional environment variables:
 *   ARCHISPACE_TILE_DIR  cache folder (default: storage/tiles)
 *   ARCHISPACE_TILE_URL  tile server template with {z} {x} {y} (default: OSM)
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$user = requireUser(true);
session_write_close(); // tiles load in parallel; do not hold the session lock

function tileFail(int $code, string $message): never
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $message;
    exit;
}

$range = static fn(int $min, int $max): array => ['options' => ['min_range' => $min, 'max_range' => $max]];
$z = filter_input(INPUT_GET, 'z', FILTER_VALIDATE_INT, $range(3, 19));
if (!is_int($z)) tileFail(400, 'Invalid zoom');
$x = filter_input(INPUT_GET, 'x', FILTER_VALIDATE_INT, $range(0, (1 << $z) - 1));
$y = filter_input(INPUT_GET, 'y', FILTER_VALIDATE_INT, $range(0, (1 << $z) - 1));
if (!is_int($x) || !is_int($y)) tileFail(400, 'Invalid tile');

$root = getenv('ARCHISPACE_TILE_DIR') ?: dirname(__DIR__) . '/storage/tiles';
$path = "$root/$z/$x/$y.png";
$maxAge = 7 * 86400;
$png = null;

if (!is_file($path) || time() - filemtime($path) >= $maxAge) {
    $limited = false;
    try { $auth->limit('tiles:user:' . $user['id'], 3000, 3600); } catch (Throwable) { $limited = true; }
    if ($limited) {
        if (!is_file($path)) tileFail(429, 'Too many map requests. Try again later.');
    } else {
        $template = getenv('ARCHISPACE_TILE_URL') ?: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
        $url = str_replace(['{z}', '{x}', '{y}'], [(string)$z, (string)$x, (string)$y], $template);
        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'user_agent' => 'Archispace/0.2 (concept building planner; ' . $base . ')',
                'header' => "Accept: image/png\r\n",
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $context, 0, 2_000_000);
        $headers = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
        $status = 0;
        foreach ($headers as $h) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $status = (int)$m[1];
        if ($status === 200 && is_string($body) && str_starts_with($body, "\x89PNG\r\n\x1a\n")) {
            $png = $body;
            if (is_dir(dirname($path)) || @mkdir(dirname($path), 0775, true)) {
                $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
                if (@file_put_contents($tmp, $png) !== false) @rename($tmp, $path);
            }
        } elseif (!is_file($path)) {
            tileFail(502, 'Map tile unavailable');
        }
        // Otherwise the upstream failed: serve the older cached tile below.
    }
}

header('Content-Type: image/png');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
if ($png !== null) echo $png;
else readfile($path);
