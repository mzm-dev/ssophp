# mzm/ssophp

iLogin single sign-on client for PHP applications without a framework. For Laravel applications, use [`mzm/sso`](https://github.com/mzm-dev/sso).

### Requirements

- PHP 8.2+
- `curl` and `json` extensions
- PHP sessions
- A system registered in iLogin, with its client ID and client secret

### How it works

1. Your login link sends the user to iLogin with a random `state` stored in `$_SESSION`.
2. The user logs in and passes 2FA on iLogin. iLogin checks that the user may use your system.
3. iLogin returns to your callback URL with `?code=...&state=...`.
4. Your callback checks the `state` and exchanges the code, server to server, at `POST /api/sso/token` with your client ID and secret. The response contains the user's data.

The code works once and expires after 60 seconds. The client secret never leaves your server.

## Installation

With Composer:

```shell
composer require mzm/ssophp:^1.0
```

Without Composer, copy this folder into your project (for example `lib/ssophp`) and load its autoloader:

```php
require __DIR__ . '/lib/ssophp/autoload.php';
```

### Registering your system in iLogin

Ask the iLogin administrator to:

- register your system with your domain and callback URL, for example `https://your-system.example/sso-callback.php`;
- generate the client secret, either from the system page in iLogin or with `php artisan sso:rahsia {system}`.

You receive `SSO_TOKEN` (client ID) and `SSO_SECRET` (client secret). The secret is shown once only. Keep both on the server, in environment variables or in a config file outside the document root.

### Configuration

```php
$sso = new Mzm\SsoPhp\IloginClient([
    'base_url' => getenv('SSO_BASE_URL'),      // iLogin URL, required
    'client_id' => getenv('SSO_TOKEN'),        // required
    'client_secret' => getenv('SSO_SECRET'),   // required
    'verify_tls' => true,                      // keep true in production
    'timeout' => 15,                           // seconds
    'session_key' => 'ilogin_sso_state',       // $_SESSION key for the state
]);
```

## Usage

Login link (`sso-login.php`):

```php
session_start();

$sso->redirectToLogin();
```

Callback (`sso-callback.php`):

```php
use Mzm\SsoPhp\SsoException;

session_start();

try {
    $user = $sso->handleCallback(); // reads $_GET
} catch (SsoException $e) {
    if ($e->bolehCubaSemula() && empty($_SESSION['sso_retry'])) {
        $_SESSION['sso_retry'] = true;   // retry once only, to avoid a loop
        $sso->redirectToLogin();
    }
    unset($_SESSION['sso_retry']);
    http_response_code($e->getCode() === SsoException::ACCESS_DENIED ? 403 : 500);
    exit(htmlspecialchars($e->getMessage()));
}
unset($_SESSION['sso_retry']);

// Find the user in your own database by $user['email'] (or $user['nokp']).
// Do not create accounts automatically unless your system allows it.

session_regenerate_id(true); // prevent session fixation
$_SESSION['user_id'] = $id;
```

Complete examples are in [examples/](examples/).

### User data

`handleCallback()` returns:

| Key | Description |
| --- | --- |
| `name` | Full name |
| `email` | Email address |
| `nokp` | Identity card number |
| `no_tel_pejabat`, `no_tel_bimbit` | Phone numbers |
| `jabatan_id`, `jabatan_nama` | Department |
| `jawatan_id`, `jawatan_nama` | Position |
| `gred`, `kod_perkhidmatan` | Grade and service code |

### Errors

`handleCallback()` throws `Mzm\SsoPhp\SsoException`. Use `getCode()` to tell the cases apart:

| `getCode()` | Meaning | What to do |
| --- | --- | --- |
| `STATE_MISMATCH` | Session expired, or the callback did not start in this browser | Send the user to iLogin again |
| `INVALID_GRANT` | Code already used or expired | Send the user to iLogin again |
| `ACCESS_DENIED` | The user has no access to this system in iLogin | Show the message (403) |
| `INVALID_CLIENT` | Wrong client ID or secret | Check the configuration |
| `CONNECTION` | iLogin unreachable, or TLS failure | Show an error and log it |
| `INVALID_RESPONSE` | Unexpected response | Log it |

`bolehCubaSemula()` returns `true` for `STATE_MISMATCH` and `INVALID_GRANT`.

The constructor throws `InvalidArgumentException` when `base_url`, `client_id` or `client_secret` is missing.

### Tests

```shell
php tests/run.php
```
