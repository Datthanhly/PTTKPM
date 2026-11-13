<?php

namespace App\Console\Commands;

use App\Services\FollowUpService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class ClinicWork extends Command
{
    protected $signature = 'clinic:work';

    protected $description = 'Xử lý email outbox, nhắc lịch và tái khám có đồng ý (một lượt)';

    public function handle(NotificationService $notifications, FollowUpService $followUps): int
    {
        $followUps->process();
        $notifications->reminders();
        $sent = $notifications->process();
        $this->info('Đã xử lý outbox, gửi '.$sent.' thông báo.');

        return self::SUCCESS;
    }
}
