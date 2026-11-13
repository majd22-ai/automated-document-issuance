@extends('layouts.public')
@section('title', 'شبكة البريد التجريبية')
@section('content')
<section class="ops-section"><div class="container">
  <div class="ops-page-head"><h1>شبكة مكاتب البريد التجريبية — أمانة العاصمة صنعاء</h1></div>
  <div class="card ops-card" role="note"><strong>بيانات تجريبية — محاكاة تعليمية</strong><p>الأسماء والمناطق هنا أمثلة تعليمية؛ لا تؤكد فروعاً رسمية أو ارتباطاً بخدمة التحصيل الفعلية للبريد اليمني. هذه المرحلة لإدارة المكاتب والحسابات فقط.</p><a href="{{ route('postal.login') }}">بوابة دخول محصلي البريد</a></div>
  @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
  @if($errors->any())<div role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <h2>المكاتب</h2>
  @foreach($offices as $office)
    <details class="card ops-card"><summary>{{ $office->name }} — {{ $office->code }} — {{ $office->status === 'active' ? 'نشط' : 'معطل' }} — {{ $office->collectors_count }} حساب — تجريبي</summary>
      <form method="POST" action="{{ route('admin.postal-network.offices.update', $office->id) }}" class="ops-form-grid">
        @csrf @method('PATCH')
        @include('admin.postal-network-office-fields', ['office' => $office])
        <button class="btn btn-primary">حفظ المكتب</button>
      </form>
    </details>
  @endforeach
  <details class="card ops-card"><summary>إضافة مكتب تجريبي</summary>
    <form method="POST" action="{{ route('admin.postal-network.offices.store') }}" class="ops-form-grid">
      @csrf @include('admin.postal-network-office-fields', ['office' => null])
      <button class="btn btn-primary">إنشاء المكتب التجريبي</button>
    </form>
  </details>
  <h2>حسابات موظفي التحصيل المستقلة</h2>
  <p>صلاحية المحصل محصورة ببوابة البريد. لا تتيح مراجعة طلبات المواطنين أو تعديل الرسوم أو إصدار البطاقات. التحصيل نفسه غير متاح في هذه المرحلة.</p>
  @foreach($collectors as $collector)
    <details class="card ops-card"><summary>{{ $collector->name }} — {{ $collector->username }} — {{ $collector->office->name }} — {{ $collector->is_active ? 'نشط' : 'معطل' }}</summary>
      <form method="POST" action="{{ route('admin.postal-network.collectors.update', $collector->id) }}" class="ops-form-grid">
        @csrf @method('PATCH')
        @include('admin.postal-network-collector-fields', ['collector' => $collector])
        <button class="btn btn-primary">حفظ الحساب</button>
      </form>
    </details>
  @endforeach
  {{ $collectors->links() }}
  <details class="card ops-card"><summary>إضافة حساب محصل تجريبي</summary>
    <form method="POST" action="{{ route('admin.postal-network.collectors.store') }}" class="ops-form-grid">
      @csrf @include('admin.postal-network-collector-fields', ['collector' => null])
      <button class="btn btn-primary" @disabled($offices->isEmpty())>إنشاء الحساب</button>
    </form>
  </details>
</div></section>
@endsection
