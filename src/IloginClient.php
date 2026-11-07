<?php

declare(strict_types=1);

namespace Mzm\SsoPhp;

use Closure;

/**
 * Klien SSO iLogin untuk aplikasi PHP native (tanpa framework).
 *
 * Aliran:
 *  1. redirectToLogin(): simpan state rawak dalam $_SESSION, hantar pengguna ke iLogin.
 *  2. iLogin kembali ke callback anda dengan ?code=&state= selepas log masuk dan 2FA.
 *  3. handleCallback(): sahkan state, tukar kod kepada data pengguna (server ke server).
 */
final readonly class IloginClient
{
    private string $baseUrl;

    private string $clientId;

    private string $clientSecret;

    private bool $verifyTls;

    private int $timeout;

    private string $sessionKey;

    /** @var (Closure(string, array<string, string>, array{verify_tls: bool, timeout: int}): array{status: int, body: string})|null */
    private ?Closure $transport;

    /**
     * @param array{
     *     base_url: string,
     *     client_id: string,
     *     client_secret: string,
     *     verify_tls?: bool,
     *     timeout?: int,
     *     session_key?: string
     * } $config
     * @param callable|null $transport Untuk ujian sahaja. Lalai: cURL.
     */
    public function __construct(array $config, ?callable $transport = null)
    {
        foreach (['base_url', 'client_id', 'client_secret'] as $kunci) {
            if (! is_string($config[$kunci] ?? null) || $config[$kunci] === '') {
                throw new \InvalidArgumentException("Konfigurasi '{$kunci}' diperlukan.");
            }
        }

        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->verifyTls = (bool) ($config['verify_tls'] ?? true);
        $this->timeout = (int) ($config['timeout'] ?? 15);
        $this->sessionKey = (string) ($config['session_key'] ?? 'ilogin_sso_state');
        $this->transport = $transport === null ? null : Closure::fromCallable($transport);
    }

    /** URL log masuk iLogin. State disimpan dalam $_SESSION. */
    public function loginUrl(): string
    {
        $this->pastikanSession();

        $state = bin2hex(random_bytes(20));
        $_SESSION[$this->sessionKey] = $state;

        return $this->baseUrl . '/login?' . http_build_query([
            'token' => $this->clientId,
            'state' => $state,
        ]);
    }

    /** Hantar pengguna ke iLogin dan hentikan skrip. */
    public function redirectToLogin(): never
    {
        $url = $this->loginUrl();

        header('Location: ' . $url, true, 302);
        exit;
    }

    /**
     * Sahkan callback dan pulangkan data pengguna iLogin:
     * name, email, nokp, jabatan_id, jabatan_nama, jawatan_id, jawatan_nama, gred, ...
     *
     * Selepas ini, cari pengguna dalam pangkalan data anda, panggil session_regenerate_id(true)
     * dan simpan id pengguna dalam $_SESSION.
     *
     * @param array<string, mixed>|null $query Lalai: $_GET
     * @return array<string, mixed>
     *
     * @throws SsoException
     */
    public function handleCallback(?array $query = null): array
    {
        $query ??= $_GET;
        $this->pastikanSession();

        $state = $_SESSION[$this->sessionKey] ?? null;
        unset($_SESSION[$this->sessionKey]);

        $stateDiterima = $query['state'] ?? null;
        if (! is_string($state) || ! is_string($stateDiterima) || ! hash_equals($state, $stateDiterima)) {
            throw new SsoException('State tidak sepadan. Sila log masuk semula.', SsoException::STATE_MISMATCH);
        }

        $kod = $query['code'] ?? null;
        if (! is_string($kod) || $kod === '') {
            throw new SsoException('Kod tiada dalam callback.', SsoException::INVALID_GRANT);
        }

        ['status' => $status, 'body' => $body] = $this->post($this->baseUrl . '/api/sso/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $kod,
        ]);

        $data = json_decode($body, true);
        $data = is_array($data) ? $data : [];

        if ($status === 200 && is_array($data['user'] ?? null)) {
            return $data['user'];
        }

        $mesej = is_string($data['error_description'] ?? null) ? $data['error_description'] : '';

        throw match ($data['error'] ?? null) {
            'invalid_client' => new SsoException('client_id atau client_secret ditolak oleh iLogin.', SsoException::INVALID_CLIENT),
            'invalid_grant' => new SsoException($mesej ?: 'Kod tidak sah atau tamat tempoh.', SsoException::INVALID_GRANT),
            'access_denied' => new SsoException($mesej ?: 'Anda tidak mempunyai akses ke sistem ini.', SsoException::ACCESS_DENIED),
            default => new SsoException("Respons iLogin tidak dijangka (HTTP {$status}).", SsoException::INVALID_RESPONSE),
        };
    }

    /**
     * @param array<string, string> $form
     * @return array{status: int, body: string}
     *
     * @throws SsoException
     */
    private function post(string $url, array $form): array
    {
        $pilihan = ['verify_tls' => $this->verifyTls, 'timeout' => $this->timeout];

        if ($this->transport !== null) {
            return ($this->transport)($url, $form, $pilihan);
        }

        if (! function_exists('curl_init')) {
            throw new SsoException('Sambungan PHP cURL diperlukan.', SsoException::CONNECTION);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($form),
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ralat = curl_error($ch);

        if ($body === false) {
            throw new SsoException('Tidak dapat menghubungi iLogin: ' . $ralat, SsoException::CONNECTION);
        }

        return ['status' => $status, 'body' => (string) $body];
    }

    private function pastikanSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (headers_sent()) {
                throw new \LogicException('Session belum dimulakan dan header telah dihantar. Panggil session_start() lebih awal.');
            }
            session_start();
        }
    }
}
