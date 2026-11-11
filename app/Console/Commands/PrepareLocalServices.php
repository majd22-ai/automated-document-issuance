<?php

namespace App\Console\Commands;

use App\Http\Requests\SaveGovernmentServiceRequest;
use App\Models\GovernmentService;
use App\Services\Oversight\AuditTrail;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PrepareLocalServices extends Command
{
    protected $signature = 'services:prepare-local {--database= : Explicit local database name} {--apply : Save the validated changes; otherwise preview only}';

    protected $description = 'Prepare the eight existing services for an unofficial local trial only';

    private const REFERENCE = 'تجربة داخلية غير رسمية — لا يوجد اعتماد حكومي';

    private const IDENTITY_FIRST_ISSUE_CONDITIONS = [
        'استكمال بيانات الاستمارة وحفظها للمراجعة.',
        'إرفاق المستندات التجريبية المطلوبة ومراجعتها من الموظف.',
        'إكمال مصادقة العاقل في المسار التجريبي.',
        'تقييم أهلية المواطن يدويًا باستخدام مرجع تجريبي موثق.',
    ];

    private const IDENTITY_FIRST_ISSUE_DOCUMENTS = [
        'إثبات بيانات الميلاد (تجريبي)',
        'مستند إثبات الهوية والبيانات الشخصية (تجريبي)',
        'تعريف عاقل الحارة أو جهة العمل (تجريبي)',
        'إثبات الجنسية (تجريبي)',
        'صورة شخصية حديثة بخلفية بيضاء (تجريبي)',
        'شهادة بيان فصيلة الدم (تجريبية)',
        'بطاقات هوية الشاهدين (تجريبية)',
    ];

    private const SERVICES = [
        'identity-first-issue' => ['identity', 'issue'],
        'identity-renewal' => ['identity', 'renewal'],
        'identity-lost' => ['identity', 'replacement'],
        'passport-first-issue' => ['passport', 'issue'],
        'passport-renewal' => ['passport', 'renewal'],
        'license-first-issue' => ['driving_license', 'issue'],
        'license-renewal' => ['driving_license', 'renewal'],
        'birth-certificate-first-issue' => ['birth_certificate', 'issue'],
    ];

    public function handle(): int
    {
        $connection = DB::connection();
        $isolatedTest = app()->environment('testing') && $connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:';
        $local = app()->environment('local') && $connection->getDriverName() === 'mysql'
            && in_array($connection->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)
            && ! filled($connection->getConfig('url')) && $connection->getDatabaseName() === 'e_issuance';
        if ((! $local && ! $isolatedTest) || $this->option('database') !== $connection->getDatabaseName()) {
            $this->error('Refused: requires the explicit verified local database or an isolated in-memory test.');

            return self::FAILURE;
        }

        try {
            $rows = DB::transaction(function () {
                $services = GovernmentService::whereIn('code', array_keys(self::SERVICES))->orderBy('id')->lockForUpdate()->get();
                if ($services->count() !== count(self::SERVICES)) {
                    throw ValidationException::withMessages(['services' => 'الخدمات الثماني غير مكتملة. طبّق الترحيلات المعلقة أولًا؛ لن ينشئ هذا الأمر خدمة مكررة.']);
                }
                $plans = [];
                foreach ($services as $service) {
                    if ([$service->document_type, $service->operation] !== self::SERVICES[$service->code]) {
                        throw ValidationException::withMessages(['services' => 'تصنيف الخدمة لا يطابق رمزها. يلزم فحص المسؤول دون تغيير نوع الوثيقة.']);
                    }
                    if (($service->workflow_rules['payment']['required'] ?? false) && $service->fee === null) {
                        throw ValidationException::withMessages(['fee' => 'الخدمة تشترط السداد دون رسوم موثقة؛ لن تُخترع رسوم أو يُلغى شرط السداد.']);
                    }
                    $patch = ['is_active' => true, 'rules_approved' => true];
                    if (! filled($service->source_reference)) {
                        $patch['source_reference'] = self::REFERENCE;
                    }
                    if ($service->code === 'identity-first-issue') {
                        $documents = $service->required_documents ?: self::IDENTITY_FIRST_ISSUE_DOCUMENTS;
                        $patch['description'] = $service->description ?: 'إعداد افتراضي لأغراض مشروع التخرج والاختبار المحلي فقط، وليس متطلبًا أو اعتمادًا حكوميًا.';
                        $patch['conditions'] = $service->conditions ?: self::IDENTITY_FIRST_ISSUE_CONDITIONS;
                        $patch['required_documents'] = $documents;
                        $patch['required_fields'] = $service->required_fields ?: ['name', 'email', 'phone'];
                        $patch['verification_rules'] = $this->identityFirstIssueVerificationRules($service->verification_rules ?? [], $documents);
                    }
                    // Only the engine's active document state; no invented legal expiry/window.
                    if ($service->operation === 'renewal' && ! $service->renewal_statuses) {
                        $patch['renewal_statuses'] = ['active'];
                    }
                    // Replacement issuance has no implemented policy: do not enable it.
                    if ($service->operation !== 'replacement' && $service->issuance_rules === null) {
                        $patch['issuance_rules'] = [
                            'enabled' => true, 'source_reference' => self::REFERENCE,
                            'expiry' => ['required' => false], 'number_prefix' => 'TEST',
                            'include_year' => true, 'electronic_copy' => 'internal_html',
                            'citizen_download' => true,
                            // No fabricated pickup center, validity period or physical dispatch.
                            'delivery' => ['methods' => ['address'], 'centers' => []],
                            'completion_stage' => 'issuance',
                        ];
                    }
                    $request = SaveGovernmentServiceRequest::create('/admin/services/'.$service->id, 'PATCH', ['name' => $service->name, ...$patch]);
                    $route = (new Route(['PATCH'], 'admin/services/{service}', fn () => null))->bind($request);
                    $route->setParameter('service', $service);
                    $request->setRouteResolver(fn () => $route);
                    $validator = Validator::make($request->all(), $request->rules());
                    foreach ($request->after() as $after) {
                        $validator->after($after);
                    }
                    $validated = $validator->validate();
                    $service->fill($validated);
                    $plans[] = [$service, array_keys($service->getDirty())];
                }
                $rows = [];
                foreach ($plans as [$service, $fields]) {
                    if ($this->option('apply') && $fields) {
                        $service->save(); // Preserve Eloquent immutability, versions and observers.
                        app(AuditTrail::class)->record('service.local_trial_prepared', 'service', $service->id, changes: ['changed_fields' => $fields]);
                    }
                    $rows[] = [$service->code, $fields ? implode(', ', $fields) : 'unchanged', $service->fee ?? 'unspecified'];
                }

                return $rows;
            });
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        $this->table(['Service', $this->option('apply') ? 'Saved fields' : 'Preview fields', 'Existing fee'], $rows);
        $this->warn(self::REFERENCE.' — رسوم غير محددة لا تعني خدمة مجانية.');

        return self::SUCCESS;
    }

    private function identityFirstIssueVerificationRules(array $rules, array $requiredDocuments): array
    {
        $documentRules = is_array($rules['documents'] ?? null) ? array_values($rules['documents']) : [];
        $configuredNames = array_column($documentRules, 'name');
        $configuredCodes = array_column($documentRules, 'code');

        foreach (array_values($requiredDocuments) as $index => $name) {
            if (in_array($name, $configuredNames, true)) {
                continue;
            }

            $code = 'demo_document_'.($index + 1);
            $suffix = 1;
            while (in_array($code, $configuredCodes, true)) {
                $code = 'demo_document_'.($index + 1).'_'.(++$suffix);
            }
            $isPhoto = is_string($name) && str_starts_with($name, 'صورة') && ! str_starts_with($name, 'صورتان');
            $documentRules[] = [
                'code' => $code,
                'name' => $name,
                'kind' => $isPhoto ? 'photo' : 'document',
                'allowed_mime_types' => $isPhoto ? ['image/jpeg', 'image/png'] : ['image/jpeg', 'image/png', 'application/pdf'],
                'requires_review' => true,
            ];
            $configuredNames[] = $name;
            $configuredCodes[] = $code;
        }

        foreach ($documentRules as &$documentRule) {
            $name = $documentRule['name'] ?? null;
            if (! is_string($name) || ! in_array($name, $requiredDocuments, true)) {
                if (($documentRule['code'] ?? null) === 'identity_photo') {
                    $documentRule = [
                        ...$documentRule,
                        'name' => 'الصورة الشخصية لاستمارة البطاقة',
                        'kind' => 'photo',
                        'allowed_mime_types' => ['image/jpeg', 'image/png'],
                        'requires_review' => true,
                        'optional' => true,
                    ];
                }

                continue;
            }
            $isPhoto = str_starts_with($name, 'صورة') && ! str_starts_with($name, 'صورتان');
            $documentRule['kind'] = $isPhoto ? 'photo' : 'document';
            $documentRule['allowed_mime_types'] = $isPhoto ? ['image/jpeg', 'image/png'] : ['image/jpeg', 'image/png', 'application/pdf'];
        }
        unset($documentRule);

        if (! collect($documentRules)->contains('kind', 'photo')) {
            $code = 'identity_photo';
            $suffix = 1;
            while (in_array($code, $configuredCodes, true)) {
                $code = 'identity_photo_'.(++$suffix);
            }
            $documentRules[] = [
                'code' => $code,
                'name' => 'الصورة الشخصية لاستمارة البطاقة',
                'kind' => 'photo',
                'allowed_mime_types' => ['image/jpeg', 'image/png'],
                'requires_review' => true,
                'optional' => true,
            ];
        }

        $rules['documents'] = $documentRules;
        $rules['restriction_source'] = $rules['restriction_source'] ?? 'manual';

        return $rules;
    }
}
