# mzm/ssophp

iLogin single sign-on client for native PHP applications (no framework). Laravel applications should use `mzm/sso` instead.

### Requirements

- PHP 8.2+
- `curl` and `json` extensions
- PHP sessions

### How it works

1. Your login button sends the user to iLogin with a random `state` stored in `$_SESSION`.
2. After login and 2FA, iLogin sends the user back to your callback URL with `?code=...&state=...`.
3. Your callback checks the `state` and exchanges the code, server to server, at `POST /api/sso/token` using your `client_id` and `client_secret`. The response contains the user's data.

The code works once and expires after 60 seconds. The `client_secret` never leaves your server.

### Registering your system

Ask the iLogin administrator to:

- register your system with its callback URL (for example `https://system.ns.gov.my/sso-callback.php`), and
- run `php artisan sso:rahsia {system}` on iLogin. This prints `SSO_TOKEN` (client_id) and `SSO_SECRET` (client_secret).

Store both on the server only, outside the document root or in environment variables.

### Installation

With Composer:

```shell
composer require mzm/ssophp
```

Without Composer, copy this folder into your project and include the autoloader:

```php
require __DIR__ . '/lib/ssophp/autoload.php';
```

### Usage

Login page (`sso-login.php`):

```php
use Mzm\SsoPhp\IloginClient;

session_start();

$sso = new IloginClient([
    'base_url' => 'https://ilogin.ns.gov.my',
    'client_id' => getenv('SSO_TOKEN'),
    'client_secret' => getenv('SSO_SECRET'),
]);

$sso->redirectToLogin();
```

Callback (`sso-callback.php`):

```php
use Mzm\SsoPhp\SsoException;

session_start();

try {
    $user = $sso->handleCallback(); // reads $_GET
} catch (SsoException $e) {
    // $e->bolehCubaSemula(): true when the state or code expired. Send the user to iLogin again (once).
    // SsoException::ACCESS_DENIED: iLogin says the user may not use this system.
    exit(htmlspecialchars($e->getMessage()));
}

// Find the user in your own database by $user['email'] (or $user['nokp']).
session_regenerate_id(true);
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

| `getCode()` | Meaning | What to do |
| --- | --- | --- |
| `STATE_MISMATCH` | Session expired, or the callback did not start in this browser | Send the user to iLogin again |
| `INVALID_GRANT` | Code already used or expired | Send the user to iLogin again |
| `ACCESS_DENIED` | User has no access to this system in iLogin | Show the message (403) |
| `INVALID_CLIENT` | Wrong `client_id` or `client_secret` | Check the configuration |
| `CONNECTION` | iLogin unreachable or TLS failure | Show an error and log it |
| `INVALID_RESPONSE` | Unexpected response | Log it |

Keep `verify_tls` set to `true` in production.

### Tests

```shell
php tests/run.php
```
