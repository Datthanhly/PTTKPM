<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;

class ClinicRestore extends Command
{
    protected $signature = 'clinic:restore {file} {--database=}';

    protected $description = 'Khôi phục vào bản sao mới và xác minh manifest, không ghi đè DB đang chạy';

    public function handle(DatabaseBackup $backups): int
    {
        $this->info('Đã khôi phục và kiểm tra số dòng: '.$backups->restore($this->argument('file'), $this->option('database')));

        return self::SUCCESS;
    }
}
