<?php
// Kept free of modern PHP syntax on purpose: a server with an old PHP version should show a
// readable message here instead of a blank error page.
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Rider Page needs PHP 8.1 or newer. This server runs PHP ' . PHP_VERSION . ".\n"
        . "Choose a newer PHP version in your hosting control panel and reload this page.\n";
    exit;
}

define('RP_ROOT', dirname(__FILE__));
require RP_ROOT . '/app/bootstrap.php';
rp_run();
