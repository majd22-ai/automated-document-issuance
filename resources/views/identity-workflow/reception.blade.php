<section class="identity-workflow" data-ops-fingerprint-fragment>
    <p class="workflow-warning">ابحث برقم الطلب أو مرجع الاستمارة. راجع الشروط الظاهرة قبل تسجيل الحضور وفتح جهاز البصمة التجريبي.</p>
    @include('operations.messages')
    <form method="get" action="{{ route('identity-workflow.reception', [], false) }}" class="workflow-panel" data-fingerprint-search>
        <label for="fingerprint-reference">مرجع الطلب أو الاستمارة</label>
        <div class="workflow-actions">
            <input id="fingerprint-reference" name="reference" maxlength="100" value="{{ $reference }}" placeholder="APP-2026-XXXXX">
            <button class="btn btn-primary" type="submit">بحث</button>
        </div>
    </form>

    @if($form)
        <section class="workflow-panel">
            <h4>{{ data_get($form->data, 'applicant_name') ?? $form->citizen?->name }}</h4>
            <p><bdi>{{ $form->application?->reference ?? $form->reference }}</bdi> · {{ $form->statusLabel() }}</p>
            @if($reasons)
                <ul class="workflow-warning" role="status">
                    @foreach($reasons as $reason)<li>{{ $reason }}</li>@endforeach
                </ul>
            @endif
            @if($form->arrived_at)
                <p>سُجّل الحضور في <bdi>{{ $form->arrived_at->format('Y-m-d H:i') }}</bdi> بمرجع <bdi>{{ $form->arrival_reference }}</bdi>.</p>
            @endif
            <div class="workflow-actions">
                @if($canRecord)
                    <a class="btn btn-outline" href="{{ route('identity-workflow.device', $form) }}" target="_blank" rel="noopener">الانتقال لجهاز البصمة</a>
                @endif
                @can('administer', $form)
                    <a class="btn btn-outline" href="{{ route('identity-workflow.administration.show', $form) }}" target="_blank" rel="noopener">إدارة الطلب</a>
                @endcan
            </div>

            @if(!$form->arrived_at && in_array($form->status, ['pending_fingerprint_attendance', 'fingerprint_failed'], true))
                <details>
                    <summary>تسجيل حضور المواطن</summary>
                    <form method="post" action="{{ route('identity-workflow.arrival', $form, false) }}" data-fingerprint-action>
                        @csrf
                        <input type="hidden" name="version" value="{{ $form->version }}">
                        <p><label><input type="checkbox" name="identity_checked" value="1" required> تحققت من هوية المواطن وتطابق بياناته مع الطلب</label></p>
                        <p><label><input type="checkbox" name="documents_checked" value="1" required> تحققت من اكتمال المستندات المطلوبة وصلاحيتها</label></p>
                        <label for="arrival-evidence">رقم وثيقة الإثبات أو وصل الحضور</label>
                        <input id="arrival-evidence" name="evidence_reference" maxlength="150" required>
                        <p><button class="btn btn-primary" type="submit" @disabled(count($reasons))>تأكيد تسجيل الحضور</button></p>
                        <p data-fingerprint-action-status role="status"></p>
                    </form>
                </details>
            @endif

            @if($form->attestation?->status === 'approved' && $form->attestation?->authority_type === 'neighborhood' && $form->attestation?->security_required && $form->attestation?->security_status !== 'recorded_manually')
                <details>
                    <summary>تسجيل الإجراء الأمني</summary>
                    @if($fingerprintReasons)
                        <ul class="workflow-warning">
                            @foreach($fingerprintReasons as $reason)<li>{{ $reason }}</li>@endforeach
                        </ul>
                    @endif
                    <form method="post" action="{{ route('identity-workflow.security', $form, false) }}" data-fingerprint-action>
                        @csrf
                        <input type="hidden" name="version" value="{{ $form->version }}">
                        <p><label><input type="checkbox" name="paper_checked" value="1" required> تحققت من الوثيقة الأمنية وصلاحيتها وتوقيعاتها</label></p>
                        <label for="security-evidence">رقم مرجع الوثيقة الأمنية</label>
                        <input id="security-evidence" name="evidence_reference" maxlength="150" required>
                        <p><button class="btn btn-primary" type="submit">حفظ الإجراء الأمني</button></p>
                        <p data-fingerprint-action-status role="status"></p>
                    </form>
                </details>
            @endif

            @if($form->status === 'returned_to_staff')
                <details>
                    <summary>إعادة الطلب إلى قائمة الانتظار</summary>
                    <form method="post" action="{{ route('identity-workflow.administration.requeue', $form, false) }}" data-fingerprint-action>
                        @csrf
                        <input type="hidden" name="version" value="{{ $form->version }}">
                        <label for="requeue-note">ملاحظات إعادة الإدراج</label>
                        <textarea id="requeue-note" name="note" maxlength="1500" required></textarea>
                        <p><button class="btn btn-primary" type="submit">إعادة الطلب</button></p>
                        <p data-fingerprint-action-status role="status"></p>
                    </form>
                </details>
            @endif
        </section>
    @elseif($reference !== '')
        <p class="workflow-warning" role="status">لم يُعثر على طلب بهذا المرجع ضمن صلاحياتك.</p>
    @endif

    <section class="workflow-panel">
        <h4>الطلبات الحالية</h4>
        <div class="ops-table-wrap">
            <table class="table" aria-label="طلبات استقبال البصمة">
                <thead><tr><th scope="col">المرجع</th><th scope="col">مقدّم الطلب</th><th scope="col">الحالة</th><th scope="col">الإجراء</th></tr></thead>
                <tbody>
                    @forelse($forms as $item)
                        <tr>
                            <td><bdi>{{ $item->application?->reference ?? $item->reference }}</bdi></td>
                            <td>{{ data_get($item->data, 'applicant_name') ?? $item->citizen?->name }}</td>
                            <td>{{ $item->statusLabel() }}</td>
                            <td><a href="{{ route('identity-workflow.reception', ['reference' => $item->reference], false) }}" aria-label="عرض الطلب {{ $item->reference }}">عرض</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4">لا توجد طلبات حالية.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $forms->links() }}
    </section>
</section>