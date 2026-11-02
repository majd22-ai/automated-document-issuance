<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class IdentityCardWitness extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['selfie_path', 'selfie_sha256'];

    public function selfieIntact(): bool
    {
        if (! preg_match('~^witness-selfies/[a-f0-9-]{36}\.(jpg|png)$~D', $this->selfie_path ?? '') || ! $this->selfie_sha256) {
            return false;
        }
        try {
            $disk = Storage::disk('attachments');
            if (! $disk->exists($this->selfie_path) || $disk->size($this->selfie_path) !== $this->selfie_size) {
                return false;
            }
            $hash = hash_file('sha256', $disk->path($this->selfie_path));

            return is_string($hash) && hash_equals($this->selfie_sha256, $hash);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'reviewed_at' => 'datetime', 'selfie_size' => 'integer'];
    }

    public function form()
    {
        return $this->belongsTo(IdentityCardForm::class, 'identity_card_form_id');
    }
}
