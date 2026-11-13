<?php

return [
    'driver' => 'argon2id',
    'bcrypt' => ['rounds' => env('BCRYPT_ROUNDS', 12)],
    'argon' => ['memory' => (int) env('ARGON_MEMORY', 65536), 'threads' => 1, 'time' => (int) env('ARGON_TIME', 4)],
];
