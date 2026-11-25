<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class LostCardCase extends Model
{
    protected $hidden = ['submission_token', 'active_key'];

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CitizenServiceRequest::class);
    }

    public function police(): HasOne
    {
        return $this->hasOne(MockPoliceLossReport::class);
    }

    public function notice(): HasOneThrough
    {
        return $this->hasOneThrough(MockNewspaperLossNotice::class, MockPoliceLossReport::class);
    }

    public function ready(): bool
    {
        return $this->police?->status === 'valid' && $this->notice?->status === 'published'
            && filled($this->notice->published_on) && filled($this->notice->issue_number) && $this->notice->page_number > 0;
    }
}
