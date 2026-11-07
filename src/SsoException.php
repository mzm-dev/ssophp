<?php

declare(strict_types=1);

namespace Mzm\SsoPhp;

/**
 * Ralat log masuk SSO. Gunakan getCode() untuk membezakan jenis ralat.
 */
class SsoException extends \RuntimeException
{
    /** State hilang atau tidak sepadan (session tamat, atau callback bukan dari pelayar ini). Mulakan semula log masuk. */
    public const STATE_MISMATCH = 1;

    /** client_id / client_secret salah. Semak konfigurasi. */
    public const INVALID_CLIENT = 2;

    /** Kod tidak sah, telah digunakan atau tamat tempoh. Mulakan semula log masuk. */
    public const INVALID_GRANT = 3;

    /** Pengguna tiada akses ke sistem ini di iLogin. Paparkan getMessage(). */
    public const ACCESS_DENIED = 4;

    /** Tidak dapat menghubungi iLogin (rangkaian / TLS). */
    public const CONNECTION = 5;

    /** Respons iLogin tidak dijangka. */
    public const INVALID_RESPONSE = 6;

    /** Sama ada pengguna patut dihantar semula ke iLogin untuk mencuba lagi. */
    public function bolehCubaSemula(): bool
    {
        return in_array($this->getCode(), [self::STATE_MISMATCH, self::INVALID_GRANT], true);
    }
}
