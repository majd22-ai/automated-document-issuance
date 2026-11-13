<?php

namespace App\Services\Postal;

use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\PostalCollection;
use App\Models\PostalCollector;
use App\Models\PostalOffice;
use App\Models\RequestCharge;
use App\Models\User;
use App\Services\Identity\IdentityJourney;
use App\Services\Oversight\AuditTrail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostalCollections
{
    public function collect(PostalCollector $collector, RequestCharge $charge, array $data): PostalCollection
    {
        abort_unless(app()->environment('local', 'testing') && config('postal_network.enabled') === true, 403);
        validator($data, ['operation_token' => ['required', 'uuid'], 'amount' => ['missing'], 'status' => ['missing']])->validate();

        try {
            return DB::transaction(function () use ($collector, $charge, $data) {
                $expectedAuthVersion = $collector->auth_version;
                $request = $charge->application;
                User::whereKey($request->user_id)->lockForUpdate()->firstOrFail();
                GovernmentService::whereKey($request->government_service_id)->lockForUpdate()->firstOrFail();
                $request = CitizenServiceRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
                $charge = RequestCharge::whereKey($charge->id)->lockForUpdate()->firstOrFail();
                // Lock the collector/office too: disabling either must serialize with cash confirmation.
                $collector = PostalCollector::whereKey($collector->id)->lockForUpdate()->firstOrFail();
                abort_unless($expectedAuthVersion === null || $collector->auth_version === $expectedAuthVersion, 403);
                $office = PostalOffice::whereKey($collector->postal_office_id)->lockForUpdate()->firstOrFail();
                $collector->setRelation('office', $office);
                abort_unless($collector->canAccessPostalWorkspace(), 403);
                $hash = hash('sha256', json_encode([$collector->id, $office->id, $charge->id, $charge->amount, $charge->currency], JSON_THROW_ON_ERROR));
                $existing = PostalCollection::where('operation_token', $data['operation_token'])->first();
                if ($existing) {
                    if (! hash_equals($existing->command_hash, $hash)) {
                        $this->fail('رمز التحصيل مستخدم لعملية مختلفة.');
                    }

                    return $existing;
                }
                if (! IdentityJourney::applies($request) || $request->status !== 'accepted' || ! $charge->invoice_number
                    || $charge->status !== 'due' || $charge->amount === '0.00' || $charge->amount !== $request->service_fee
                    || $charge->currency !== $request->service_currency || $charge->user_id !== $request->user_id
                    || $charge->government_service_id !== $request->government_service_id
                    || $charge->payment()->exists() || $charge->postalCollection()->exists()) {
                    $this->fail('الفاتورة غير قابلة للتحصيل أو سبق سدادها؛ تحقق من السند قبل استلام النقد مرة أخرى.');
                }
                $receipt = (new PostalCollection)->forceFill(['request_charge_id' => $charge->id, 'postal_collector_id' => $collector->id,
                    'postal_office_id' => $office->id, 'receipt_reference' => 'POST-'.Str::ulid(), 'operation_token' => $data['operation_token'],
                    'command_hash' => $hash, 'amount' => $charge->amount, 'currency' => $charge->currency, 'collected_at' => now()]);
                $receipt->save();
                $charge->forceFill(['status' => 'paid'])->save();
                app(AuditTrail::class)->record('postal_collection_confirmed', PostalCollection::class, $receipt->id, changes: ['status' => 'paid', 'action' => 'local_cash_collection']);
                $journey = app(IdentityJourney::class);
                $body = 'تم تأكيد سداد الفاتورة '.$charge->invoice_number.' بقيمة '.$charge->amount.' '.$charge->currency.'، السند '.$receipt->receipt_reference.'. سيحدد الموظف موعد حضورك.';
                $journey->notify($request->citizen, $request, 'postal:'.$receipt->id, 'تأكيد سداد رسوم الهوية', $body);
                $staff = $request->assignee;
                if ($staff?->is_active && $staff->role === 'employee' && $staff->canReviewService($request->government_service_id, 'can_review')) {
                    $journey->notify($staff, $request, 'postal:'.$receipt->id, 'تم سداد طلب مسند إليك', $body);
                }
                $request->forceFill(['requirements_result' => null, 'requirements_checked_at' => null])->save();

                return $receipt;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $this->fail('تعارض تأكيد التحصيل أو رمز عملية مستخدم؛ ابحث برقم الفاتورة وتحقق من السند قبل إعادة المحاولة.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['invoice' => $message]);
    }
}
