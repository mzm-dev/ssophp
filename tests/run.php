<?php

/*
 * Ujian ringkas tanpa PHPUnit: php tests/run.php
 */

require __DIR__ . '/../autoload.php';

use Mzm\SsoPhp\IloginClient;
use Mzm\SsoPhp\SsoException;

$gagal = 0;
$lulus = 0;

function semak(string $nama, callable $ujian): void
{
    global $gagal, $lulus;
    $_SESSION = [];

    try {
        $ujian();
        $lulus++;
        echo "  ✓ {$nama}\n";
    } catch (Throwable $e) {
        $gagal++;
        echo "  ✗ {$nama}: " . get_class($e) . ': ' . $e->getMessage() . "\n";
    }
}

function pastikan(bool $syarat, string $mesej = 'Syarat tidak dipenuhi'): void
{
    if (! $syarat) {
        throw new RuntimeException($mesej);
    }
}

/** Klien dengan transport palsu yang merekod permintaan. */
function klien(int $status, array $body, ?array &$rekod = null): IloginClient
{
    return new IloginClient(
        ['base_url' => 'https://ilogin.test/', 'client_id' => 'id-1', 'client_secret' => 'rahsia-1'],
        function ($url, $form, $pilihan) use ($status, $body, &$rekod) {
            $rekod = compact('url', 'form', 'pilihan');

            return ['status' => $status, 'body' => json_encode($body)];
        }
    );
}

function stateDari(string $url): string
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

    return $q['state'];
}

function jangkaRalat(callable $fn, int $kod): void
{
    try {
        $fn();
    } catch (SsoException $e) {
        pastikan($e->getCode() === $kod, "Kod ralat {$e->getCode()}, dijangka {$kod}");

        return;
    }
    throw new RuntimeException('SsoException tidak dilemparkan');
}

// Session dalam CLI: guna tatasusunan biasa.
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

echo "mzm/ssophp\n";

semak('loginUrl membawa client_id dan state rawak', function () {
    $url = klien(200, [])->loginUrl();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

    pastikan(strpos($url, 'https://ilogin.test/login?') === 0, $url);
    pastikan($q['token'] === 'id-1');
    pastikan(strlen($q['state']) === 40);
    pastikan($_SESSION['ilogin_sso_state'] === $q['state']);
});

semak('callback sah memulangkan data pengguna dan menghantar kelayakan', function () {
    $rekod = null;
    $sso = klien(200, ['user' => ['email' => 'a@example.test']], $rekod);
    $state = stateDari($sso->loginUrl());

    $user = $sso->handleCallback(['code' => 'kod-1', 'state' => $state]);

    pastikan($user['email'] === 'a@example.test');
    pastikan($rekod['url'] === 'https://ilogin.test/api/sso/token');
    pastikan($rekod['form'] === ['client_id' => 'id-1', 'client_secret' => 'rahsia-1', 'code' => 'kod-1']);
    pastikan($rekod['pilihan']['verify_tls'] === true);
    pastikan(! isset($_SESSION['ilogin_sso_state']), 'State mesti dibuang selepas digunakan');
});

semak('state salah ditolak tanpa memanggil iLogin', function () {
    $rekod = null;
    $sso = klien(200, ['user' => []], $rekod);
    $sso->loginUrl();

    jangkaRalat(function () use ($sso) {
        $sso->handleCallback(['code' => 'kod-1', 'state' => 'lain']);
    }, SsoException::STATE_MISMATCH);
    pastikan($rekod === null);
});

semak('state hanya boleh digunakan sekali', function () {
    $sso = klien(200, ['user' => ['email' => 'a']]);
    $state = stateDari($sso->loginUrl());
    $sso->handleCallback(['code' => 'k', 'state' => $state]);

    jangkaRalat(function () use ($sso, $state) {
        $sso->handleCallback(['code' => 'k', 'state' => $state]);
    }, SsoException::STATE_MISMATCH);
});

semak('callback tanpa session state ditolak', function () {
    jangkaRalat(function () {
        klien(200, ['user' => []])->handleCallback(['code' => 'k', 'state' => 'x']);
    }, SsoException::STATE_MISMATCH);
});

foreach ([
    ['invalid_grant', 400, SsoException::INVALID_GRANT, true],
    ['invalid_client', 401, SsoException::INVALID_CLIENT, false],
    ['access_denied', 403, SsoException::ACCESS_DENIED, false],
] as [$ralat, $status, $kod, $cubaSemula]) {
    semak("ralat {$ralat} dipetakan", function () use ($ralat, $status, $kod, $cubaSemula) {
        $sso = klien($status, ['error' => $ralat, 'error_description' => 'Mesej iLogin']);
        $state = stateDari($sso->loginUrl());

        try {
            $sso->handleCallback(['code' => 'k', 'state' => $state]);
        } catch (SsoException $e) {
            pastikan($e->getCode() === $kod);
            pastikan($e->bolehCubaSemula() === $cubaSemula);

            return;
        }
        throw new RuntimeException('Tiada ralat');
    });
}

semak('respons tidak dijangka', function () {
    $sso = klien(500, ['x' => 1]);
    $state = stateDari($sso->loginUrl());

    jangkaRalat(function () use ($sso, $state) {
        $sso->handleCallback(['code' => 'k', 'state' => $state]);
    }, SsoException::INVALID_RESPONSE);
});

semak('konfigurasi tanpa client_secret ditolak', function () {
    try {
        new IloginClient(['base_url' => 'https://ilogin.test', 'client_id' => 'x']);
    } catch (InvalidArgumentException $e) {
        return;
    }
    throw new RuntimeException('Tiada ralat');
});

semak('konfigurasi tanpa base_url ditolak', function () {
    try {
        new IloginClient(['client_id' => 'x', 'client_secret' => 'y']);
    } catch (InvalidArgumentException $e) {
        return;
    }
    throw new RuntimeException('Tiada ralat');
});

echo "\n{$lulus} lulus, {$gagal} gagal\n";
exit($gagal > 0 ? 1 : 0);
