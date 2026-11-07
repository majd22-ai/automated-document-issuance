<?php

namespace Tests\Feature;

use App\Services\PhotoQuality;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhotoQualityTest extends TestCase
{
    public function test_live_engine_rejects_a_generated_blank_image(): void
    {
        if (! getenv('PHOTO_QUALITY_LIVE_TEST')) {
            $this->markTestSkipped('Opt-in live Python/OpenCV smoke test.');
        }
        $path = tempnam(sys_get_temp_dir(), 'photo-quality-');
        try {
            $image = imagecreatetruecolor(600, 600);
            imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
            imagepng($image, $path);
            imagedestroy($image);
            try {
                app(PhotoQuality::class)->inspect($path);
                $this->fail('A blank image must not pass the real face detector.');
            } catch (ValidationException $error) {
                $this->assertStringContainsString('لم يتمكن الفحص من اكتشاف وجه', $error->errors()['photo'][0]);
            }
        } finally {
            unlink($path);
        }
    }

    public function test_success_uses_argument_array_and_trusted_stdin_without_shell_interpolation(): void
    {
        Process::fake(['*' => Process::result(output: '{"version":1,"status":"completed","accepted":true,"errors":[]}')]);
        app(PhotoQuality::class)->inspect('private path/صورة.jpg');
        Process::assertRan(fn ($process) => is_array($process->command)
            && $process->command[2] === 'private path/صورة.jpg'
            && json_decode($process->input, true)['blur_min'] === 60);
    }

    public function test_rejection_codes_are_mapped_to_arabic_on_the_correct_field(): void
    {
        foreach (['no_face', 'multiple_faces', 'blur', 'dark', 'bright', 'lighting', 'background', 'unreadable'] as $code) {
            Process::fake(['*' => Process::result(output: json_encode(['version' => 1, 'status' => 'completed', 'accepted' => false, 'errors' => [$code]]))]);
            try {
                app(PhotoQuality::class)->inspect('private.jpg', 'file');
                $this->fail('Rejected photo passed');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('file', $error->errors());
                $this->assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', $error->errors()['file'][0]);
            }
        }
    }

    public function test_unavailable_malformed_and_inconsistent_results_fail_closed_without_raw_errors(): void
    {
        foreach (['private traceback', '"scalar"', '{}', '{"version":1,"status":"completed","accepted":true,"errors":["blur"]}', '{"version":1,"status":"completed","accepted":false,"errors":[]}'] as $output) {
            Process::fake(['*' => Process::result(output: $output, errorOutput: 'SECRET INTERNAL PATH')]);
            try {
                app(PhotoQuality::class)->inspect('private.jpg');
                $this->fail('Invalid engine result passed');
            } catch (ValidationException $error) {
                $this->assertStringContainsString('عطل تقني في خدمة الفحص', $error->errors()['photo'][0]);
                $this->assertStringNotContainsString('SECRET', $error->getMessage());
            }
        }
    }

    public function test_process_exception_and_nonzero_exit_do_not_accept_a_photo(): void
    {
        Process::fake(['*' => Process::result(output: '{"version":1,"status":"completed","accepted":true,"errors":[]}', exitCode: 2)]);
        try {
            app(PhotoQuality::class)->inspect('private.jpg');
            $this->fail('Nonzero exit passed');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('photo', $error->errors());
        }
        Process::shouldReceive('timeout')->once()->andThrow(new \RuntimeException('internal detail'));
        $this->expectException(ValidationException::class);
        app(PhotoQuality::class)->inspect('private.jpg');
    }
}
