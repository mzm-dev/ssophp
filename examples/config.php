<?php

declare(strict_types=1);

// Simpan fail ini di luar document root, atau baca nilai daripada pemboleh ubah persekitaran.
return [
    'base_url' => getenv('SSO_BASE_URL') ?: '',
    'client_id' => getenv('SSO_TOKEN') ?: '',
    'client_secret' => getenv('SSO_SECRET') ?: '',
    'verify_tls' => true,
];
