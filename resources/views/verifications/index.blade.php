@extends('layouts.public')
@section('title','طلبات التحقق الإلكتروني')
@section('content')
@if(\App\Services\Integrations\LocalCivilRegistryProvider::enabled())
<div class="container" style="padding-block:1rem"><a class="btn btn-secondary" href="{{ route('integrations.civil-registry-demo') }}">محاكاة السجل المدني — تجربة محلية</a></div>
@endif
<section class="section"><div class="container"><div class="ops-page-head"><h1>طلبات التحقق ضمن اختصاصي</h1><p class="ops-help">افتح الطلب للاطلاع على مصادر التحقق ونتائج المحاولات ثم تنفيذ الاستعلام المخول.</p></div><p>تظهر فقط الخدمات التي مُنحت صلاحية التحقق الخارجي لها.</p><div class="card ops-card" style="padding:1rem">@forelse($applications as $application)<article class="ops-card" style="padding:1rem;border-block-end:1px solid var(--border)"><h2>{{ $application->reference }}</h2><p>{{ $application->service_name }} <span class="ops-status">{{ $application->statusLabel() }}</span></p><a class="btn btn-outline" href="{{ route('employee.verifications.show',$application->id) }}">عرض مصادر التحقق والمحاولات</a></article>@empty<p>لا توجد طلبات ضمن صلاحية التحقق الممنوحة لك.</p>@endforelse{{ $applications->links() }}</div></div></section>
@endsection
