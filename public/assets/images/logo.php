<?php
// Serves logo.png if it exists, otherwise serves logo.svg
$dir = __DIR__;
if (file_exists($dir . '/logo.png')) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    readfile($dir . '/logo.png');
} elseif (file_exists($dir . '/logo.svg')) {
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=86400');
    readfile($dir . '/logo.svg');
} else {
    http_response_code(404);
}
