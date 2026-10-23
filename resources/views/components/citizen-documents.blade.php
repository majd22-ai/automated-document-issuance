<div class="card" style="padding:1.5rem;margin-top:1rem;overflow-x:auto;">
  <h2>الوثائق الحكومية المرتبطة</h2>
  <table class="table" style="width:100%;text-align:start;">
    <thead><tr><th>نوع الوثيقة</th><th>رقم الوثيقة</th><th>الحالة المسجلة</th><th>تاريخ الإصدار</th><th>تاريخ الانتهاء</th></tr></thead>
    <tbody>
      @forelse($documents as $document)
        <tr>
          <td>{{ $document->document_type }}</td><td>@if($document->issued_by && auth()->user()->can('view',$document))<a href="{{ route((auth()->user()->role==='citizen'?'citizen':'employee').'.documents.show',$document->id) }}">{{ $document->document_number }}</a>@else{{ $document->document_number }}@endif</td>
          <td>{{ $document->validityLabel() }}</td>
          <td>{{ $document->issued_at?->format('Y-m-d') ?? 'غير متوفر' }}</td>
          <td>{{ $document->expires_at?->format('Y-m-d') ?? 'غير متوفر' }}</td>
        </tr>
      @empty
        <tr><td colspan="5">لا توجد وثائق حكومية مرتبطة بحسابك أو بهذا السجل في المنصة. لا يعني ذلك عدم وجود وثائق لدى الجهة الحكومية.</td></tr>
      @endforelse
    </tbody>
  </table>
  {{ $documents->links() }}
</div>
