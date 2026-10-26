<?php

namespace App\Services\Oversight;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalReports
{
    public function filters(User $actor, array $data): array
    {
        abort_unless($actor->is_active && ($actor->role === 'admin' || ($actor->role === 'employee' && $actor->report_scope === 'department' && $actor->department?->is_active)), 403);
        $now = CarbonImmutable::now(config('app.timezone'));
        [$from,$to] = match ($data['period'] ?? '30days') {
            'today' => [$now->startOfDay(), $now->endOfDay()], '7days' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'month' => [$now->startOfMonth(), $now->endOfDay()],
            'custom' => [CarbonImmutable::parse($data['from'])->startOfDay(), CarbonImmutable::parse($data['to'])->endOfDay()],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()]
        };
        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['to' => 'الحد الأقصى لفترة التقرير 366 يومًا.']);
        }
        $department = $data['department_id'] ?? null;
        if ($actor->role === 'employee') {
            abort_if($department && (int) $department !== (int) $actor->department_id, 403);
            $department = $actor->department_id;
        }
        if ($id = $data['employee_id'] ?? null) {
            $employee = User::findOrFail($id);
            abort_unless(in_array($employee->role, ['employee', 'admin']) && (! $department || (int) $employee->department_id === (int) $department), 403);
        }

        return $data + ['from_at' => $from, 'to_at' => $to, 'scope_department' => $department];
    }

    private function members(array $f)
    {
        return DB::table('users')->select('id')->whereIn('role', ['employee', 'admin'])->when($f['scope_department'], fn ($q, $id) => $q->where('department_id', $id))->when($f['employee_id'] ?? null, fn ($q, $id) => $q->where('id', $id));
    }

    private function requests(array $f, ?string $date = 'created_at')
    {
        return DB::table('citizen_service_requests as r')->when($date, fn ($q) => $q->whereBetween('r.'.$date, [$f['from_at'], $f['to_at']]))
            ->when($f['scope_department'] || ! empty($f['employee_id']), fn ($q) => $q->whereIn('r.assigned_to', $this->members($f)))
            ->when($f['service_id'] ?? null, fn ($q, $id) => $q->where('r.government_service_id', $id))->when($f['status'] ?? null, fn ($q, $s) => $q->where('r.status', $s));
    }

    private function records(string $table, string $date, string $actor, string $request, array $f)
    {
        $q = DB::table($table)->whereBetween($date, [$f['from_at'], $f['to_at']]);
        if ($f['scope_department'] || ! empty($f['employee_id'])) {
            $q->whereIn($actor, $this->members($f));
        }
        if (! empty($f['service_id']) || ! empty($f['status'])) {
            $q->whereExists(function ($q) use ($request, $f) {
                $q->selectRaw('1')->from('citizen_service_requests as rf')->whereColumn('rf.id', $request)->when($f['service_id'] ?? null, fn ($q, $id) => $q->where('rf.government_service_id', $id))->when($f['status'] ?? null, fn ($q, $s) => $q->where('rf.status', $s));
            });
        }

        return $q;
    }

    private function buckets($q, string $column): array
    {
        return (clone $q)->select($column)->selectRaw('COUNT(*) AS total')->groupBy($column)->orderBy($column)->get()->map(fn ($r) => ['key' => $r->{$column}, 'total' => (int) $r->total])->all();
    }

    public function build(User $actor, array $input): array
    {
        $f = $this->filters($actor, $input);
        $requests = $this->requests($f);
        $total = (clone $requests)->count();
        $completed = (clone $requests)->where('r.status', 'completed')->count();
        $closures = $this->requests($f, 'completed_at')->where('r.status', 'completed');
        $seconds = DB::getDriverName() === 'mysql' ? 'TIMESTAMPDIFF(SECOND,r.created_at,r.completed_at)' : '(julianday(r.completed_at)-julianday(r.created_at))*86400';
        $average = (clone $closures)->whereColumn('r.completed_at', '>=', 'r.created_at')->selectRaw('AVG('.$seconds.') AS duration')->value('duration');
        $docs = $this->records('citizen_documents', 'issued_recorded_at', 'issued_by', 'citizen_service_request_id', $f)->whereNotNull('citizen_service_request_id');
        $reviews = $this->records('review_decisions', 'decided_at', 'actor_id', 'citizen_service_request_id', $f);
        $attempts = $this->records('integration_attempts', 'started_at', 'actor_id', 'citizen_service_request_id', $f);
        $payments = DB::table('manual_payments as p')->join('request_charges as c', 'c.id', '=', 'p.request_charge_id')->whereBetween('p.paid_at', [$f['from_at']->toDateString(), $f['to_at']->toDateString()]);
        if ($f['scope_department'] || ! empty($f['employee_id'])) {
            $payments->whereIn('p.recorded_by', $this->members($f));
        }
        $payments->whereExists(function ($q) use ($f) {
            $q->selectRaw('1')->from('citizen_service_requests as rf')->whereColumn('rf.id', 'c.citizen_service_request_id')->when($f['service_id'] ?? null, fn ($q, $id) => $q->where('rf.government_service_id', $id))->when($f['status'] ?? null, fn ($q, $s) => $q->where('rf.status', $s));
        });
        $valid = (clone $payments)->where('c.status', 'paid')->whereColumn('p.amount', 'c.amount')->whereColumn('p.currency', 'c.currency');
        $sum = DB::getDriverName() === 'mysql' ? 'CAST(SUM(p.amount) AS DECIMAL(30,2))' : "printf('%.2f',SUM(p.amount))";
        $money = (clone $valid)->select('p.currency')->selectRaw('COUNT(*) AS total, '.$sum.' AS amount')->groupBy('p.currency')->orderBy('p.currency')->get()->map(fn ($r) => ['currency' => $r->currency, 'total' => (int) $r->total, 'amount' => (string) $r->amount])->all();
        $charges = $this->records('request_charges', 'created_at', 'user_id', 'citizen_service_request_id', $f);
        // Charges have no recorder: departmental scope follows the request's current assignee.
        if ($f['scope_department'] || ! empty($f['employee_id'])) {
            $charges = DB::table('request_charges')->whereBetween('created_at', [$f['from_at'], $f['to_at']])->whereIn('citizen_service_request_id', $this->requests($f, null)->select('r.id'));
        }
        $staffPage = $this->staffQuery($f, $reviews)->paginate(20, ['*'], 'page', (int) ($f['page'] ?? 1))->withQueryString();
        $staff = $staffPage->getCollection()->map(fn ($r) => ['employee_id' => $r->employee_id, 'actions' => (int) $r->actions, 'requests' => (int) $r->requests, 'approvals' => (int) $r->approvals, 'assigned_open' => (int) $r->assigned_open, 'completed' => (int) $r->completed, 'average_seconds' => $r->average_seconds === null ? null : round((float) $r->average_seconds, 2)])->all();
        $deptRequests = (clone $requests)->join('users as du', 'du.id', '=', 'r.assigned_to')->select('du.department_id')->selectRaw('COUNT(*) AS total')->groupBy('du.department_id')->pluck('total', 'department_id');
        $deptReviews = (clone $reviews)->join('users as vu', 'vu.id', '=', 'review_decisions.actor_id')->select('vu.department_id')->selectRaw('COUNT(*) AS total')->groupBy('vu.department_id')->pluck('total', 'department_id');
        $deptDocs = (clone $docs)->join('users as iu', 'iu.id', '=', 'citizen_documents.issued_by')->select('iu.department_id')->selectRaw('COUNT(*) AS total')->groupBy('iu.department_id')->pluck('total', 'department_id');
        $departmentActivity = DB::table('departments')->when($f['scope_department'], fn ($q, $id) => $q->where('id', $id))->orderBy('id')->paginate(20, ['*'], 'department_page')->withQueryString();
        $departmentRows = $departmentActivity->getCollection()->map(fn ($d) => ['department_id' => $d->id, 'name' => $d->name, 'is_active' => (bool) $d->is_active, 'requests' => (int) ($deptRequests[$d->id] ?? 0), 'review_actions' => (int) ($deptReviews[$d->id] ?? 0), 'documents' => (int) ($deptDocs[$d->id] ?? 0)])->all();
        $success = (clone $attempts)->where('status', 'verified')->where('origin', 'configured_provider')->count();
        $attemptTotal = (clone $attempts)->count();

        return ['filters' => $f, 'total_requests' => $total, 'request_statuses' => $this->buckets($requests, 'status'), 'request_services' => $this->buckets($requests, 'service_code'),
            'cohort_completed' => $completed, 'completion_rate' => $total ? round(100 * $completed / $total, 2) : null, 'rejection_rate' => $total ? round(100 * (clone $requests)->where('r.status', 'rejected')->count() / $total, 2) : null,
            'closed_in_period' => (clone $closures)->count(), 'average_processing_seconds' => $average === null ? null : round((float) $average, 2), 'overdue' => null,
            'open_requests' => (clone $requests)->whereNotIn('r.status', ['completed', 'rejected', 'cancelled'])->count(),
            'documents' => (clone $docs)->count(), 'document_statuses' => $this->buckets($docs, 'status'), 'document_services' => $this->buckets($docs, 'government_service_id'),
            'employees' => $staff, 'staff_pagination' => $staffPage, 'department_activity' => $departmentRows, 'department_pagination' => $departmentActivity, 'payments' => $money, 'payment_count' => (clone $valid)->count(), 'payment_reconciliation' => (clone $payments)->count() - (clone $valid)->count(), 'charge_statuses' => $this->buckets($charges, 'status'),
            'verification_total' => $attemptTotal, 'verification_statuses' => $this->buckets($attempts, 'status'), 'verification_kinds' => $this->buckets($attempts, 'kind'), 'verification_providers' => $this->buckets($attempts, 'provider'), 'verification_origins' => $this->buckets($attempts, 'origin'), 'verification_rate' => $attemptTotal ? round(100 * $success / $attemptTotal, 2) : null,
            'active_employees' => User::where('role', 'employee')->where('is_active', true)->when($f['scope_department'], fn ($q, $id) => $q->where('department_id', $id))->count(),
            'departments' => DB::table('departments')->where('is_active', true)->when($f['scope_department'], fn ($q, $id) => $q->where('id', $id))->count()];
    }

    public static function csvCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[\s\x00-\x20]*[=+@\-]/u', $value) ? "'".$value : $value;
    }

    public function departmentQuery(array $f)
    {
        $requests = $this->requests($f)->join('users as u', 'u.id', '=', 'r.assigned_to')->select('u.department_id')->selectRaw('COUNT(*) AS requests')->groupBy('u.department_id');
        $reviews = $this->records('review_decisions', 'decided_at', 'actor_id', 'citizen_service_request_id', $f)->join('users as u', 'u.id', '=', 'review_decisions.actor_id')->select('u.department_id')->selectRaw('COUNT(*) AS review_actions')->groupBy('u.department_id');
        $docs = $this->records('citizen_documents', 'issued_recorded_at', 'issued_by', 'citizen_service_request_id', $f)->whereNotNull('citizen_service_request_id')->join('users as u', 'u.id', '=', 'citizen_documents.issued_by')->select('u.department_id')->selectRaw('COUNT(*) AS documents')->groupBy('u.department_id');

        return DB::table('departments as d')->when($f['scope_department'], fn ($q, $id) => $q->where('d.id', $id))->leftJoinSub($requests, 'rq', 'rq.department_id', '=', 'd.id')->leftJoinSub($reviews, 'rv', 'rv.department_id', '=', 'd.id')->leftJoinSub($docs, 'dc', 'dc.department_id', '=', 'd.id')->selectRaw('d.id AS department_id,d.name,d.is_active,COALESCE(rq.requests,0) AS requests,COALESCE(rv.review_actions,0) AS review_actions,COALESCE(dc.documents,0) AS documents')->orderBy('d.id');
    }

    public function staffQuery(array $f, $reviews = null)
    {
        $reviews ??= $this->records('review_decisions', 'decided_at', 'actor_id', 'citizen_service_request_id', $f);
        $activity = (clone $reviews)->select('actor_id')->selectRaw("COUNT(*) AS actions, COUNT(DISTINCT citizen_service_request_id) AS requests, SUM(CASE WHEN action='approve' THEN 1 ELSE 0 END) AS approvals")->groupBy('actor_id');
        $work = $this->requests($f)->whereNotIn('r.status', ['completed', 'cancelled', 'rejected'])->select('r.assigned_to')->selectRaw('COUNT(*) AS assigned_open')->groupBy('r.assigned_to');
        $seconds = DB::getDriverName() === 'mysql' ? 'TIMESTAMPDIFF(SECOND,created_at,completed_at)' : '(julianday(completed_at)-julianday(created_at))*86400';
        $closed = $this->records('citizen_service_requests', 'completed_at', 'completed_by', 'citizen_service_requests.id', $f)->where('status', 'completed')->whereColumn('completed_at', '>=', 'created_at')->select('completed_by')->selectRaw('COUNT(*) AS completed, AVG('.$seconds.') AS average_seconds')->groupBy('completed_by');

        return DB::table('users as staff')->whereIn('staff.id', $this->members($f))->leftJoinSub($activity, 'act', 'act.actor_id', '=', 'staff.id')->leftJoinSub($work, 'work', 'work.assigned_to', '=', 'staff.id')->leftJoinSub($closed, 'closed', 'closed.completed_by', '=', 'staff.id')
            ->where(fn ($q) => $q->whereNotNull('act.actor_id')->orWhereNotNull('work.assigned_to')->orWhereNotNull('closed.completed_by'))
            ->selectRaw('staff.id AS employee_id, COALESCE(act.actions,0) AS actions, COALESCE(act.requests,0) AS requests, COALESCE(act.approvals,0) AS approvals, COALESCE(work.assigned_open,0) AS assigned_open, COALESCE(closed.completed,0) AS completed, closed.average_seconds')->orderBy('staff.id');
    }
}
