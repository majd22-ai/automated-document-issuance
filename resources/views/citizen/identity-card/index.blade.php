@extends('layouts.public')
@section('title', 'استمارات البطاقة الشخصية')
@push('styles')<link rel="stylesheet" href="{{ asset('css/identity-card-wizard.css') }}">@endpush
@section('content')
<section class="section identity-wizard"><div class="container">
    <div class="identity-heading"><div><h1>استمارات البطاقة الشخصية</h1><p>احفظ بياناتك كمسودة، ثم راجعها قبل تقديم الطلب.</p></div>
    <form method="POST" action="{{ route('citizen.identity-card.store') }}">@csrf<button class="btn btn-primary" type="submit">بدء استمارة جديدة</button></form></div>
    @if(session('status') || session('success'))<p class="identity-notice" role="status">{{ session('status') ?? session('success') }}</p>@endif
    @if($errors->any())<div class="identity-error" role="alert">{{ $errors->first() }}</div>@endif
    <div class="identity-drafts">
    @forelse($forms as $form)
        <article class="identity-panel"><h2>{{ $form->reference }}</h2><p>{{ $form->statusLabel() }}</p><p>آخر تحديث: {{ $form->updated_at?->format('Y-m-d H:i') }}</p>
            <a class="btn btn-outline" href="{{ route('citizen.identity-card.show', ['form'=>$form, 'step'=>$form->submitted_at ? 7 : $form->current_step]) }}">{{ $form->submitted_at ? 'عرض الاستمارة' : 'متابعة المسودة' }}</a>
        </article>
    @empty<div class="identity-panel"><p>لا توجد استمارات حتى الآن. ابدأ استمارة جديدة لإدخال بياناتك.</p></div>@endforelse
    </div>{{ $forms->links() }}
</div></section>
@endsection
