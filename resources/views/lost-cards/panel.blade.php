<section data-ops-view="lost-cards" hidden>
    <h3>محاكاة الشرطة وصحيفة الثورة</h3>
    <p>محاكاة أكاديمية · <bdi>{{ $lostCardCase->reference }}</bdi></p>
    @include('operations.messages')
    <div class="card ops-card" style="padding:1rem">
        <h4>محاكاة الشرطة</h4>
        @if($lostCardCase->police)
            <p>بلاغ مسجّل <bdi>{{ $lostCardCase->police->reference }}</bdi></p>
            <a href="{{ route($operator->role.'.lost-cards.verify', [$lostCardCase, 'police']) }}" target="_blank" rel="noopener">رابط التحقق من البلاغ</a>
        @else
            <form method="post" action="{{ route($operator->role.'.lost-cards.police', $lostCardCase) }}">
                @csrf
                <button class="btn btn-primary" type="submit">تقديم بلاغ الشرطة</button>
            </form>
        @endif
    </div>
    <div class="card ops-card" style="padding:1rem;margin-block:1rem">
        <h4>محاكاة صحيفة الثورة</h4>
        @if($lostCardCase->notice?->status === 'published')
            <p>إعلان منشور <bdi>{{ $lostCardCase->notice->reference }}</bdi></p>
            <p>تاريخ النشر <bdi>{{ $lostCardCase->notice->published_on }}</bdi> · العدد {{ $lostCardCase->notice->issue_number }} · الصفحة {{ $lostCardCase->notice->page_number }}</p>
            <a href="{{ route($operator->role.'.lost-cards.verify', [$lostCardCase, 'newspaper']) }}" target="_blank" rel="noopener">رابط التحقق من النشر</a>
        @elseif($lostCardCase->notice?->status === 'pending')
            <p>الإعلان قيد التجهيز.</p>
            <form method="post" action="{{ route($operator->role.'.lost-cards.publish', $lostCardCase) }}">
                @csrf
                <p><label>تاريخ النشر <input type="date" name="published_on" class="form-control" value="{{ old('published_on') }}" min="{{ $lostCardCase->lost_on }}" max="{{ today()->format('Y-m-d') }}" required></label></p>
                <p><label>رقم العدد <input name="issue_number" class="form-control" value="{{ old('issue_number') }}" maxlength="50" required></label></p>
                <p><label>رقم الصفحة <input type="number" name="page_number" class="form-control" value="{{ old('page_number') }}" min="1" max="10000" required></label></p>
                <button class="btn btn-primary" type="submit">توثيق النشر التجريبي</button>
            </form>
        @elseif($lostCardCase->police?->status === 'valid')
            <form method="post" action="{{ route($operator->role.'.lost-cards.notice', $lostCardCase) }}">
                @csrf
                <button class="btn btn-outline" type="submit">طلب النشر في صحيفة الثورة</button>
            </form>
        @else
            <p>يلزم تسجيل بلاغ الشرطة أولًا.</p>
        @endif
    </div>
</section>
