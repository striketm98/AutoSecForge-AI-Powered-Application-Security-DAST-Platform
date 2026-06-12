<?php
function is_safe_url($url) {
    $blocked = ['127.0.0.1', 'localhost', '169.254.169.254', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'];
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return false;
    foreach ($blocked as $b) if (strpos($host, $b) !== false) return false;
    return true;
}
?>
