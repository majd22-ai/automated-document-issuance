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
}
