<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityVisitEvidence extends Model
{
    protected $table = 'identity_visit_evidence';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['form_revision' => 'integer', 'reviewed_at' => 'immutable_datetime'];
    }

    public function attachment()
    {
        return $this->belongsTo(CitizenAttachment::class, 'citizen_attachment_id');
    }

    public function form()
    {
        return $this->belongsTo(IdentityCardForm::class, 'identity_card_form_id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $row) {
            if ($row->isDirty(['identity_card_form_id', 'form_revision', 'kind', 'citizen_attachment_id', 'recorded_by'])) {
                throw new \LogicException('Evidence provenance is immutable');
            }
        });
        static::deleting(fn () => throw new \LogicException('Identity evidence history is retained'));
    }
}
