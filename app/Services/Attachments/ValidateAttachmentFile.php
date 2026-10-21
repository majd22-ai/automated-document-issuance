<?php

namespace App\Services\Attachments;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class ValidateAttachmentFile
{
    public function inspect(UploadedFile $file, array $rule): array
    {
        $fail = fn ($message) => throw ValidationException::withMessages(['file' => $message]);
        if (! $file->isValid() || ! is_readable($file->getPathname()) || $file->getSize() < 1) {
            $fail('الملف فارغ أو تعذر قراءته.');
        }
        if ($file->getSize() > $rule['max_kb'] * 1024) {
            $fail('حجم الملف يتجاوز الحد المسموح لهذا النوع.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        $extension = strtolower($file->getClientOriginalExtension());
        $extensions = ['image/png' => ['png'], 'image/jpeg' => ['jpg', 'jpeg'], 'application/pdf' => ['pdf']];
        if (! in_array($mime, $rule['allowed_mime_types'], true) || ! in_array($extension, $extensions[$mime] ?? [], true)) {
            $fail('نوع الملف الفعلي أو امتداده غير مسموح. ارفع PNG أو JPEG أو PDF حسب شروط الحقل.');
        }
        if (str_starts_with($mime, 'image/')) {
            $info = @getimagesize($file->getPathname());
            if (! $info || ($info['mime'] ?? null) !== $mime || $info[0] < 1 || $info[1] < 1 || config('attachments.max_pixels') < $info[0] * $info[1]) {
                $fail('الصورة تالفة أو تتجاوز أبعاد المعالجة المسموحة.');
            }
            $image = $mime === 'image/png' ? @imagecreatefrompng($file->getPathname()) : @imagecreatefromjpeg($file->getPathname());
            if (! $image) {
                $fail('تعذر فك الصورة؛ ارفع صورة سليمة.');
            }
            imagedestroy($image);
        } else {
            $binary = config('attachments.pdfinfo_binary');
            if (! is_string($binary) || ! is_file($binary) || ! is_executable($binary)) {
                $fail('التحقق من ملفات PDF غير مهيأ حاليًا. ارفع صورة مسموحة أو راجع الجهة المختصة.');
            }
            try {
                $process = new Process([$binary, $file->getPathname()], null, ['LC_ALL' => 'C']);
                $process->setTimeout(8);
                $process->run();
                $readable = $process->isSuccessful() && trim($process->getErrorOutput()) === '' && preg_match('/^Pages:\s+[1-9][0-9]*\s*$/m', $process->getOutput());
            } catch (\Throwable) {
                $readable = false;
            }
            if (! $readable) {
                $fail('ملف PDF تالف أو محمي أو غير قابل للتحقق.');
            }
        }
        $display = preg_replace('/[\x00-\x1f\x7f\/\\\\<>]/u', '_', $file->getClientOriginalName());

        return ['mime_type' => $mime, 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getPathname()), 'display_name' => mb_substr($display ?: 'document.'.$extension, 0, 150), 'extension' => $extensions[$mime][0]];
    }
}
