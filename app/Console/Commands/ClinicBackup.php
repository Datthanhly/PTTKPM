<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;

class ClinicBackup extends Command
{
    protected $signature = 'clinic:backup';

    protected $description = 'Sao lưu demo SQLite/MySQL kèm checksum và số dòng';

    public function handle(DatabaseBackup $backups): int
    {
        $this->info($backups->create());

        return self::SUCCESS;
    }
}
