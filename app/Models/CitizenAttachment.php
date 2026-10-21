<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CitizenAttachment extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['storage_path', 'slot_key', 'sha256'];

    protected function casts(): array
    {
        return ['rules_version' => 'integer', 'size' => 'integer', 'reviewed_at' => 'datetime', 'expires_at' => 'date'];
    }

    public static function slot(int $owner, int $service, ?int $request, string $code): string
    {
        return hash('sha256', $owner.':'.$service.':'.($request ?? 'staged').':'.$code);
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(GovernmentService::class, 'government_service_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CitizenServiceRequest::class, 'citizen_service_request_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return ['uploaded' => 'مرفوع — تحقق فني فقط', 'pending' => 'يحتاج إلى مراجعة', 'approved' => 'مقبول للمرفق', 'rejected' => 'مرفوض', 'replacement_required' => 'يحتاج إلى استبدال'][$this->review_status] ?? 'غير معروف';
    }

    public function intact(): bool
    {
        // Paths are generated internally; never allow a database value to escape the private namespace.
        if (! preg_match('~^citizen-files/[a-f0-9-]{36}\.(png|jpg|pdf)$~D', $this->storage_path ?? '')) {
            return false;
        }
        $stream = null;
        try {
            $disk = Storage::disk('attachments');
            if (! $disk->exists($this->storage_path) || $disk->size($this->storage_path) !== $this->size) {
                return false;
            }
            $stream = $disk->readStream($this->storage_path);
            if (! is_resource($stream)) {
                return false;
            }
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);

            return hash_equals($this->sha256, hash_final($hash));
        } catch (\Throwable) {
            return false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
