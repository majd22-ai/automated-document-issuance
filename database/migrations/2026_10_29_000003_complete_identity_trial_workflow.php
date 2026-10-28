<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('identity_card_forms', 'revision')) {
            Schema::table('identity_card_forms', function (Blueprint $table) {
                $table->unsignedInteger('revision')->default(1);
                $table->timestamp('arrived_at')->nullable();
                $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('arrival_reference')->nullable();
                $table->timestamp('identity_documents_checked_at')->nullable();
                $table->string('fingerprint_status', 40)->nullable();
                $table->timestamp('correction_opened_at')->nullable();
                $table->foreignId('correction_opened_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->text('correction_reason')->nullable();
                $table->timestamp('resubmitted_at')->nullable();
            });
        }
        if (! Schema::hasColumn('identity_card_attestations', 'form_revision')) {
            Schema::table('identity_card_attestations', function (Blueprint $table) {
                $table->unsignedInteger('form_revision')->default(1);
                $table->string('citizen_name', 200)->nullable();
                $table->foreignId('security_recorded_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('security_recorded_at')->nullable();
                $table->unique(['identity_card_form_id', 'form_revision'], 'identity_attestation_revision_unique');
            });
        }
        if (Schema::hasIndex('identity_card_attestations', 'identity_card_attestations_identity_card_form_id_unique')) {
            Schema::table('identity_card_attestations', fn (Blueprint $table) => $table->dropUnique('identity_card_attestations_identity_card_form_id_unique'));
        }
        DB::table('identity_card_attestations')->whereNull('citizen_name')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $data = json_decode(DB::table('identity_card_forms')->where('id', $row->identity_card_form_id)->value('data') ?? '{}', true);
                DB::table('identity_card_attestations')->where('id', $row->id)->update(['citizen_name' => $data['applicant_name'] ?? null]);
            }
        });
        if (! Schema::hasTable('identity_fingerprint_operations')) {
            Schema::create('identity_fingerprint_operations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('identity_card_form_id')->constrained()->restrictOnDelete();
                $table->unsignedInteger('form_revision');
                $table->string('reference', 40)->unique();
                $table->uuid('operation_token')->unique();
                $table->string('source', 40);
                $table->string('result', 20);
                $table->string('input_fingerprint', 64);
                $table->string('success_key', 64)->nullable()->unique();
                $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('executed_at');
                $table->string('error_detail', 500)->nullable();
                $table->string('evidence_reference', 200)->nullable();
                $table->timestamps();
                $table->index(['identity_card_form_id', 'form_revision', 'result'], 'identity_fingerprint_revision_result');
            });
        }
        if (! Schema::hasTable('identity_administrative_decisions')) {
            Schema::create('identity_administrative_decisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('identity_card_form_id');
                $table->unsignedInteger('form_revision');
                $table->foreignId('identity_fingerprint_operation_id')->nullable();
                $table->string('action', 40);
                $table->unsignedInteger('expected_version');
                $table->uuid('decision_token');
                $table->text('internal_note')->nullable();
                $table->text('citizen_reason')->nullable();
                $table->foreignId('decided_by');
                $table->timestamp('decided_at');
                $table->timestamps();
            });
        }
        // Explicit short names support MySQL's 64-character identifier limit.
        // Guards also allow a failed, partially committed MySQL DDL batch to resume safely.
        foreach ([['identity_card_form_id', 'identity_card_forms', 'identity_admin_form_fk'], ['identity_fingerprint_operation_id', 'identity_fingerprint_operations', 'identity_admin_fingerprint_fk'], ['decided_by', 'users', 'identity_admin_actor_fk']] as [$column, $target, $name]) {
            if (! collect(Schema::getForeignKeys('identity_administrative_decisions'))->contains(fn ($key) => $key['columns'] === [$column])) {
                Schema::table('identity_administrative_decisions', fn (Blueprint $table) => $table->foreign($column, $name)->references('id')->on($target)->restrictOnDelete());
            }
        }
        if (! Schema::hasIndex('identity_administrative_decisions', 'identity_admin_token_unique')) {
            Schema::table('identity_administrative_decisions', fn (Blueprint $table) => $table->unique('decision_token', 'identity_admin_token_unique'));
        }
        if (! Schema::hasIndex('identity_administrative_decisions', 'identity_administrative_stage_unique')) {
            Schema::table('identity_administrative_decisions', fn (Blueprint $table) => $table->unique(['identity_card_form_id', 'expected_version'], 'identity_administrative_stage_unique'));
        }
        // Upgrade approved phase-two forms to the newly introduced attendance step.
        // Never reopen a closed request or invalidate an already recorded success.
        DB::table('identity_card_forms')->where('status', 'pending_administrative_review')
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('identity_card_attestations')
                ->whereColumn('identity_card_attestations.identity_card_form_id', 'identity_card_forms.id')
                ->whereColumn('identity_card_attestations.form_revision', 'identity_card_forms.revision')->where('identity_card_attestations.status', 'approved'))
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('citizen_service_requests')
                ->whereColumn('citizen_service_requests.id', 'identity_card_forms.citizen_service_request_id')->whereIn('citizen_service_requests.status', ['submitted', 'under_review', 'requires_correction', 'accepted', 'approved']))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('identity_fingerprint_operations')
                ->whereColumn('identity_fingerprint_operations.identity_card_form_id', 'identity_card_forms.id')
                ->whereColumn('identity_fingerprint_operations.form_revision', 'identity_card_forms.revision')->where('identity_fingerprint_operations.result', 'success'))
            ->update(['status' => 'pending_fingerprint_attendance', 'version' => DB::raw('version + 1')]);
    }

    public function down(): void
    {
        // Preserve trial history and its evidence; rollback does not delete citizen records.
    }
};
