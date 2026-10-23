<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DocumentCopy extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['storage_path', 'sha256'];

    public function document()
    {
        return $this->belongsTo(CitizenDocument::class, 'citizen_document_id');
    }

    public function contents(): ?string
    {
        if (! preg_match('~^copies/[a-f0-9-]{36}\.html$~D', $this->storage_path ?? '')) {
            return null;
        }
        try {
            $bytes = Storage::disk('documents')->get($this->storage_path);

            return is_string($bytes) && strlen($bytes) === (int) $this->byte_size && hash_equals($this->sha256, hash('sha256', $bytes)) ? $bytes : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Document copy history is immutable'));
        static::deleting(fn () => throw new \LogicException('Document copy history is retained'));
    }
}
