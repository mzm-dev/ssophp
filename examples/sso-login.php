<?php

declare(strict_types=1);

// Butang "Log masuk dengan iLogin" memaut ke fail ini.

require __DIR__ . '/../autoload.php'; // atau vendor/autoload.php jika menggunakan Composer

use Mzm\SsoPhp\IloginClient;

session_start();

if (! empty($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

(new IloginClient(require __DIR__ . '/config.php'))->redirectToLogin();
