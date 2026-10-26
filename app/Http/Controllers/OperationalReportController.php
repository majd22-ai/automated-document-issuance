<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFiltersRequest;
use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\GovernmentService;
use App\Services\Oversight\AuditTrail;
use App\Services\Oversight\OperationalReports;
use Illuminate\Http\Request;

class OperationalReportController extends Controller
{
    public function index(ReportFiltersRequest $request, OperationalReports $reports)
    {
        $report = $reports->build($request->user()->fresh(), $request->validated());
        $report['recent_audit'] = $request->routeIs('admin.dashboard') ? AuditEvent::latest('id')->limit(10)->get(['id', 'event', 'actor_id', 'subject_type', 'subject_id', 'result', 'occurred_at']) : collect();

        return response()->view('admin.reports.index', compact('report') + ['departments' => $request->user()->role === 'admin' ? Department::orderBy('name')->get() : Department::whereKey($request->user()->department_id)->get(), 'services' => GovernmentService::orderBy('id')->get(['id', 'name']), 'dashboard' => $request->routeIs('admin.dashboard')])->header('Cache-Control', 'no-store, private');
    }

    public function export(ReportFiltersRequest $request, OperationalReports $reports)
    {
        $actor = $request->user()->fresh();
        abort_unless($actor->role === 'admin' || $actor->can_export_reports, 403);
        $report = $reports->build($actor, $request->validated());
        $type = $request->validated('report', 'requests');
        $rows = match ($type) {
            'requests' => array_merge([['إجمالي الطلبات', $report['total_requests']], ['مكتملة من مجموعة الإنشاء', $report['cohort_completed']], ['مغلقة في الفترة', $report['closed_in_period']]], array_map(fn ($r) => [$r['key'], $r['total']], $report['request_statuses'])),
            'employees' => $reports->staffQuery($report['filters'])->lazy(500)->map(fn ($r) => [$r->employee_id, $r->actions, $r->requests, $r->approvals, $r->assigned_open, $r->completed, $r->average_seconds ?? 'غير متاح']),
            'departments' => $reports->departmentQuery($report['filters'])->lazy(500)->map(fn ($r) => [$r->department_id, $r->name, $r->requests, $r->review_actions, $r->documents]),
            'documents' => array_map(fn ($r) => [$r['key'], $r['total']], $report['document_statuses']),
            'payments' => array_map(fn ($r) => [$r['currency'], $r['total'], $r['amount']], $report['payments']),
            'integrations' => array_map(fn ($r) => [$r['key'], $r['total']], $report['verification_statuses'])
        };
        app(AuditTrail::class)->record('report.export', 'report', null, $actor, 'success', ['report' => $type, 'from' => $report['filters']['from_at']->toDateString(), 'to' => $report['filters']['to_at']->toDateString(), 'department_id' => $report['filters']['scope_department']]);

        return response()->streamDownload(function () use ($rows, $report, $type) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $write = fn ($row) => fputcsv($out, array_map([OperationalReports::class, 'csvCell'], $row), ',', '"', '');
            $write(['تقرير المنصة', $type]);
            $write(['من', $report['filters']['from_at']->toDateTimeString(), 'إلى', $report['filters']['to_at']->toDateTimeString(), 'المنطقة', config('app.timezone')]);
            $write(['تاريخ الإنشاء', now()->toDateTimeString()]);
            $write(['القسم', $report['filters']['scope_department'] ?? 'الكل', 'الموظف', $report['filters']['employee_id'] ?? 'الكل', 'الخدمة', $report['filters']['service_id'] ?? 'الكل', 'الحالة', $report['filters']['status'] ?? 'الكل']);
            $write(match ($type) {
                'employees' => ['معرف الموظف', 'قرارات المراجعة', 'طلبات مميزة', 'اعتمادات', 'عبء مفتوح', 'مكتملة بالمنفذ', 'متوسط الإكمال بالثواني'],'departments' => ['معرف القسم', 'القسم', 'الطلبات', 'القرارات', 'الوثائق'],'payments' => ['العملة', 'الإيصالات المطابقة', 'المبلغ'],'requests' => ['المؤشر / الحالة', 'العدد'],default => ['الحالة المسجلة', 'العدد']
            });
            foreach ($rows as $row) {
                $write($row);
            }fclose($out);
        }, 'report-'.$type.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function audit(Request $request)
    {
        return response()->view('admin.audit-logs.index', ['events' => $this->auditQuery($request)->with(['actor:id,name', 'department:id,name'])->latest('id')->paginate(30)->withQueryString()])->header('Cache-Control', 'no-store, private');
    }

    private function auditQuery(Request $request)
    {
        $f = $request->validate(['actor_id' => ['nullable', 'integer'], 'event' => ['nullable', 'string', 'max:100'], 'subject_type' => ['nullable', 'string', 'max:50'], 'result' => ['nullable', 'in:success,failed'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $events = AuditEvent::with(['actor:id,name', 'department:id,name']);
        foreach (['actor_id', 'event', 'subject_type', 'result'] as $key) {
            if (! empty($f[$key])) {
                $events->where($key, $f[$key]);
            }
        }
        $events->when($f['from'] ?? null, fn ($q, $d) => $q->where('occurred_at', '>=', $d.' 00:00:00'))->when($f['to'] ?? null, fn ($q, $d) => $q->where('occurred_at', '<=', $d.' 23:59:59'));

        return $events;
    }

    public function exportAudit(Request $request)
    {
        abort_unless($request->user()->fresh()?->role === 'admin' && $request->user()->fresh()?->is_active, 403);
        $query = $this->auditQuery($request)->without(['actor', 'department']);
        $last = (clone $query)->max('id');
        $query->where('id', '<=', $last ?? 0);
        app(AuditTrail::class)->record('audit.export', 'report', null, $request->user(), 'success', ['report' => 'audit', 'from' => $request->input('from'), 'to' => $request->input('to')]);

        return response()->streamDownload(function () use ($query, $request) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $write = fn ($row) => fputcsv($out, array_map([OperationalReports::class, 'csvCell'], $row), ',', '"', '');
            $write(['سجل التدقيق', 'من', $request->input('from', 'البداية'), 'إلى', $request->input('to', 'الآن'), 'أُنشئ', now()->toDateTimeString(), 'المنطقة', config('app.timezone')]);
            $write(['المنفذ', $request->input('actor_id', 'الكل'), 'الحدث', $request->input('event', 'الكل'), 'نوع السجل', $request->input('subject_type', 'الكل'), 'النتيجة', $request->input('result', 'الكل')]);
            $write(['معرف الحدث', 'الوقت', 'النوع', 'معرف المنفذ', 'معرف القسم', 'نوع السجل', 'معرف السجل', 'النتيجة', 'التتبع']);
            $query->reorder('id')->chunkById(500, function ($events) use ($write) {
                foreach ($events as $event) {
                    $write([$event->id, $event->occurred_at, $event->event, $event->actor_id, $event->department_id, $event->subject_type, $event->subject_id, $event->result, $event->trace_id]);
                }
            });
            fclose($out);
        }, 'audit-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }
}
