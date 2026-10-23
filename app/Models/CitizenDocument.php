<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CitizenDocument extends Model
{
    protected $fillable = ['document_type', 'document_type_code', 'document_number', 'status', 'issued_at', 'expires_at'];

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'expires_at' => 'date', 'issued_recorded_at' => 'immutable_datetime', 'issuance_rules' => 'array', 'holder_snapshot' => 'encrypted:array', 'workflow_version' => 'integer'];
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function application()
    {
        return $this->belongsTo(CitizenServiceRequest::class, 'citizen_service_request_id');
    }

    public function service()
    {
        return $this->belongsTo(GovernmentService::class, 'government_service_id');
    }

    public function previousDocument()
    {
        return $this->belongsTo(self::class, 'previous_document_id');
    }

    public function successor()
    {
        return $this->hasOne(self::class, 'previous_document_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function copies()
    {
        return $this->hasMany(DocumentCopy::class);
    }

    public function delivery()
    {
        return $this->hasOne(DocumentDelivery::class);
    }

    public function events()
    {
        return $this->hasMany(DocumentEvent::class);
    }

    public function validityLabel(): string
    {
        if ($this->status === 'superseded') {
            return 'مستبدلة بوثيقة مجددة';
        }
        if ($this->expires_at?->lt(today())) {
            return 'منتهية الصلاحية';
        }

        return $this->status === 'active' ? 'صادرة وسارية حسب السجل' : ($this->status ?? 'غير محددة');
    }

    protected static function booted(): void
    {
        static::updating(function (self $document) {
            if ($document->getOriginal('issued_by') && $document->isDirty(['user_id', 'citizen_service_request_id', 'government_service_id', 'previous_document_id', 'document_type', 'document_type_code', 'document_number', 'issued_at', 'expires_at', 'issued_by', 'issued_recorded_at', 'issuance_rules', 'holder_snapshot'])) {
                throw new \LogicException('Issued document identity and policy are immutable');
            }
        });
        static::deleting(function (self $document) {
            if ($document->issued_by) {
                throw new \LogicException('Issued document history is retained');
            }
        });
    }
}
