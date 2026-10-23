<?php

namespace App\Services\Issuance;

use App\Enums\ServiceRequestStatus;
use App\Models\CitizenDocument;
use App\Models\User;
use App\Services\Operations\LockedRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageDocuments
{
    public function __construct(private LockedRequest $locks, private DocumentAudit $audit, private ElectronicCopies $copies) {}

    public function locked(User $actor, CitizenDocument $document, string $ability, callable $work): mixed
    {
        Gate::forUser($actor)->authorize($ability, $document);
        abort_unless($document->application, 422, 'الوثيقة التاريخية غير مرتبطة بعملية إصدار في المنصة.');

        return $this->locks->run($actor, $document->application, 'view', function ($actor, $owner, $service, $request) use ($document, $ability, $work) {
            $document = CitizenDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless($document->user_id === $owner->id && $document->citizen_service_request_id === $request->id && $document->government_service_id === $service->id, 409);
            Gate::forUser($actor)->authorize($ability, $document);

            return $work($actor, $document, $request);
        });
    }

    private function active(CitizenDocument $document): void
    {
        if ($document->status !== 'active' || $document->expires_at?->lt(today())) {
            throw ValidationException::withMessages(['document' => 'الوثيقة ليست سارية لهذه العملية.']);
        }
    }

    public function choose(User $actor, CitizenDocument $document, array $data): void
    {
        $this->locked($actor, $document, 'chooseDelivery', function ($actor, $document, $request) use ($data) {
            if ($this->audit->replay($document, $actor, 'delivery_selected', $data)) {
                return;
            }
            $this->audit->current($document, $data);
            $this->active($document);
            $rules = app(IssuanceRules::class)->get($document->issuance_rules);
            $delivery = $document->delivery;
            if (! in_array($delivery->status, ['awaiting_choice', 'ready'], true)) {
                throw ValidationException::withMessages(['delivery' => 'بدأت إجراءات التسليم؛ لا يمكن تغيير الطريقة أو العنوان.']);
            }
            if (! in_array($data['method'], $rules['delivery']['methods'], true)) {
                throw ValidationException::withMessages(['method' => 'طريقة الاستلام غير متاحة لهذه الخدمة.']);
            }
            if ($data['method'] === 'pickup' && (! in_array($data['center'] ?? null, $rules['delivery']['centers'] ?? [], true) || filled($data['address'] ?? null))) {
                throw ValidationException::withMessages(['center' => 'اختر مركزًا معتمدًا؛ لا يلزم عنوان للاستلام الشخصي.']);
            }
            if ($data['method'] === 'address' && (! filled($data['address'] ?? null) || filled($data['center'] ?? null))) {
                throw ValidationException::withMessages(['address' => 'عنوان التوصيل مطلوب دون مركز استلام شخصي.']);
            }
            $from = $delivery->status;
            $delivery->forceFill(['method' => $data['method'], 'center' => $data['method'] === 'pickup' ? $data['center'] : null, 'address' => $data['method'] === 'address' ? $data['address'] : null, 'status' => 'ready'])->save();
            $document->forceFill(['workflow_version' => $document->workflow_version + 1])->save();
            $this->audit->record($document, $actor, 'delivery_selected', $data, $from, 'ready', details: ['method' => $data['method']]);
        });
    }

    public function status(User $actor, CitizenDocument $document, array $data): void
    {
        $this->locked($actor, $document, 'updateDelivery', function ($actor, $document, $request) use ($data) {
            if ($this->audit->replay($document, $actor, 'delivery_status', $data)) {
                return;
            }
            $this->audit->current($document, $data);
            $this->active($document);
            $delivery = $document->delivery;
            $next = $data['status'];
            $from = $delivery->status;
            $allowed = match ($from) {
                'ready' => ['preparing', ...($delivery->method === 'pickup' ? ['delivered'] : [])],
                'preparing' => ['failed', ...($delivery->method === 'pickup' ? ['delivered'] : ['out_for_delivery'])],
                'out_for_delivery' => ['delivered', 'failed', 'returned'], 'failed' => ['returned', 'ready'], 'returned' => ['ready'],default => [],
            };
            if (! in_array($next, $allowed, true)) {
                throw ValidationException::withMessages(['delivery' => 'انتقال التسليم غير مسموح لطريقة الاستلام والحالة الحالية.']);
            }
            if ((in_array($next, ['failed', 'returned'], true) || in_array($from, ['failed', 'returned'], true)) && ! filled($data['reason'] ?? null)) {
                throw ValidationException::withMessages(['reason' => 'سبب موثق مطلوب للفشل أو الإرجاع أو إعادة التجهيز.']);
            }
            if ($next === 'delivered') {
                if (! filled($data['proof_reference'] ?? null)) {
                    throw ValidationException::withMessages(['proof_reference' => 'مرجع تأكيد الاستلام الداخلي مطلوب دون بيانات شخصية إضافية.']);
                }
                app(IssuanceLifecycle::class)->assertTransition($request, ServiceRequestStatus::Processing);
                $delivery->delivered_at = now();
                $delivery->delivered_by = $actor->id;
                $delivery->proof_reference = $data['proof_reference'];
            }
            $delivery->status = $next;
            $delivery->save();
            $document->forceFill(['workflow_version' => $document->workflow_version + 1])->save();
            $this->audit->record($document, $actor, 'delivery_status', $data, $from, $next);
            app(IssuanceLifecycle::class)->completeIfReady($request, $actor);
        });
    }

    public function regenerate(User $actor, CitizenDocument $document, array $data): void
    {
        $path = null;
        try {
            $this->locked($actor, $document, 'regenerate', function ($actor, $document, $request) use ($data, &$path) {
                if ($this->audit->replay($document, $actor, 'copy_regenerated', $data)) {
                    return;
                }
                $this->audit->current($document, $data);
                if (($document->issuance_rules['electronic_copy'] ?? 'none') === 'none') {
                    throw ValidationException::withMessages(['copy' => 'لا تتضمن سياسة هذه الوثيقة نسخة إلكترونية.']);
                }
                try {
                    $copy = $this->copies->generate($document, $actor);
                    $path = $copy->storage_path;
                } catch (\Throwable) {
                    throw ValidationException::withMessages(['copy' => 'تعذر إنشاء نسخة جديدة؛ بقي سجل النسخ السابق محفوظًا.']);
                }
                $document->forceFill(['workflow_version' => $document->workflow_version + 1])->save();
                $this->audit->record($document,$actor,'copy_regenerated',$data,details: ['copy_id' => $copy->id]);
            });
        } catch (\Throwable $error) {
            $this->copies->discard($path);
            throw $error;
        }
    }
}
