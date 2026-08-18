<?php

use Dotenv\Dotenv;
$basePath = dirname(__DIR__);

Dotenv::createImmutable($basePath)->safeLoad();

$clientKey = $_ENV['CLIENT_KEY']
    ?? $_SERVER['CLIENT_KEY']
    ?? null;

if (! is_string($clientKey) || trim($clientKey) === '') {
    return;
}

$clientKey = trim($clientKey);

if (! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $clientKey)) {
    throw new InvalidArgumentException(
        "Invalid Engage SEO CLIENT_KEY: {$clientKey}"
    );
}

$clientPath = $basePath.'/clients/'.$clientKey;

if (! is_dir($clientPath)) {
    throw new RuntimeException(
        "Selected Engage SEO client directory does not exist: {$clientKey}"
    );
}

Dotenv::createImmutable($clientPath)->safeLoad();