<?php

namespace App\Services\Tracking;

use App\Models\CitizenServiceRequest;
use Illuminate\Support\Facades\DB;

class ApplicationTimeline
{
    public const LABELS = ['official_identity_confirmed' => 'تأكيد الموظف للإصدار الرسمي وترقية الحساب', 'submitted' => 'تقديم الطلب', 'start' => 'بدء المراجعة', 'return' => 'إعادة لاستكمال النواقص', 'resubmit' => 'إعادة التقديم', 'accept' => 'قبول بعد المراجعة', 'reject' => 'رفض الطلب', 'approve' => 'اعتماد تمهيدًا للإصدار', 'processing' => 'بدء تجهيز الوثيقة', 'completed' => 'إكمال الطلب', 'issued' => 'إصدار سجل الوثيقة', 'delivery_selected' => 'اختيار طريقة الاستلام', 'delivery_status' => 'تحديث التسليم'];

    public function rows(CitizenServiceRequest $application)
    {
        $submission = DB::table('citizen_service_requests')->where('id', $application->id)->whereNotNull('submitted_at')->selectRaw("id, 'submitted' as action, submitted_at as occurred_at, NULL as public_reason, NULL as to_status, 0 as source_order");
        $review = DB::table('review_decisions')->where('citizen_service_request_id', $application->id)->whereIn('action', ['start', 'return', 'resubmit', 'accept', 'reject', 'approve', 'processing', 'completed'])
            ->selectRaw('id, action, decided_at as occurred_at, public_reason, to_status, 1 as source_order');
        $documents = DB::table('document_events')->whereIn('citizen_document_id', $application->issuedDocument()->select('id'))->whereIn('action', ['issued', 'delivery_selected', 'delivery_status'])
            ->selectRaw('id, action, occurred_at, NULL as public_reason, to_status, 2 as source_order');

        $official = DB::table('citizen_account_confirmations')->where('citizen_service_request_id', $application->id)->where('user_id', $application->user_id)->where('action', 'confirm_issuance')->where('result', 'confirmed')
            ->selectRaw("id, 'official_identity_confirmed' as action, confirmed_at as occurred_at, NULL as public_reason, 'completed' as to_status, 3 as source_order");

        return DB::query()->fromSub($submission->unionAll($review)->unionAll($documents)->unionAll($official), 'timeline')->orderBy('occurred_at')->orderBy('source_order')->orderBy('id')->paginate(25, ['*'], 'timeline_page')->withQueryString();
    }

    public function nextAction(CitizenServiceRequest $application): string
    {
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
