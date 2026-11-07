<?php

declare(strict_types=1);

/*
 * Untuk projek tanpa Composer: require __DIR__ . '/path/ke/ssophp/autoload.php';
 */
spl_autoload_register(static function (string $kelas): void {
    $awalan = 'Mzm\\SsoPhp\\';

    if (strncmp($kelas, $awalan, strlen($awalan)) !== 0) {
        return;
    }

    $fail = __DIR__ . '/src/' . str_replace('\\', '/', substr($kelas, strlen($awalan))) . '.php';

    if (is_file($fail)) {
        require $fail;
    }
});
