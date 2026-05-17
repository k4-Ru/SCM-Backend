<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$target = __DIR__ . $path;

if ($path !== '/' && is_file($target)) {
  return false;
}

require dirname(__DIR__) . '/main.php';
