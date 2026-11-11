<?php

namespace App\Console\Commands;

use App\Models\CitizenAttachment;
use App\Models\GovernmentService;
use App\Models\IdentityCardForm;
use App\Models\User;
use App\Services\Oversight\AuditTrail;
use App\Services\Registration\MockCivilSystems;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PrepareIdentityCardDemoSubmission extends Command
{
    protected $signature = 'identity-card:prepare-demo-submission {--form-id= : Explicit identity-card draft ID} {--apply : Save a local synthetic demo fixture}';

    protected $description = 'Prepare one explicitly selected local mock identity-card draft for academic demonstration';

    public function handle(): int
    {
        $connection = DB::connection();
        $isolatedTest = app()->environment('testing') && $connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:';
        $local = app()->environment('local') && $connection->getDriverName() === 'mysql'
            && in_array($connection->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)
            && ! filled($connection->getConfig('url')) && $connection->getDatabaseName() === 'e_issuance';
        if ((! $local && ! $isolatedTest) || ! MockCivilSystems::enabled()) {
            $this->error('Refused: a local-only civil-registry simulation is required.');

            return self::FAILURE;
        }
        if (! $this->option('form-id') || ! ctype_digit((string) $this->option('form-id'))) {
            $this->error('Specify one draft explicitly with --form-id.');

            return self::FAILURE;
        }

        $formId = (int) $this->option('form-id');
        $createdPaths = [];
        try {
            $result = DB::transaction(function () use ($formId, &$createdPaths) {
                $form = IdentityCardForm::whereKey($formId)->lockForUpdate()->firstOrFail();
                $citizen = User::whereKey($form->user_id)->lockForUpdate()->firstOrFail();
                $service = GovernmentService::whereKey($form->government_service_id)->lockForUpdate()->firstOrFail();
                $attestation = $form->attestation()->lockForUpdate()->first();
                if ($service->code !== 'identity-first-issue' || $form->current_step !== 7 || $form->submitted_at
                    || ! $citizen->is_active || $citizen->role !== 'citizen'
                    || ! in_array($citizen->activation_source, ['automated_local_mock', 'employee_local_mock'], true)
                    || ! $citizen->mock_person_id
                    || ! $citizen->hasActivatedIdentity()
                    || ! $attestation || $attestation->status !== 'approved' || $attestation->form_revision !== $form->revision) {
                    throw ValidationException::withMessages(['form' => 'المسودة المحددة ليست طلبًا تجريبيًا محليًا مكتمل الخطوات وبمصادقة محاكاة معتمدة.']);
                }

                $admins = User::where('role', 'admin')->where('is_active', true)->orderBy('id')->get();
                if ($admins->count() !== 1) {
                    throw ValidationException::withMessages(['admin' => 'يلزم وجود حساب مدير نشط واحد فقط لتسجيل تقييم العرض التجريبي.']);
                }
                $admin = $admins->first();
                $referencePath = 'mock-registry/reference-illustration.png';
                $disk = Storage::disk('attachments');
                if (! $disk->exists($referencePath)) {
                    throw ValidationException::withMessages(['fixture' => 'سجل المحاكاة فارغ؛ شغّل LinkedCivilMockSeeder أولًا.']);
                }
                $bytes = $this->syntheticDocument();
                $rules = $service->verification_rules ?? [];
                $documents = is_array($rules['documents'] ?? null) ? $rules['documents'] : [];
                $requiredDocuments = $service->required_documents ?? [];
                foreach ($requiredDocuments as $name) {
                    $matched = false;
                    foreach ($documents as &$rule) {
                        if (($rule['name'] ?? null) === $name) {
                            $rule['requires_review'] = false;
                            $matched = true;
                        }
                    }
                    unset($rule);
                    if (! $matched) {
                        throw ValidationException::withMessages(['documents' => 'قاعدة مستند مطلوبة غير موجودة: '.$name]);
                    }
                }
                $rules['documents'] = $documents;
                $service->fill(['verification_rules' => $rules]);

                $missingAttachments = 0;
                foreach ($documents as $rule) {
                    if (in_array($rule['name'] ?? null, $requiredDocuments, true)
                        && ! CitizenAttachment::where('user_id', $citizen->id)->where('government_service_id', $service->id)
                            ->whereNull('citizen_service_request_id')->where('document_code', $rule['code'])->exists()) {
                        $missingAttachments++;
                    }
                }
                if (! $this->option('apply')) {
                    return ['form' => $form->id, 'assessment' => null, 'attachments' => $missingAttachments,
                        'version' => $service->rules_version + ($service->isDirty('verification_rules') ? 1 : 0)];
                }

                $service->save();
                $photoRule = collect($documents)->firstWhere('kind', 'photo');
                $existingPhoto = $form->photo;
                if ($photoRule && $existingPhoto && $existingPhoto->document_code === $photoRule['code']
                    && $existingPhoto->user_id === $citizen->id && $existingPhoto->government_service_id === $service->id
                    && $existingPhoto->intact() && in_array($existingPhoto->mime_type, $photoRule['allowed_mime_types'], true)
                    && $existingPhoto->rules_version !== $service->rules_version) {
                    $existingPhoto->forceFill([
                        'document_name' => $photoRule['name'],
                        'rules_version' => $service->rules_version,
                        'version_token' => (string) Str::uuid(),
                    ])->save();
                }

                $assessment = $service->assessments()->updateOrCreate(['user_id' => $citizen->id], [
                    'rules_version' => $service->rules_version,
                    'identity_verified' => true,
                    'conditions_met' => true,
                    'age_requirement_met' => true,
                    'driving_requirements_met' => true,
                    'is_blocked' => false,
                    'block_reason' => null,
                    'public_block_reason' => null,
                    'verification_reference' => 'DEMO-IDENTITY-ASSESSMENT-FORM-'.$form->id,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                ]);

                $fileCount = 0;
                foreach ($documents as $rule) {
                    if (! in_array($rule['name'] ?? null, $requiredDocuments, true)) {
                        continue;
                    }
                    $existing = CitizenAttachment::where('user_id', $citizen->id)->where('government_service_id', $service->id)
                        ->whereNull('citizen_service_request_id')->where('document_code', $rule['code'])->lockForUpdate()->first();
                    if ($existing) {
                        if (! str_starts_with($existing->display_name, 'DEMO-SIMULATION-ONLY-') || ! $existing->intact()) {
                            throw ValidationException::withMessages(['attachments' => 'يوجد مرفق غير تجريبي لهذا المتطلب؛ لم أستبدله. راجعه أو ارفعه يدويًا قبل متابعة التجهيز.']);
                        }
                        if ($existing->rules_version !== $service->rules_version) {
                            $existing->forceFill(['rules_version' => $service->rules_version, 'version_token' => (string) Str::uuid()])->save();
                        }
                        $fileCount++;

                        continue;
                    }
                    $path = 'citizen-files/'.Str::uuid().'.png';
                    $createdPaths[] = $path;
                    if (! $disk->put($path, $bytes, ['visibility' => 'private'])) {
                        throw new \RuntimeException('Unable to write private demo attachment.');
                    }
                    $attachment = new CitizenAttachment;
                    $attachment->forceFill([
                        'user_id' => $citizen->id,
                        'government_service_id' => $service->id,
                        'document_code' => $rule['code'],
                        'document_name' => $rule['name'],
                        'kind' => $rule['kind'],
                        'rules_version' => $service->rules_version,
                        'slot_key' => CitizenAttachment::slot($citizen->id, $service->id, null, $rule['code']),
                        'version_token' => (string) Str::uuid(),
                        'storage_path' => $path,
                        'display_name' => 'DEMO-SIMULATION-ONLY-'.$rule['code'].'.png',
                        'mime_type' => 'image/png',
                        'size' => strlen($bytes),
                        'sha256' => hash('sha256', $bytes),
                        'review_status' => 'uploaded',
                    ])->save();
                    abort_unless($attachment->intact(), 503, 'Synthetic attachment integrity check failed.');
                    $fileCount++;
                }

                if ($this->option('apply')) {
                    app(AuditTrail::class)->record('identity_card.demo_fixture_prepared', IdentityCardForm::class, $form->id,
                        changes: ['changed_fields' => ['demo_assessment', 'demo_attachments', 'demo_review_configuration'], 'government_service_id' => $service->id]);
                }

                return ['form' => $form->id, 'assessment' => $assessment->id, 'attachments' => $fileCount, 'version' => $service->rules_version];
            }, 3);
        } catch (\Throwable $exception) {
            foreach ($createdPaths as $path) {
                Storage::disk('attachments')->delete($path);
            }
            if ($exception instanceof ValidationException) {
                foreach ($exception->errors() as $messages) {
                    foreach ($messages as $message) {
                        $this->error($message);
                    }
                }

                return self::FAILURE;
            }
            throw $exception;
        }

        if (! $this->option('apply')) {
            $this->table(['Draft', 'Assessment', 'Synthetic attachments', 'Rules version'], [[$result['form'], 'would be added', $result['attachments'], $result['version']]]);
            $this->warn('Preview only. No account data was changed; rerun with --apply to prepare this local mock draft.');

            return self::SUCCESS;
        }

        $this->table(['Draft', 'Assessment', 'Synthetic attachments', 'Rules version'], [[$result['form'], $result['assessment'], $result['attachments'], $result['version']]]);
        $this->warn('Synthetic academic fixture only. Reopen step 7 to refresh its review token; this command does not submit the form.');

        return self::SUCCESS;
    }

    private function syntheticDocument(): string
    {
        if (function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor(800, 600);
            $white = imagecolorallocate($image, 255, 255, 255);
            $gray = imagecolorallocate($image, 70, 70, 70);
            $red = imagecolorallocate($image, 160, 30, 30);
            imagefill($image, 0, 0, $white);
            imagestring($image, 5, 270, 230, 'ACADEMIC DEMO ONLY', $red);
            imagestring($image, 4, 250, 280, 'NOT A GOVERNMENT DOCUMENT', $gray);
            ob_start();
            imagepng($image);
            $bytes = ob_get_clean();
            imagedestroy($image);

            return $bytes;
        }

        return Storage::disk('attachments')->get('mock-registry/reference-illustration.png');
    }
}
