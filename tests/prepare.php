<?php

// Run only against a NEW, disposable test database.
if (PHP_SAPI !== 'cli') {
    exit;
}

$config = require dirname(__DIR__) . '/config.php';

if (strpos($config['database'], 'test_') !== 0) {
    exit("PRAKTIKUM_DB_NAME must begin with test_\n");
}

$db = new PDO(
    'mysql:host=' .
    $config['host'] .
    ';port=' .
    $config['port'] .
    ';charset=utf8mb4',
    $config['username'],
    $config['password'],
    array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    )
);

if (
    !preg_match(
        '/^test_[a-zA-Z0-9_]+$/',
        $config['database']
    )
) {
    exit('Invalid test name');
}

$db->exec(
    'CREATE DATABASE `' .
    $config['database'] .
    '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
);

$db->exec(
    'USE `' .
    $config['database'] .
    '`'
);

$sql = file_get_contents(
    dirname(__DIR__) .
    '/database/schema.sql'
);

list(
    $base,
    $triggers
) = explode(
    'DELIMITER $$',
    $sql
);

foreach (
    explode(';', $base) as $statement
) {
    if (trim($statement)) {
        $db->exec($statement);
    }
}

foreach (
    explode(
        '$$',
        str_replace(
            'DELIMITER ;',
            '',
            $triggers
        )
    ) as $statement
) {
    if (trim($statement)) {
        $db->exec($statement);
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/install.php';

install_admin(
    'Pengelola Laboratorium',
    'admin@fikom.umi.ac.id',
    'Test-password-2026',
    true
);

echo
    'Ready: ' .
    $config['database'] .
    ' PHP ' .
    PHP_VERSION .
    "\n";
