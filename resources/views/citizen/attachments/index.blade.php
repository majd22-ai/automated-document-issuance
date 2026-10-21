@extends('layouts.public')
@section('title', 'الصور والمرفقات')
@section('content')
<section class="section"><div class="container"><div class="card" style="padding:1.5rem">
  <h1>الصور والمرفقات</h1><h2>{{ $service->name }}</h2>
  @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <p>الملفات خاصة بحسابك. يُحفظ ملف واحد لكل نوع مستند. نجاح الرفع والفحص الفني لا يعني اعتماد المستند رسميًا.</p>
  @if($application)<p>الطلب: {{ $application->reference }} — {{ $application->statusLabel() }}</p>@else<p>لم يُنشأ طلب بعد. تُربط هذه الملفات بطلبك عند تأكيد التقديم.</p>@endif
  @if(! $editable)<p role="status">عرض فقط: حالة الطلب أو الخدمة لا تسمح بتعديل المرفقات.</p>@endif
  @foreach($service->required_documents ?? [] as $name)
    @if(! collect($documentRules)->contains('name', $name))<div class="alert alert-danger">{{ $name }}: لم تعتمد قواعد الرفع لهذا المستند؛ راجع الجهة المختصة.</div>@endif
  @endforeach
  @forelse($documentRules as $code => $rule)
    @php($current = $attachments->get($code))
    <section class="card mb-3" style="padding:1rem">
      <h3>{{ $rule['name'] }} — {{ $rule['mandatory'] ? 'مطلوب' : 'اختياري' }}</h3>
      <p>ملف واحد. الأنواع: {{ implode('، ', $rule['allowed_mime_types']) }}. الحد: {{ $rule['max_kb'] }} كيلوبايت.</p>
      <p>{{ $rule['requires_review'] ? 'يحتاج إلى مراجعة الموظف قبل استيفاء المتطلب.' : 'يتحقق النظام من سلامة الملف الفنية فقط.' }}</p>
      @if(in_array('application/pdf', $rule['allowed_mime_types']) && !config('attachments.pdfinfo_binary'))<p>قبول PDF متوقف حتى إعداد أداة التحقق؛ استخدم نوع صورة مسموحًا إن توفر.</p>@endif
      @if($current)
        @include('components.attachment-list', ['attachments' => [$current]])
        @if($current->rules_version !== $service->rules_version)<p class="alert alert-danger">تغيرت قواعد الخدمة. أعد رفع الملف وفق المتطلبات الحالية.</p>@endif
      @else<p>لم يُرفع هذا المستند بعد.</p>@endif
      @if($editable)
        <form method="POST" enctype="multipart/form-data" action="{{ $application ? route('citizen.applications.attachments.store', $application->id) : route('citizen.services.attachments.store', $service) }}">
          @csrf<input type="hidden" name="document_code" value="{{ $code }}">
          <label class="form-label" for="file-{{ $code }}">{{ $current ? 'اختر الملف البديل' : 'اختر الملف' }}</label>
          <input class="form-control" type="file" id="file-{{ $code }}" name="file" accept="{{ implode(',', $rule['allowed_mime_types']) }}" required>
          <button class="btn btn-primary" type="submit">{{ $current ? 'استبدال المرفق' : 'رفع المرفق' }}</button>
        </form>
        @if($current && !$rule['mandatory'])
          <form method="POST" action="{{ route('citizen.attachments.destroy', $current) }}">@csrf @method('DELETE')<button class="btn btn-outline" type="submit">حذف المرفق الاختياري</button></form>
        @endif
      @endif
    </section>
  @empty<p>لا توجد حقول رفع معتمدة لهذه الخدمة حاليًا.</p>@endforelse
  @if($application)<a href="{{ route('citizen.applications.show', $application->id) }}">العودة إلى الطلب وإعادة التحقق</a>
  @else<a class="btn btn-primary" href="{{ route('citizen.applications.create', ['service' => $service->code]) }}">العودة إلى بيانات الطلب والتحقق</a>@endif
</div></div></section>
@endsection
