@extends('layouts.public')
@section('title','التحقق الإلكتروني')
@section('content')
<section class="section"><div class="container"><h1>التحقق الإلكتروني للطلب {{ $application->reference }}</h1>
<p>وجود بيانات محلية أو قبول مرفق أو تسجيل فحص أو إيصال لا يعني التحقق من جهة خارجية. لا ترسل صور المرفقات أو تفاصيل طبية إلى هذه الطبقة.</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if($configurationError)<p role="alert">قواعد التحقق الخارجي غير صالحة أو متعارضة؛ راجع الإدارة.</p>@endif
@foreach($states as $state)<article class="card" style="padding:1rem;margin-block:1rem"><h2>{{ $state['label'] }}</h2><p>{{ $state['message'] }}</p>
<p>المصدر المحدد: {{ $state['provider']??'لا يوجد' }} — {{ $state['required']?'إلزامي قبل الاعتماد والإصدار':'غير إلزامي' }}</p><p>آخر محاولة: {{ $state['attempted_at']?->format('Y-m-d H:i')??'لم يتم التحقق إلكترونيًا حتى الآن' }}</p>
@can('verifyExternal',$application)@if($state['provider'] && !in_array($application->status,['completed','cancelled','rejected'],true))<form method="POST" action="{{ route('employee.verifications.start',$application->id) }}">@csrf<input type="hidden" name="kind" value="{{ $state['kind'] }}"><input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">@if($state['kind']==='document')<label>الوثيقة السابقة المرتبطة بالمواطن<select class="form-control" name="target_document_id" required><option value="">اختر الوثيقة المراد الاستعلام عنها</option>@foreach($application->citizen->documents()->where('document_type_code',$application->service->document_type)->get() as $document)<option value="{{ $document->id }}">{{ $document->document_number }}</option>@endforeach</select></label>@endif<button class="btn btn-primary">بدء تحقق جديد / إعادة الاستعلام</button></form>@endif @endcan
</article>@endforeach
<h2>سجل محاولات التحقق</h2><p>تظهر النتيجة المسجلة وقت المحاولة؛ صلاحية الاعتماد الحالية موضحة أعلاه.</p>
<div class="card" style="padding:1rem;overflow-x:auto"><table class="table"><thead><tr><th>النوع</th><th>المصدر</th><th>الحالة المسجلة</th><th>الوقت</th></tr></thead><tbody>@forelse($attempts as $attempt)<tr><td>{{ \App\Services\Integrations\IntegrationRules::KINDS[$attempt->kind]??'تحقق' }}</td><td>{{ $attempt->provider }}</td><td>{{ \App\Enums\VerificationStatus::tryFrom($attempt->status)?->label()??'غير معروف' }}</td><td>{{ $attempt->started_at->format('Y-m-d H:i') }}</td></tr>@empty<tr><td colspan="4">لا توجد محاولات تحقق إلكتروني مسجلة.</td></tr>@endforelse</tbody></table>{{ $attempts->links() }}</div>
</div></section>
@endsection
