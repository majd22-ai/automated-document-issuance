@forelse($attachments as $attachment)
  <article class="card mb-3" style="padding:1rem">
    <h3>{{ $attachment->document_name }}</h3>
    <p>{{ $attachment->display_name }} — {{ number_format($attachment->size / 1024, 1) }} كيلوبايت — {{ $attachment->mime_type }}</p>
    <p>الحالة: {{ $attachment->statusLabel() }} — تاريخ الرفع: {{ $attachment->created_at?->format('Y-m-d H:i') }}</p>
    @if($attachment->public_reason)<p>ملاحظة المراجعة: {{ $attachment->public_reason }}</p>@endif
    @if(str_starts_with($attachment->mime_type, 'image/'))
      <img src="{{ route('attachments.preview', $attachment) }}" alt="معاينة {{ $attachment->document_name }}" loading="lazy" style="display:block;max-width:100%;width:180px;max-height:180px;object-fit:contain;margin-block:1rem">
    @endif
    <a class="btn btn-outline" href="{{ route('attachments.download', $attachment) }}">تنزيل الملف</a>
  </article>
@empty
  <p>لا توجد مرفقات محفوظة لهذا السياق حتى الآن.</p>
@endforelse
