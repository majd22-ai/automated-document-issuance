@if(\App\Services\Identity\IdentityJourney::applies($application))
@php($identityInvoice = $application->charge()->with('postalCollection')->first())
<section class="card" style="padding:1.5rem;margin-block:1rem"><h2>رسوم وموعد الهوية</h2>
@if($identityInvoice)<p>الفاتورة <bdi>{{ $identityInvoice->invoice_number }}</bdi> — {{ $identityInvoice->amount }} {{ $identityInvoice->currency }} — {{ $identityInvoice->statusLabel() }}</p>
@if($identityInvoice->postalCollection)<p>سند البريد <bdi>{{ $identityInvoice->postalCollection->receipt_reference }}</bdi></p>@elseif($identityInvoice->status==='due')<p>سدد نقدًا في مكتب بريد تجريبي؛ يحدد الموظف موعد حضورك بعد تأكيد السداد.</p>@endif
@else<p>تصدر الفاتورة عند القبول المبدئي للطلب.</p>@endif
@foreach($application->appointments()->with('slot')->whereIn('status',['booked','confirmed'])->get() as $identityVisit)<p>موعد الحضور: {{ $identityVisit->slot?->starts_at->setTimezone(config('operations.timezone'))->format('Y-m-d H:i') }} — {{ $identityVisit->slot?->center }}. أحضر أصول الوثائق ونتيجة فحص الدم الخارجي.</p>@endforeach
@if(auth()->user()->role==='citizen' && $application->status==='requires_correction')<a class="btn btn-primary" href="{{ route('citizen.identity-card.show',$application->identityCardForm) }}">استكمال استمارة الهوية وإعادة التقديم</a>@endif
@can('viewReview',$application)<a class="btn btn-outline" href="{{ route('employee.applications.review',$application->id) }}">ملف معاملة الهوية</a>@endcan
</section>
@endif
