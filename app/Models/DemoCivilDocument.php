<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DemoCivilDocument extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['storage_path', 'sha256'];

    protected function casts(): array
    {
        return ['byte_size' => 'integer', 'archived_at' => 'datetime', 'is_synthetic' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $document) => $document->is_synthetic = true);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(DemoCivilPerson::class, 'person_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(DemoCivilRecord::class, 'record_id');
    }

    public function intact(): bool
    {
        if (! $this->safeStoragePath() || $this->byte_size < 1 || $this->byte_size > 5 * 1024 * 1024) {
            return false;
        }
        $disk = Storage::disk('demo-civil-registry');
        if (! $disk->exists($this->storage_path) || $disk->size($this->storage_path) !== $this->byte_size) {
            return false;
        }
        $stream = $disk->readStream($this->storage_path);
        if (! is_resource($stream)) {
            return false;
        }
        $hash = hash_init('sha256');
        hash_update_stream($hash, $stream);
        fclose($stream);

        return hash_equals($this->sha256, hash_final($hash));
    }

    public function safeStoragePath(): bool
    {
        return (bool) preg_match('~^(people|documents)/[a-f0-9-]{36}\.(png|jpg|pdf)$|^fixtures/(portrait|document)\.svg$~D', $this->storage_path ?? '');
    }

    public function uploadedStoragePath(): bool
    {
        return (bool) preg_match('~^documents/[a-f0-9-]{36}\.(png|jpg|pdf)$~D', $this->storage_path ?? '');
    }
}