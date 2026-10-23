<?php
namespace App\Services\Notifications;
use App\Models\CitizenDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class ExpiryAlerts
{
    public function run(): int
    {
        $offsets=config('citizen_notifications.expiry_offsets',[]); $policy=config('citizen_notifications.expired_policy','skip');
        if(!is_array($offsets) || !in_array($policy,['skip','once'],true)) { throw new \InvalidArgumentException('Invalid expiry notification configuration'); }
        foreach($offsets as $days) { if(!ctype_digit((string)$days) || (int)$days>36500) { throw new \InvalidArgumentException('Expiry offsets must be nonnegative days up to 36500'); } }
        $offsets=array_values(array_unique(array_map('intval',$offsets))); sort($offsets);
        $statuses=config('citizen_notifications.expiry_statuses',['active']);
        if(!is_array($statuses) || array_filter($statuses,fn($s)=>!is_string($s))) { throw new \InvalidArgumentException('Invalid expiry status configuration'); }
        if(!$offsets && $policy==='skip') { return 0; }
        $count=0;
        CitizenDocument::whereNotNull('expires_at')->whereIn('status',$statuses)->whereNotIn('status',['superseded','cancelled','replaced','ملغاة','مستبدلة'])
            ->whereDate('expires_at','<=',today()->addDays($offsets?max($offsets):0))->orderBy('id')->chunkById(100,function($documents) use(&$count,$offsets,$policy,$statuses) {
                foreach($documents as $document) {
                    DB::transaction(function() use($document,&$count,$offsets,$policy,$statuses) {
                        $owner=User::whereKey($document->user_id)->lockForUpdate()->first();
                        $document=CitizenDocument::whereKey($document->id)->lockForUpdate()->first();
                        if(!$owner || !$owner->is_active || $owner->role!=='citizen' || !$document || $document->user_id!==$owner->id || !$document->expires_at || !in_array($document->status,$statuses,true) || in_array($document->status,['superseded','cancelled','replaced','ملغاة','مستبدلة'],true)
                            || ($document->issued_at && ($document->issued_at->gt(today()) || $document->expires_at->lt($document->issued_at)))) { return; }
                        $days=(int)today()->diffInDays($document->expires_at,false); $threshold=null;
                        if($days<0) { if($policy==='once') { $threshold='expired'; } }
                        else { foreach($offsets as $offset) { if($days<=$offset) { $threshold=$offset; break; } } }
                        if($threshold===null) { return; }
                        $before=$owner->notifications()->count();
                        app(CitizenUpdates::class)->expiry($document,$days,$threshold);
                        $count+=(int)($owner->notifications()->count()>$before);
                    });
                }
            });
        return $count;
    }
}
