<?php

namespace App\Services\Notifications;

use App\Jobs\SendCitizenNotificationMail;
use App\Mail\CitizenUpdateMail;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationMail
{
    public function configured(): bool
    {
        return config('citizen_notifications.mail_enabled') && in_array(config('mail.default'), ['smtp', 'sendmail', 'ses', 'postmark', 'resend'], true)
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL);
    }

    public function enqueue(string $id): void
    {
        if (! $this->configured() || in_array(config('queue.default'), ['sync', 'null'], true)) {
            return;
        }
        if (! DB::table('notifications')->where('id', $id)->where('mail_status', 'pending')->update(['mail_status' => 'queued', 'mail_queued_at' => now()])) {
            return;
        }
        try {
            SendCitizenNotificationMail::dispatch($id)->onQueue(config('citizen_notifications.mail_queue'));
        } catch (\Throwable $error) {
            DB::table('notifications')->where('id', $id)->where('mail_status', 'queued')->update(['mail_status' => 'pending', 'mail_queued_at' => null]);
            $this->log('notification_queue_unavailable', $id, $error);
        }
    }

    public function dispatchPending(): int
    {
        if (! $this->configured()) {
            return 0;
        }
        DB::table('notifications')->where('mail_status', 'queued')->where('mail_queued_at', '<', now()->subMinutes(15))->update(['mail_status' => 'pending', 'mail_queued_at' => null]);
        $count = 0;
        DatabaseNotification::where('mail_status', 'pending')->orderBy('id')->chunkById(100, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $this->enqueue($row->id);
                $count++;
            }
        });

        return $count;
    }

    public function send(string $id): void
    {
        if (! $this->configured()) {
            return;
        }
        $claimed = DB::transaction(function () use ($id) {
            $note = DatabaseNotification::whereKey($id)->lockForUpdate()->first();
            if (! $note || ! in_array($note->mail_status, ['pending', 'queued'], true) || $note->mail_attempts >= config('citizen_notifications.mail_max_attempts', 3)) {
                return null;
            }
            $citizen = User::find($note->user_id);
            if (! $citizen || ! $citizen->is_active || $citizen->role !== 'citizen' || ! $citizen->email_verified_at || $citizen->email_verified_at->gt(now()) || ! hash_equals($note->mail_recipient_hash ?? '', hash('sha256', mb_strtolower(trim($citizen->email))))) {
                $note->forceFill(['mail_status' => 'skipped'])->save();

                return null;
            }
            $note->forceFill(['mail_status' => 'sending', 'mail_attempts' => $note->mail_attempts + 1])->save();

            return $citizen->email;
        });
        if (! $claimed) {
            return;
        }
        try {
            Mail::to($claimed)->send(new CitizenUpdateMail);
            DB::table('notifications')->where('id', $id)->where('mail_status', 'sending')->update(['mail_status' => 'sent', 'mail_sent_at' => now()]);
        } catch (\Throwable $error) {
            DB::table('notifications')->where('id', $id)->where('mail_status', 'sending')->update(['mail_status' => 'failed']);
            $this->log('notification_mail_failed', $id, $error);
        }
    }

    public function retry(string $id): bool
    {
        return (bool) DB::table('notifications')->where('id', $id)->where('mail_status', 'failed')->where('mail_attempts', '<', config('citizen_notifications.mail_max_attempts', 3))->update(['mail_status' => 'pending', 'mail_queued_at' => null]);
    }

    private function log(string $event, string $id, \Throwable $error): void
    {
        try {
            Log::warning($event, ['notification_id' => $id, 'error_type' => get_class($error)]);
        } catch (\Throwable) {
        }
    }
}
