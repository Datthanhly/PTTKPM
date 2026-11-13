<?php
if (version_compare(PHP_VERSION, '8.1.0', '<')) { fwrite(STDERR, "Cần PHP 8.1 trở lên.\n"); exit(1); }
foreach (['mbstring', 'openssl', 'pdo', 'pdo_sqlite', 'pdo_mysql', 'fileinfo', 'gd', 'dom', 'xml', 'zip', 'intl'] as $extension) {
    if (!extension_loaded($extension)) { fwrite(STDERR, 'Thiếu extension PHP: '.$extension."\n"); exit(1); }
}
if (!defined('PASSWORD_ARGON2ID')) { fwrite(STDERR, "PHP cần hỗ trợ Argon2id.\n"); exit(1); }
