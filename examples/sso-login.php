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

$sso = new IloginClient(require __DIR__ . '/config.php');

if (! $sso->isEnabled()) {
    http_response_code(503);
    echo 'Log masuk melalui Sistem iLogin ditutup sementara untuk penyelenggaraan. Sila cuba lagi kemudian.';
    exit;
}

$sso->redirectToLogin();
