<?php
namespace App\Notifications\Channels;
use App\Notifications\CitizenActivity;
use App\Services\Notifications\NotificationMail;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class InboxChannel extends DatabaseChannel
{
    public function send($notifiable,Notification $notification)
    {
        if(!$notification instanceof CitizenActivity) { throw new \LogicException('Unsupported inbox notification'); }
        $email=$notification->email && app(NotificationMail::class)->configured() && $notifiable->email_verified_at && $notifiable->email_verified_at->lte(now());
        $row=$notifiable->notifications()->firstOrCreate(['event_key'=>hash('sha256',$notifiable->id.':'.$notification->eventKey)],[
            'id'=>(string)Str::uuid(),'type'=>CitizenActivity::class,'data'=>$notification->toDatabase($notifiable),'read_at'=>null,
            'user_id'=>$notifiable->id,'request_id'=>$notification->requestId,'document_id'=>$notification->documentId,
            'mail_status'=>$email?'pending':'disabled','mail_recipient_hash'=>$email?hash('sha256',mb_strtolower(trim($notifiable->email))):null,
        ]);
        if($row->wasRecentlyCreated && $email) { DB::afterCommit(function() use($row) {
            try { app(NotificationMail::class)->enqueue($row->id); }
            catch(\Throwable $error) { try { \Illuminate\Support\Facades\Log::warning('notification_dispatch_deferred',['notification_id'=>$row->id,'error_type'=>get_class($error)]); } catch(\Throwable) {} }
        }); }
        return $row;
    }
}
