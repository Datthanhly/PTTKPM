<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DatabaseBackup
{
    public function create(): string
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql'])) {
            throw new \RuntimeException('Backup demo hỗ trợ SQLite/MySQL.');
        }
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir.'/clinic-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).($driver === 'sqlite' ? '.sqlite' : '.sql');
        if ($driver === 'sqlite') {
            DB::connection()->getPdo()->exec('VACUUM INTO '.DB::connection()->getPdo()->quote($path));
            $counts = $this->counts(new \PDO('sqlite:'.$path), $driver);
        } else {
            $pdo = DB::connection()->getPdo();
            $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
            if ($tables) {
                $pdo->exec('LOCK TABLES '.implode(', ', array_map(fn ($table) => '`'.str_replace('`', '``', $table).'` READ', $tables)));
            }
            try {
                $this->runMysql('mysqldump', ['--single-transaction', '--no-tablespaces', config('database.connections.mysql.database')], null, $path);
                $counts = $this->counts($pdo, $driver);
            } finally {
                if ($tables) {
                    $pdo->exec('UNLOCK TABLES');
                }
            }
        }
        file_put_contents($path.'.json', json_encode(['driver' => $driver, 'sha256' => hash_file('sha256', $path), 'counts' => $counts], JSON_PRETTY_PRINT));

        return $path;
    }

    public function restore(string $path, ?string $target): string
    {
        $path = realpath($path);
        if (! $path || ! is_file($path.'.json')) {
            throw new \RuntimeException('Thiếu file backup hoặc manifest .json.');
        }
        $meta = json_decode(file_get_contents($path.'.json'), true, 512, JSON_THROW_ON_ERROR);
        if (! hash_equals($meta['sha256'], hash_file('sha256', $path))) {
            throw new \RuntimeException('Checksum backup không khớp.');
        }
        if ($meta['driver'] === 'sqlite') {
            $dir = storage_path('app/restored');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $destination = $dir.'/restore-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sqlite';
            if (! copy($path, $destination)) {
                throw new \RuntimeException('Không sao chép được bản khôi phục.');
            }
            $pdo = new \PDO('sqlite:'.$destination);
        } elseif ($meta['driver'] === 'mysql') {
            if (! $target || ! preg_match('/^clinic_restore_[a-zA-Z0-9_]+$/', $target)) {
                throw new \RuntimeException('Đặt --database=clinic_restore_<tên_mới> để bảo vệ DB đang chạy.');
            }
            $cfg = config('database.connections.mysql');
            $pdo = new \PDO('mysql:host='.$cfg['host'].';port='.$cfg['port'].';charset=utf8mb4', $cfg['username'], $cfg['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            // CREATE fails if target exists; restore never overwrites a database.
            $pdo->exec('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->runMysql('mysql', [$target], $path, null);
            $pdo->exec('USE `'.$target.'`');
            $destination = $target;
        } else {
            throw new \RuntimeException('Loại backup không hợp lệ.');
        }
        if ($this->counts($pdo, $meta['driver']) !== $meta['counts']) {
            throw new \RuntimeException('Số dòng sau khôi phục không khớp manifest.');
        }

        return $destination;
    }

    private function counts(\PDO $pdo, string $driver): array
    {
        $tables = $pdo->query($driver === 'sqlite' ? "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'" : 'SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `'.str_replace('`', '``', $table).'`')->fetchColumn();
        }
        ksort($counts);

        return $counts;
    }

    private function runMysql(string $tool, array $arguments, ?string $input, ?string $output): void
    {
        $cfg = config('database.connections.mysql');
        $binary = env(strtoupper($tool).'_PATH');
        if (! $binary) {
            $root = dirname(base_path(), 3); // <Laragon>/www/<folder>/<project> or PATH fallback.
            $candidates = glob($root.'/bin/mysql/*/bin/'.$tool.'.exe');
            $binary = $candidates[0] ?? $tool;
        }
        $command = [$binary, '--host='.$cfg['host'], '--port='.$cfg['port'], '--user='.$cfg['username'], ...$arguments];
        $descriptors = [0 => $input ? ['file', $input, 'r'] : ['pipe', 'r'], 1 => $output ? ['file', $output, 'w'] : ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, base_path(), array_merge(getenv(), ['MYSQL_PWD' => $cfg['password'] ?? '']));
        if (! is_resource($process)) {
            throw new \RuntimeException('Không chạy được tiện ích MySQL.');
        }
        if (isset($pipes[0])) {
            fclose($pipes[0]);
        }
        if (isset($pipes[1])) {
            stream_get_contents($pipes[1]);
            fclose($pipes[1]);
        }
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new \RuntimeException('Tiện ích MySQL lỗi. Kiểm tra cấu hình DB và đường dẫn '.$tool.'.');
        }
    }
}
