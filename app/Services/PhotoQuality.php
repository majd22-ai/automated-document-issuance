<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;

class PhotoQuality
{
    private const MESSAGES = [
        'unreadable' => 'تعذر قراءة الصورة. ارفع ملف JPG أو PNG سليمًا.',
        'no_face' => 'لم يتمكن الفحص من اكتشاف وجه واضح. ارفع صورة أمامية يظهر فيها وجهك بوضوح.',
        'multiple_faces' => 'اكتشف الفحص أكثر من وجه. ارفع صورة شخصية يظهر فيها شخص واحد فقط.',
        'blur' => 'الصورة غير واضحة أو ضبابية. ثبّت الكاميرا والتقط صورة أوضح.',
        'dark' => 'الصورة مظلمة أو تحتوي مناطق داكنة كثيرة. أعد التصوير بإضاءة أمامية جيدة.',
        'bright' => 'الصورة شديدة السطوع وتفاصيل الوجه غير واضحة. خفّف الإضاءة المباشرة.',
        'lighting' => 'الإضاءة غير متوازنة على الوجه أو توجد ظلال قوية. استخدم إضاءة أمامية موزعة.',
        'background' => 'الخلفية ليست بيضاء وموحدة بما يكفي. التقط الصورة أمام خلفية بيضاء خالية من الظلال والتفاصيل.',
    ];

    public function inspect(string $path, string $field = 'photo'): void
    {
        $unavailable = fn () => throw ValidationException::withMessages([
            $field => 'تعذر تشغيل فحص الصورة الإلكتروني حاليًا. لم تُعتمد الصورة؛ حاول لاحقًا أو تواصل مع الدعم الفني.',
        ]);
        try {
            $result = Process::timeout(config('photo_quality.timeout', 12))
                ->input(json_encode(config('photo_quality.thresholds'), JSON_THROW_ON_ERROR))
                ->run([config('photo_quality.python'), base_path('scripts/photo_quality.py'), $path]);
            $output = json_decode($result->output(), true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $unavailable();
        }
        if (! $result->successful() || ! is_array($output) || ($output['version'] ?? null) !== 1 || ($output['status'] ?? null) !== 'completed'
            || ! is_bool($output['accepted'] ?? null) || ! is_array($output['errors'] ?? null) || ! array_is_list($output['errors'])) {
            $unavailable();
        }
        if (! $output['accepted']) {
            $messages = [];
            foreach ($output['errors'] as $code) {
                if (! is_string($code) || ! isset(self::MESSAGES[$code])) {
                    $unavailable();
                }
                $messages[] = self::MESSAGES[$code];
            }
            if (! $messages) {
                $unavailable();
            }
            throw ValidationException::withMessages([$field => array_values(array_unique($messages))]);
        }
        if ($output['errors'] !== []) {
            $unavailable();
        }
    }
}
