<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClinicDatabase extends Command
{
    protected $signature = 'clinic:database';

    protected $description = 'Tạo database local nếu chưa tồn tại, không xóa dữ liệu';

    public function handle(): int
    {
        $driver = config('database.default');
        if ($driver === 'sqlite') {
            $file = config('database.connections.sqlite.database');
            if (! file_exists($file)) {
                touch($file);
            }
        } elseif ($driver === 'mysql') {
            $c = config('database.connections.mysql');
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $c['database'])) {
                $this->error('Tên DB chỉ gồm chữ, số, dấu gạch dưới.');

                return self::FAILURE;
            }
            $pdo = new \PDO('mysql:host='.$c['host'].';port='.$c['port'], $c['username'], $c['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$c['database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } else {
            $this->error('Setup Laragon hỗ trợ MySQL hoặc SQLite.');

            return self::FAILURE;
        }
        $this->info('Database đã sẵn sàng.');

        return self::SUCCESS;
    }
}
