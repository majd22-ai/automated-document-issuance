<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationMail;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DispatchCitizenNotificationMail extends Command
{
    protected $signature = 'notifications:dispatch-mail {--retry= : Explicitly retry a failed notification UUID, up to configured attempt limit}';

    protected $description = 'Dispatch pending configured citizen mail to the existing asynchronous queue';

    public function handle(NotificationMail $mail): int
    {
        if ($id = $this->option('retry')) {
            if (! Str::isUuid($id) || ! $mail->retry($id)) {
                $this->error('Only failed notification IDs below the attempt limit can be retried.');

                return self::FAILURE;
            }
        }
        $this->info('Pending notifications examined: '.$mail->dispatchPending());

        return self::SUCCESS;
    }
}
