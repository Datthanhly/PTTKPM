<?php
// Collect an empty migrated SQLite schema. Never connect to the operational DB.
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\Artisan::call('migrate', ['--force'=>true]);
$db = Illuminate\Support\Facades\DB::connection('sqlite');
$tables = [];
foreach ($db->select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $table) {
    $n = $table->name;
    $indexes = [];
    foreach ($db->select('PRAGMA index_list("'.$n.'")') as $index) {
        $indexes[] = ['name'=>$index->name,'unique'=>(bool)$index->unique,'columns'=>$db->select('PRAGMA index_info("'.$index->name.'")')];
    }
    $columns=$db->select('PRAGMA table_info("'.$n.'")');
    // Read metadata only from the operational MySQL schema to retain DECIMAL lengths.
    try {
        $physical=Illuminate\Support\Facades\DB::connection('mysql')->select('SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$n]);
        $types=[]; foreach($physical as $c) { $types[$c->COLUMN_NAME]=$c->COLUMN_TYPE; }
        foreach($columns as $c) { $c->mysql_type=$types[$c->name]??null; }
    } catch (Throwable $e) { /* Empty-schema inventory remains reproducible without MySQL. */ }
    $foreign=$db->select('PRAGMA foreign_key_list("'.$n.'")');
    $sqliteForeign=$foreign;
    try {
        $mysql=Illuminate\Support\Facades\DB::connection('mysql');
        $foreign=$mysql->select('SELECT k.COLUMN_NAME AS `from`, k.REFERENCED_TABLE_NAME AS `table`, k.REFERENCED_COLUMN_NAME AS `to`, r.DELETE_RULE AS on_delete FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[$n]);
        $physicalIndexes=[];
        foreach($mysql->select('SHOW INDEX FROM `'.$n.'`') as $ix){
            if($ix->Key_name==='PRIMARY')continue;
            $physicalIndexes[$ix->Key_name]['name']=$ix->Key_name;
            $physicalIndexes[$ix->Key_name]['unique']=!$ix->Non_unique;
            $physicalIndexes[$ix->Key_name]['columns'][]=['name'=>$ix->Column_name];
        }
        $indexes=array_values($physicalIndexes);
    } catch(Throwable $e) { }
    $tables[$n] = ['columns'=>$columns,'foreign_keys'=>$foreign,'sqlite_foreign_keys'=>$sqliteForeign,'indexes'=>$indexes,'sql'=>$table->sql];
}
file_put_contents(__DIR__.'/schema.json', json_encode($tables, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo count($tables)." tables exported from isolated SQLite migration.\n";
