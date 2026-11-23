@extends(!empty($toolsFragment) ? 'layouts.operations-tool-fragment' : 'layouts.public')
@section('title', 'ملف المواطن التجريبي')
@section('content')
@php
    $roles = ['father' => 'أب', 'mother' => 'أم', 'child' => 'ابن أو ابنة'];
    $civilStatuses = ['active' => 'سارية', 'expired' => 'منتهية', 'cancelled' => 'ملغاة', 'inactive' => 'غير سارية'];
@endphp
<section class="ops-section" aria-labelledby="profile-title" @if(!empty($toolsFragment)) data-ops-fragment="profile" @endif>
    <div class="container">
        <div class="ops-page-head">
            <a href="{{ route('admin.mock-civil-registry.index', [], false) }}">ملفات المواطنين التجريبية</a>
            <h1 id="profile-title">{{ $person->name }}</h1>
            <p>قيد مدني تجريبي · {{ $person->complete ? 'مكتمل' : 'يحتاج مراجعة' }}</p>
        </div>
        <div class="ops-grid">
            <section class="card ops-card" data-registry-topic="civil">
                <h2>البيانات الشخصية والمدنية</h2>
                <dl>
                    <dt>الرقم الوطني التجريبي</dt><dd><bdi>{{ $person->national_id ?? 'غير مسجل' }}</bdi></dd>
                    <dt>تاريخ الميلاد</dt><dd><bdi>{{ $person->birth_date }}</bdi></dd>
                    <dt>مكان الميلاد</dt><dd>{{ $person->birth_place }}</dd>
                    <dt>الأب</dt><dd>{{ $person->father_name ?? 'غير مسجل' }}</dd>
                    <dt>الأم</dt><dd>{{ $person->mother_name ?? 'غير مسجلة' }}</dd>
                </dl>
            </section>
            <section class="card ops-card" data-registry-topic="family">
                <h2>قيد الأسرة وأفرادها</h2>
                @if($person->family_reference !== null)
                    <p>مرجع الأسرة <bdi>{{ $person->family_reference }}</bdi></p>
                    <ul>
                        @foreach($members as $member)
                            <li>
                                @if($person->id === $member->id)
                                    <strong>{{ $member->name }}</strong>
                                @else
                                    <a href="{{ route('admin.mock-civil-registry.show', $member->id, false) }}">{{ $member->name }}</a>
                                @endif
                                · {{ $roles[$member->family_role] ?? 'فرد في الأسرة' }}
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p>لا يوجد قيد أسرة مرتبط.</p>
                @endif
            </section>
            <section class="card ops-card" data-registry-topic="birth">
                <h2>شهادة الميلاد التجريبية</h2>
                @if($birth)
                    <p>رقم السجل <bdi>{{ $birth->number }}</bdi></p>
                    <p><bdi>{{ $person->birth_date }}</bdi> · {{ $person->birth_place }}</p>
                @else
                    <p>لا توجد شهادة ميلاد مرتبطة.</p>
                @endif
            </section>
            <section class="card ops-card" data-registry-topic="card">
                <h2>البطاقة الشخصية التجريبية</h2>
                @if($card)
                    <p>رقم البطاقة <bdi>{{ $card->number }}</bdi></p>
                    <p>الحالة {{ $civilStatuses[$card->status] ?? 'غير محددة' }}</p>
                @else
                    <p>لا توجد بطاقة شخصية مرتبطة.</p>
                @endif
            </section>
        </div>

        <section class="card ops-card" data-registry-topic="documents">
            <h2>الوثائق المدنية التجريبية</h2>
            <p>المصدر سجل المحاكاة المدنية.</p>
            @forelse($civilDocuments as $document)
                <p>{{ $document->document_type }} · <bdi>{{ $document->document_number }}</bdi> ·
                    {{ $civilStatuses[$document->status] ?? 'حالة غير محددة' }} ·
                    {{ $document->issued_place }} · <bdi>{{ $document->issued_at }}</bdi></p>
            @empty
                <p>لا توجد وثائق مدنية إضافية.</p>
            @endforelse
        </section>

        @if($person->linked)
            <div class="ops-grid">
                <section class="card ops-card" data-registry-topic="requests">
                    <h2>طلبات الخدمات</h2>
                    <p>المصدر حساب المواطن في التطبيق.</p>
                    @forelse($requests as $application)
                        <p><bdi>{{ $application->reference }}</bdi> · <bdi>{{ $application->service_code }}</bdi> · {{ $application->statusLabel() }}</p>
                    @empty
                        <p>لا توجد طلبات متاحة بصلاحياتك.</p>
                    @endforelse
                </section>
                <section class="card ops-card" data-registry-topic="official">
                    <h2>الوثائق الرسمية الصادرة عبر التطبيق</h2>
                    <p>المصدر وثائق حساب المواطن في التطبيق.</p>
                    @forelse($officialDocuments as $document)
                        <p>{{ $document->document_type }} · <bdi>{{ $document->document_number }}</bdi> · {{ $document->status ?? 'حالة غير محددة' }}</p>
                    @empty
                        <p>لا توجد وثائق رسمية متاحة بصلاحياتك.</p>
                    @endforelse
                </section>
                <section class="card ops-card" data-registry-topic="identity">
                    <h2>التحقق من الهوية والوجه</h2>
                    @if($identityStatus !== null)
                        <p>{{ $identityStatus }}</p>
                        <p>{{ $faceStatus }}</p>
                    @else
                        <p>يتطلب عرض الحالة صلاحية مراجعة التفعيل.</p>
                    @endif
                </section>
            </div>
        @else
            <p class="card ops-card" data-registry-topic="identity">لا يوجد حساب مرتبط بهذا القيد المدني.</p>
        @endif
    </div>
</section>
@endsection
