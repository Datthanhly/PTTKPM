<?php

// Local migration utility: import only into a completely empty MySQL database.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
});

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

if (config('database.default') !== 'mysql') {
    throw new RuntimeException('DB_CONNECTION must be mysql for this command.');
}
$sourcePath = realpath($argv[1] ?? '');
if (! $sourcePath || ! is_file($sourcePath)) {
    throw new RuntimeException('Provide a SQLite source file.');
}
$target = DB::connection();
if ($target->select('SHOW TABLES')) {
    throw new RuntimeException('Target MySQL database must have no tables. Existing data will never be overwritten.');
}
$source = new PDO('sqlite:'.$sourcePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$source->exec('PRAGMA query_only = ON');
$tables = $source->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
if (! $tables || ! $source->query("SELECT COUNT(*) FROM migrations WHERE migration='2026_11_12_000002_extend_clinic_workflows'")->fetchColumn()) {
    throw new RuntimeException('Source must have the latest clinic schema.');
}
Artisan::call('migrate', ['--force' => true]);
echo Artisan::output();
$counts = [];
$target->statement('SET FOREIGN_KEY_CHECKS=0');
try {
    $target->transaction(function () use ($source, $tables, $target, &$counts) {
        foreach ($tables as $table) {
            if (! preg_match('/^[a-z_]+$/', $table)) {
                throw new RuntimeException('Unexpected source table name.');
            }
            $columns = $target->select('SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$target->getDatabaseName(), $table]);
            if (! $columns) {
                throw new RuntimeException('Missing target table: '.$table);
            }
            $types = array_column(array_map(fn ($c) => (array) $c, $columns), 'DATA_TYPE', 'COLUMN_NAME');
            // New migrations create only one bootstrap branch; all other target tables are empty.
            $target->table($table)->delete();
            $rows = $source->query('SELECT * FROM "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                foreach ($row as $column => &$value) {
                    if (! array_key_exists($column, $types)) {
                        throw new RuntimeException('Unknown target column: '.$table.'.'.$column);
                    }
                    if ($value !== null && $types[$column] === 'date') {
                        $value = substr($value, 0, 10);
                    }
                }
                unset($value);
            }
            unset($row);
            foreach (array_chunk($rows, 100) as $chunk) {
                $target->table($table)->insert($chunk);
            }
            $counts[$table] = count($rows);
            if ($target->table($table)->count() !== count($rows)) {
                throw new RuntimeException('Row count mismatch: '.$table);
            }
        }
        $foreignKeys = $target->select('SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL', [$target->getDatabaseName()]);
        foreach ($foreignKeys as $key) {
            $missing = $target->table($key->TABLE_NAME.' as c')->leftJoin($key->REFERENCED_TABLE_NAME.' as p', 'c.'.$key->COLUMN_NAME, '=', 'p.'.$key->REFERENCED_COLUMN_NAME)
                ->whereNotNull('c.'.$key->COLUMN_NAME)->whereNull('p.'.$key->REFERENCED_COLUMN_NAME)->exists();
            if ($missing) {
                throw new RuntimeException('Foreign key mismatch: '.$key->TABLE_NAME.'.'.$key->COLUMN_NAME);
            }
        }
    });
} finally {
    $target->statement('SET FOREIGN_KEY_CHECKS=1');
}
echo 'Imported '.count($counts).' tables, '.array_sum($counts).' rows. Counts and foreign keys verified.'.PHP_EOL;
