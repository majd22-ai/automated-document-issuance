<?php

namespace App\Services\Operations;

use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\ManualPayment;
use App\Models\RequestCharge;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestCharges
{
    public function __construct(private LockedRequest $locks) {}

    public static function money(mixed $amount): ?string
    {
        if ((! is_string($amount) && ! is_int($amount)) || ! preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D', (string) $amount, $parts)) {
            return null;
        }

        return $parts[1].'.'.str_pad($parts[2] ?? '', 2, '0');
    }

    public function ensure(CitizenServiceRequest $application): ?RequestCharge
    {
        return DB::transaction(function () use ($application) {
            User::whereKey($application->user_id)->lockForUpdate()->firstOrFail();
            GovernmentService::whereKey($application->government_service_id)->lockForUpdate()->firstOrFail();
            $application = CitizenServiceRequest::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $existing = $application->charge()->first();
            if ($existing) {
                return $existing;
            }
            if ($application->service_fee === null) {
                return null;
            }
            $amount = self::money($application->service_fee);
            if ($amount === null || ! filled($application->service_currency)) {
                throw ValidationException::withMessages(['charge' => 'بيانات رسم الطلب التاريخية غير مكتملة؛ لا يمكن افتراض رسوم.']);
            }
            $charge = (new RequestCharge)->forceFill(['user_id' => $application->user_id, 'government_service_id' => $application->government_service_id, 'citizen_service_request_id' => $application->id, 'amount' => $amount, 'currency' => $application->service_currency, 'status' => $amount === '0.00' ? 'not_required' : 'due', 'source_reference' => $application->service_snapshot['source_reference'] ?? null]);
            $charge->save();
            app(\App\Services\Notifications\CitizenUpdates::class)->charge($charge);

            return $charge;
        });
    }

    public function record(User $actor, CitizenServiceRequest $application, array $data): ManualPayment
    {
        try {
            return $this->locks->run($actor, $application, 'recordOperations', function ($actor, $owner, $service, $request) use ($data) {
                $charge = $this->ensure($request);
                if (! $charge || $charge->amount === '0.00') {
                    throw ValidationException::withMessages(['charge' => 'لا يوجد التزام مالي مستحق قابل لتسجيل دفعة.']);
                }
                if (self::money($data['amount']) !== $charge->amount || $data['currency'] !== $charge->currency) {
                    throw ValidationException::withMessages(['amount' => 'المبلغ والعملة يجب أن يطابقا الرسم المستحق بالكامل.']);
                }
                $reference = mb_strtoupper(trim($data['receipt_reference']));
                $receipt = ManualPayment::where('receipt_reference', $reference)->first();
                if ($receipt) {
                    if ($receipt->request_charge_id === $charge->id && $receipt->amount === $charge->amount && $receipt->method === $data['method'] && $receipt->paid_at->toDateString() === $data['paid_at']) {
                        return $receipt;
                    }
                    throw ValidationException::withMessages(['receipt_reference' => 'مرجع الإيصال مستخدم مسبقًا ولا يمكن إعادة استخدامه.']);
                }
                if ($charge->status !== 'due' || $charge->payment()->exists()) {
                    throw ValidationException::withMessages(['charge' => 'هذا الرسم مسدد بالفعل أو غير قابل للسداد.']);
                }
                $payment = (new ManualPayment)->forceFill(['request_charge_id' => $charge->id, 'amount' => $charge->amount, 'currency' => $charge->currency, 'receipt_reference' => $reference, 'method' => $data['method'], 'paid_at' => $data['paid_at'], 'recorded_by' => $actor->id, 'recorded_at' => now()]);
                $payment->save();
                $charge->forceFill(['status' => 'paid'])->save();
                $this->locks->invalidate($request);
                $this->locks->audit('manual_payment_recorded', $payment->id, $request, $actor);

                return $payment;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['receipt_reference' => 'تعارض تسجيل الإيصال أو سداد متزامن؛ حدّث الصفحة للتحقق.']);
        }
    }
}
