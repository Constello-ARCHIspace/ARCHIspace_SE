<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
$user = requireUser();

// Keep studio shortcuts and submission bookmarks from before the studio had its own URL.
$studioParams = array_filter(array_intersect_key($_GET, ['tool' => true, 'submission' => true]), 'is_string');
if ($studioParams) {
    header('Location: studio.php?' . http_build_query($studioParams));
    exit;
}

header('Location: ' . accountHome($user));
exit;
