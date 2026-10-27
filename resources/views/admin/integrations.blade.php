@extends('layouts.public')
@section('title','إعدادات التكامل الإلكتروني')
@section('content')
@if(\App\Services\Integrations\LocalCivilRegistryProvider::enabled())
<div class="container" style="padding-block:1rem"><a class="btn btn-secondary" href="{{ route('integrations.civil-registry-demo') }}">محاكاة السجل المدني — تجربة محلية</a></div>
@endif
<section class="ops-section"><div class="container"><div class="ops-page-head"><h1>التكامل والتحقق الإلكتروني</h1></div><p>تدار جهات الاتصال وأسرارها من إعدادات النشر بعد توفير وثائق API واعتماد المزود. لا تتيح هذه الصفحة إدخال عنوان خارجي أو تزوير نتيجة.</p>
<h2>مزودو التحقق</h2><div class="ops-grid">@forelse($providers as $provider)<article class="card ops-card"><p>{{ $provider['label'] }} ({{ $provider['key'] }}) — {{ $provider['configured']?'المحول معتمد ومفعّل في الإعدادات؛ لا يعني ذلك نجاح الاتصال':'خدمة التحقق غير مهيأة' }}</p></article>@empty<p class="ops-empty">لا يوجد مزود خارجي معتمد مهيأ. لم ينفذ تحقق رسمي.</p>@endforelse</div>
<p><a href="{{ route('admin.services.index') }}">تحديد متطلبات التحقق حسب الخدمة</a> — <a href="{{ route('admin.review-permissions.index') }}">منح صلاحية التحقق للموظف حسب الخدمة</a></p>
<h2>سجل العمليات المنقح</h2><div class="card ops-card" style="padding:1rem;overflow-x:auto"><div class="ops-table-wrap"><table class="table"><thead><tr><th scope="col">المحاولة</th><th scope="col">الطلب</th><th scope="col">النوع</th><th scope="col">المصدر</th><th scope="col">الحالة</th><th scope="col">رمز تقني منقح</th><th scope="col">HTTP</th><th scope="col">عدد المحاولات</th><th scope="col">وقت التنفيذ</th></tr></thead><tbody>@forelse($attempts as $attempt)<tr><td>{{ $attempt->id }}</td><td>{{ $attempt->citizen_service_request_id }}</td><td>{{ $attempt->kind }}</td><td>{{ $attempt->provider }}</td><td>{{ \App\Enums\VerificationStatus::tryFrom($attempt->status)?->label() }}</td><td>{{ $attempt->error_code??'—' }}</td><td>{{ $attempt->http_status??'—' }}</td><td>{{ $attempt->attempts }}</td><td>{{ $attempt->started_at }}</td></tr>@empty<tr><td class="ops-empty" colspan="9">لا توجد عمليات مسجلة.</td></tr>@endforelse</tbody></table></div>{{ $attempts->links() }}</div></div></section>
@endsection
