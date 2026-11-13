<?php

namespace App\Services\Tracking;

use App\Models\CitizenServiceRequest;
use Illuminate\Support\Facades\DB;

class ApplicationTimeline
{
    public const LABELS = ['identity_notice' => 'إشعار للمواطن', 'identity_issue_failed' => 'تعذر إصدار الوثيقة — بانتظار المعالجة', 'postal_paid' => 'تأكيد سداد البريد', 'identity_appointment' => 'تحديد موعد حضور الهوية', 'identity_arrival' => 'حضور المواطن', 'identity_evidence' => 'تسجيل دليل زيارة الهوية', 'identity_fingerprint' => 'محاولة البصمة التجريبية', 'identity_administration' => 'قرار المراجعة الإدارية', 'official_identity_confirmed' => 'تأكيد الموظف للإصدار الرسمي وترقية الحساب', 'submitted' => 'تقديم الطلب', 'start' => 'بدء المراجعة', 'return' => 'إعادة لاستكمال النواقص', 'resubmit' => 'إعادة التقديم', 'accept' => 'قبول بعد المراجعة', 'reject' => 'رفض الطلب', 'approve' => 'اعتماد تمهيدًا للإصدار', 'processing' => 'بدء تجهيز الوثيقة', 'completed' => 'إكمال الطلب', 'issued' => 'إصدار سجل الوثيقة', 'delivery_selected' => 'اختيار طريقة الاستلام', 'delivery_status' => 'تحديث التسليم'];

    public function rows(CitizenServiceRequest $application)
    {
        $submission = DB::table('citizen_service_requests')->where('id', $application->id)->whereNotNull('submitted_at')->selectRaw("id, 'submitted' as action, submitted_at as occurred_at, NULL as public_reason, NULL as to_status, 0 as source_order");
        $review = DB::table('review_decisions')->where('citizen_service_request_id', $application->id)->whereIn('action', ['start', 'return', 'resubmit', 'accept', 'reject', 'approve', 'processing', 'completed'])
            ->selectRaw('id, action, decided_at as occurred_at, public_reason, to_status, 1 as source_order');
        $documents = DB::table('document_events')->whereIn('citizen_document_id', $application->issuedDocument()->select('id'))->whereIn('action', ['issued', 'delivery_selected', 'delivery_status'])
            ->selectRaw('id, action, occurred_at, NULL as public_reason, to_status, 2 as source_order');

        $official = DB::table('citizen_account_confirmations')->where('citizen_service_request_id', $application->id)->where('user_id', $application->user_id)->where('action', 'confirm_issuance')->where('result', 'confirmed')
            ->selectRaw("id, 'official_identity_confirmed' as action, confirmed_at as occurred_at, NULL as public_reason, 'completed' as to_status, 3 as source_order");

        if (\App\Services\Identity\IdentityJourney::applies($application)) {
            $formId = $application->identityCardForm->id;
            $submission->unionAll(DB::table('postal_collections')->whereIn('request_charge_id', $application->charge()->select('id'))->selectRaw("id, 'postal_paid' as action, collected_at as occurred_at, NULL as public_reason, NULL as to_status, 4 as source_order"));
            $submission->unionAll(DB::table('citizen_appointments')->where('citizen_service_request_id', $application->id)->selectRaw("id, 'identity_appointment' as action, created_at as occurred_at, NULL as public_reason, status as to_status, 5 as source_order"));
            $submission->unionAll(DB::table('identity_card_forms')->where('id', $formId)->whereNotNull('arrived_at')->selectRaw("id, 'identity_arrival' as action, arrived_at as occurred_at, NULL as public_reason, NULL as to_status, 6 as source_order"));
            $submission->unionAll(DB::table('identity_visit_evidence')->where('identity_card_form_id', $formId)->selectRaw("id, 'identity_evidence' as action, created_at as occurred_at, NULL as public_reason, status as to_status, 7 as source_order"));
            $submission->unionAll(DB::table('identity_fingerprint_operations')->where('identity_card_form_id', $formId)->selectRaw("id, 'identity_fingerprint' as action, executed_at as occurred_at, NULL as public_reason, result as to_status, 8 as source_order"));
            $submission->unionAll(DB::table('identity_administrative_decisions')->where('identity_card_form_id', $formId)->selectRaw("id, 'identity_administration' as action, decided_at as occurred_at, citizen_reason as public_reason, action as to_status, 9 as source_order"));
            $submission->unionAll(DB::table('notifications')->where('request_id', $application->id)->where('user_id', $application->user_id)->selectRaw("id, 'identity_notice' as action, created_at as occurred_at, NULL as public_reason, NULL as to_status, 10 as source_order"));
            $submission->unionAll(DB::table('audit_events')->where('subject_type', CitizenServiceRequest::class)->where('subject_id', (string) $application->id)->where('event', 'identity_issue_failed')->selectRaw("id, 'identity_issue_failed' as action, occurred_at, NULL as public_reason, NULL as to_status, 11 as source_order"));
        }

        return DB::query()->fromSub($submission->unionAll($review)->unionAll($documents)->unionAll($official), 'timeline')->orderBy('occurred_at')->orderBy('source_order')->orderBy('id')->paginate(25, ['*'], 'timeline_page')->withQueryString();
    }

    public function nextAction(CitizenServiceRequest $application): string
    {
        if ($application->status === 'accepted' && \App\Services\Identity\IdentityJourney::applies($application)) {
            return app(\App\Services\Identity\IdentityJourney::class)->paid($application)
                ? 'تم تأكيد السداد. تابع إشعار موعد الحضور الذي يحدده الموظف وأحضر نتيجة فحص الدم الخارجي.'
                : 'قُبل الطلب مبدئيًا. سدد الفاتورة في مكتب بريد تجريبي؛ سيحدد الموظف موعد الحضور بعد تأكيد السداد.';
        }
        return match ($application->status) {
            'requires_correction' => 'استكمل النواقص المحددة ثم أعد تقديم الطلب للمراجعة.',
            'rejected' => 'راجع سبب الرفض المعروض. إعادة فتح هذا الطلب ليست متاحة ضمن المسار الحالي.',
            'approved' => 'الطلب معتمد وبانتظار إجراء الإصدار من الموظف المختص.',
            'processing' => $application->issuedDocument?->delivery?->status === 'awaiting_choice' ? 'اختر طريقة استلام الوثيقة من تفاصيلها.' : 'تابع حالة التجهيز والتسليم من تفاصيل الوثيقة.',
            'completed' => 'اكتمل الطلب وفق سياسة الخدمة. يمكنك الرجوع إلى سجل الوثيقة والاستلام.',
            default => 'تابع الطلب وأي متطلبات للمواعيد أو الرسوم من الإجراءات المرتبطة به.',
        };
    }
}
