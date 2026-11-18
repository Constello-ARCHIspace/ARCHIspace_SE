<?php
/**
 * Address search for the studio lot map: turns a typed address or place name into map positions.
 *
 * The browser only talks to this server. PHP asks OpenStreetMap Nominatim and follows its usage policy
 * (https://operations.osmfoundation.org/policies/nominatim/): searches run only when the user presses
 * Search (no search-as-you-type), at most one request per second from this server, an identifying
 * User-Agent, and answers are cached for 30 days. Results are © OpenStreetMap contributors; the studio
 * shows that credit under the results.
 *
 * Optional environment variables:
 *   ARCHISPACE_GEOCODE_URL        search endpoint (default: https://nominatim.openstreetmap.org/search)
 *   ARCHISPACE_GEOCODE_COUNTRIES  comma-separated ISO country codes to search in (default: ph; "" = worldwide)
 *   ARCHISPACE_GEOCODE_EMAIL      contact email sent to Nominatim (recommended for production)
 *   ARCHISPACE_GEOCODE_DIR        cache folder (default: storage/geocode)
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$user = requireUser(true);
session_write_close();

function geoReply(int $code, array $body): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$query = $_GET['q'] ?? '';
if (!is_string($query)) geoReply(400, ['error' => 'Type an address or place name.']);
$query = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
if (mb_strlen($query) < 3 || mb_strlen($query) > 200) geoReply(400, ['error' => 'Type 3 to 200 characters.']);

// Optional bias toward the area the map is showing: lat,lng of the map centre.
$near = null;
if (isset($_GET['near']) && is_string($_GET['near']) && preg_match('/^(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)$/D', $_GET['near'], $m)) {
    $la = (float)$m[1]; $lo = (float)$m[2];
    if (abs($la) <= 85 && abs($lo) <= 180) $near = [$la, $lo];
}

$countries = getenv('ARCHISPACE_GEOCODE_COUNTRIES');
$countries = $countries === false ? 'ph' : strtolower(preg_replace('/[^a-zA-Z,]/', '', $countries) ?? '');
$root = getenv('ARCHISPACE_GEOCODE_DIR') ?: dirname(__DIR__) . '/storage/geocode';
// The map-centre bias is rounded to about 10 km so nearby searches share the cache.
$nearKey = $near ? sprintf('%.1f,%.1f', $near[0], $near[1]) : '';
$cache = $root . '/' . hash('sha256', mb_strtolower($query) . '|' . $countries . '|' . $nearKey) . '.json';
$maxAge = 30 * 86400;

if (is_file($cache) && time() - filemtime($cache) < $maxAge) {
    $hit = json_decode((string)file_get_contents($cache), true);
    if (is_array($hit)) geoReply(200, ['results' => $hit, 'cached' => true]);
}

try { $auth->limit('geocode:user:' . $user['id'], 60, 3600); }
catch (Throwable) { geoReply(429, ['error' => 'Too many searches. Try again later, or click the map to place the pin.']); }

if (!is_dir($root) && !@mkdir($root, 0775, true)) geoReply(503, ['error' => 'Address search is not available on this server.']);

// One request per second for the whole server: wait for the previous search to be at least a second old.
$lock = @fopen($root . '/.throttle', 'c+');
if ($lock === false || !flock($lock, LOCK_EX)) geoReply(503, ['error' => 'Address search is busy. Try again.']);
$last = (float)stream_get_contents($lock);
$wait = $last + 1.05 - microtime(true);
if ($wait > 0) usleep((int)min(1_100_000, $wait * 1_000_000));

$params = ['q' => $query, 'format' => 'jsonv2', 'limit' => 5, 'addressdetails' => 0, 'accept-language' => 'en'];
if ($countries !== '') $params['countrycodes'] = $countries;
if ($near) { $d = 0.5; $params['viewbox'] = implode(',', [$near[1] - $d, $near[0] + $d, $near[1] + $d, $near[0] - $d]); }
$email = getenv('ARCHISPACE_GEOCODE_EMAIL');
if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) $params['email'] = $email;
$url = (getenv('ARCHISPACE_GEOCODE_URL') ?: 'https://nominatim.openstreetmap.org/search') . '?' . http_build_query($params);
$context = stream_context_create([
    'http' => [
        'timeout' => 8,
        'user_agent' => 'Archispace/0.2 (concept building planner; ' . $base . ')',
        'header' => "Accept: application/json\r\n",
        'ignore_errors' => true,
        'follow_location' => 0,
    ],
    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
]);
$body = @file_get_contents($url, false, $context, 0, 500_000);
ftruncate($lock, 0); rewind($lock); fwrite($lock, (string)microtime(true)); fflush($lock);
flock($lock, LOCK_UN); fclose($lock);

$headers = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
$status = 0;
foreach ($headers as $h) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $status = (int)$m[1];
$rows = $status === 200 && is_string($body) ? json_decode($body, true) : null;
if (!is_array($rows) || !array_is_list($rows)) {
    error_log('ARCHIspace geocode failed: HTTP ' . $status);
    geoReply(502, ['error' => 'Address search is not reachable right now. Click the map or type the latitude and longitude instead.']);
}

// Keep only what the studio needs, and only valid positions.
$results = [];
foreach ($rows as $r) {
    if (!is_array($r) || !is_numeric($r['lat'] ?? null) || !is_numeric($r['lon'] ?? null) || !is_string($r['display_name'] ?? null)) continue;
    $la = (float)$r['lat']; $lo = (float)$r['lon'];
    if (abs($la) > 85 || abs($lo) > 180) continue;
    $item = ['name' => mb_substr($r['display_name'], 0, 300), 'lat' => round($la, 6), 'lng' => round($lo, 6), 'type' => is_string($r['type'] ?? null) ? mb_substr($r['type'], 0, 40) : ''];
    // Bounding box (south, north, west, east) lets the studio zoom to fit a large place such as a barangay.
    $bb = $r['boundingbox'] ?? null;
    if (is_array($bb) && count($bb) === 4 && array_filter($bb, 'is_numeric') === $bb) $item['bbox'] = array_map(fn($v) => round((float)$v, 6), $bb);
    $results[] = $item;
    if (count($results) >= 5) break;
}
$tmp = $cache . '.' . bin2hex(random_bytes(4)) . '.tmp';
if (@file_put_contents($tmp, json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false) @rename($tmp, $cache);
geoReply(200, ['results' => $results]);
