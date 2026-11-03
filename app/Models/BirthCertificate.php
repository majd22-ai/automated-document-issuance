<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BirthCertificate extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['verification_token', 'verification_token_hash', 'certificate_data', 'pdf_path', 'pdf_sha256'];

    protected function casts(): array
    {
        return ['verification_token' => 'encrypted', 'certificate_data' => 'encrypted:array', 'issued_at' => 'immutable_datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CitizenDocument::class, 'citizen_document_id');
    }

    public function pdfContents(): ?string
    {
        if (! preg_match('~^birth-certificates/[a-f0-9-]{36}\.pdf$~D', $this->pdf_path ?? '')) {
            return null;
        }
        try {
            $bytes = Storage::disk('documents')->get($this->pdf_path);

            return is_string($bytes) && strlen($bytes) === (int) $this->pdf_byte_size && hash_equals($this->pdf_sha256, hash('sha256', $bytes)) ? $bytes : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
