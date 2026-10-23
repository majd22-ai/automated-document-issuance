<?php
namespace App\Services\Notifications;
use App\Models\CitizenDocument;
use App\Models\CitizenServiceRequest;
use App\Models\DocumentEvent;
use App\Models\RequestCharge;
use App\Models\ReviewDecision;
use App\Models\User;
use App\Notifications\CitizenActivity;
use Illuminate\Support\Str;
class CitizenUpdates
{
    private function notify(User $citizen,string $key,string $kind,string $title,string $body,?int $request=null,?int $document=null,bool $email=false): void
    {
        if(!$citizen->is_active || $citizen->role!=='citizen') { return; }
        $citizen->notifyNow(new CitizenActivity($key,['kind'=>$kind,'title'=>$title,'body'=>Str::limit($body,600)],$request,$document,$email));
    }
    public function submitted(CitizenServiceRequest $request): void
    {
        $this->notify($request->citizen,'request:'.$request->id.':submitted','submitted','تم تقديم طلبك','حُفظ طلبك وهو بانتظار المراجعة. تابع مراحله من تفاصيل الطلب.',$request->id,email:true);
        if($request->service_snapshot['workflow_rules']['appointment']['required']??false) {
            $this->notify($request->citizen,'request:'.$request->id.':appointment','appointment_required','راجع موعد الخدمة','تتطلب خدمتك موعدًا بالحالة المحددة. راجع المواعيد ومتطلبات الطلب.',$request->id);
        }
    }
    public function review(ReviewDecision $decision): void
    {
        $titles=['start'=>'بدأت مراجعة طلبك','return'=>'طلبك يحتاج إلى استكمال','resubmit'=>'أعيد تقديم طلبك','accept'=>'تم قبول طلبك بعد المراجعة','reject'=>'تم رفض طلبك','approve'=>'تم اعتماد طلبك','completed'=>'اكتمل طلبك'];
        if(!isset($titles[$decision->action])) { return; }
        $body=match($decision->action) { 'return','reject'=>$decision->public_reason ?: 'راجع تفاصيل الطلب لمعرفة الإجراء المطلوب.', 'approve'=>'اعتمد الطلب تمهيدًا للإصدار. الاعتماد وحده لا يعني صدور وثيقة.', 'completed'=>'استوفى الطلب شروط الإكمال المسجلة.', default=>'توجد مرحلة جديدة مسجلة في طلبك. راجع التفاصيل.' };
        $this->notify($decision->application->citizen,'review:'.$decision->id,$decision->action,$titles[$decision->action],$body,$decision->citizen_service_request_id,email:in_array($decision->action,['return','reject'],true));
    }
    public function document(DocumentEvent $event): void
    {
        $document=$event->document; $kind=$event->action;
        if($kind==='issued') { $title='صدرت وثيقتك داخل المنصة'; $body='أنشئ سجل الوثيقة. راجع تفاصيلها وطريقة استلامها؛ لا يمثل ذلك اعتمادًا حكوميًا.'; }
        elseif(in_array($kind,['delivery_selected','delivery_status'],true)) {
            $kind='delivery_'.$event->to_status;
            $titles=['delivery_ready'=>'وثيقتك جاهزة للاستلام أو التجهيز','delivery_preparing'=>'يجري تجهيز وثيقتك','delivery_out_for_delivery'=>'بدأ توصيل وثيقتك','delivery_delivered'=>'تم تسجيل تسليم وثيقتك','delivery_failed'=>'تعذر تسليم وثيقتك','delivery_returned'=>'أعيدت وثيقتك إلى جهة الإصدار'];
            if(!isset($titles[$kind])) { return; } $title=$titles[$kind]; $body='راجع تفاصيل الوثيقة وتعليمات الاستلام. التوصيل مسجل داخليًا في المنصة.';
        } else { return; }
        $this->notify($document->citizen,'document-event:'.$event->id,$kind,$title,$body,$document->citizen_service_request_id,$document->id,$kind==='issued');
    }
    public function charge(RequestCharge $charge): void
    {
        if($charge->status==='due') { $request=$charge->application; $this->notify($request->citizen,'charge:'.$charge->id,'payment_due','رسوم الخدمة مستحقة','يوجد التزام مالي مرتبط بطلبك. راجع صفحة الرسوم والإجراءات المطلوبة.',$request->id); }
    }
    public function expiry(CitizenDocument $document,int $days,int|string $threshold): void
    {
        $expired=$days<0; $date=$document->expires_at->toDateString();
        $this->notify($document->citizen,'expiry:'.$document->id.':'.$date.':'.$threshold,$expired?'document_expired':'document_expiring',$expired?'انتهت صلاحية وثيقتك':'اقترب انتهاء صلاحية وثيقتك',
            $document->document_type.' — تاريخ الانتهاء '.$date.($expired?'':('؛ المتبقي '.$days.' يومًا')).'. راجع متطلبات التجديد؛ لم ينفذ تجديد تلقائيًا.',$document->citizen_service_request_id,$document->id,true);
    }
}
