<?php

declare(strict_types=1);

// URL fail ini ialah "callback" sistem anda dalam iLogin, cth. https://sistem-anda.example/sso-callback.php

require __DIR__ . '/../autoload.php'; // atau vendor/autoload.php jika menggunakan Composer

use Mzm\SsoPhp\IloginClient;
use Mzm\SsoPhp\SsoException;

session_start();

$sso = new IloginClient(require __DIR__ . '/config.php');

try {
    $pengguna = $sso->handleCallback();
} catch (SsoException $e) {
    // Session tamat atau kod sudah digunakan: cuba sekali lagi dari awal.
    if ($e->bolehCubaSemula() && empty($_SESSION['sso_cuba_semula'])) {
        $_SESSION['sso_cuba_semula'] = true;
        $sso->redirectToLogin();
    }

    unset($_SESSION['sso_cuba_semula']);
    http_response_code($e->getCode() === SsoException::ACCESS_DENIED ? 403 : 500);
    echo htmlspecialchars($e->getMessage());
    exit;
}

unset($_SESSION['sso_cuba_semula']);

// Cari pengguna dalam pangkalan data sistem anda. Contoh menggunakan PDO:
$pdo = new PDO('mysql:host=localhost;dbname=sistem;charset=utf8mb4', 'user', 'pass', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND aktif = 1');
$stmt->execute([$pengguna['email']]);
$id = $stmt->fetchColumn();

if (! $id) {
    http_response_code(404);
    echo 'Akaun anda belum didaftarkan dalam sistem ini. Sila hubungi pentadbir.';
    exit;
}

// Elak session fixation, kemudian log masuk.
session_regenerate_id(true);
$_SESSION['user_id'] = (int) $id;
$_SESSION['user_name'] = $pengguna['name'];

header('Location: /');
exit;
