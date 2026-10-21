<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('government_services')) {
            Schema::create('government_services', function (Blueprint $table) {
                $table->id();
                $table->string('code', 100)->unique();
                $table->string('name', 200);
                $table->string('document_type', 30);
                $table->string('operation', 30);
                $table->text('description')->nullable();
                $table->json('conditions')->nullable();
                $table->json('required_documents')->nullable();
                $table->json('required_fields')->nullable();
                $table->decimal('fee', 12, 2)->nullable();
                $table->string('currency', 20)->nullable();
                $table->string('estimated_duration')->nullable();
                $table->boolean('is_active')->default(false)->index();
                $table->boolean('rules_approved')->default(false);
                $table->string('source_reference')->nullable();
                $table->unsignedInteger('rules_version')->default(1);
                $table->unsignedSmallInteger('minimum_age')->nullable();
                $table->boolean('requires_driving_clearance')->default(false);
                $table->json('renewal_statuses')->nullable();
                $table->unsignedInteger('renewal_window_days')->nullable();
                $table->json('terminal_request_statuses')->nullable();
                $table->timestamps();
            });
        }
        // Known service labels only. No unofficial rules, fees or availability are imported.
        $catalog = [
            ['identity-first-issue', 'إصدار البطاقة الشخصية', 'identity', 'issue'],
            ['identity-renewal', 'تجديد البطاقة الشخصية', 'identity', 'renewal'],
            ['identity-lost', 'بدل فاقد البطاقة الشخصية', 'identity', 'replacement'],
            ['passport-first-issue', 'إصدار جواز السفر', 'passport', 'issue'],
            ['passport-renewal', 'تجديد جواز السفر', 'passport', 'renewal'],
            ['license-first-issue', 'إصدار رخصة القيادة', 'driving_license', 'issue'],
            ['license-renewal', 'تجديد رخصة القيادة', 'driving_license', 'renewal'],
        ];
        foreach ($catalog as [$code, $name, $type, $operation]) {
            if (! DB::table('government_services')->where('code', $code)->exists()) {
                DB::table('government_services')->insert(['code' => $code, 'name' => $name, 'document_type' => $type, 'operation' => $operation, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        if (! Schema::hasTable('citizen_service_assessments')) {
            Schema::create('citizen_service_assessments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('government_service_id')->constrained()->restrictOnDelete();
                $table->unsignedInteger('rules_version');
                $table->boolean('identity_verified')->default(false);
                $table->boolean('conditions_met')->default(false);
                $table->boolean('age_requirement_met')->default(false);
                $table->boolean('driving_requirements_met')->default(false);
                $table->boolean('is_blocked')->default(false);
                $table->string('block_reason')->nullable();
                $table->string('verification_reference');
                $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('reviewed_at');
                $table->timestamps();
                $table->unique(['user_id', 'government_service_id'], 'citizen_service_assessment_owner');
            });
        }
        if (! Schema::hasColumn('citizen_documents', 'document_type_code')) {
            Schema::table('citizen_documents', fn (Blueprint $table) => $table->string('document_type_code', 30)->nullable()->index());
        }
        if (! Schema::hasColumn('citizen_service_requests', 'government_service_id')) {
            Schema::table('citizen_service_requests', function (Blueprint $table) {
                $table->foreignId('government_service_id')->nullable()->constrained()->restrictOnDelete();
                $table->string('service_name', 200)->nullable();
                $table->decimal('service_fee', 12, 2)->nullable();
                $table->string('service_currency', 20)->nullable();
                $table->unsignedInteger('service_rules_version')->nullable();
            });
        }
        $aliases = ['passport-first-issue' => 'passports-first-issue', 'passport-renewal' => 'passports-renewal', 'license-first-issue' => 'driving-first-issue', 'license-renewal' => 'driving-renewal'];
        foreach (DB::table('government_services')->get(['id', 'code']) as $service) {
            // Historical fees/conditions are unknown: never backfill them from today's catalog.
            DB::table('citizen_service_requests')->whereNull('government_service_id')
                ->whereIn('service_code', [$service->code, $aliases[$service->code] ?? $service->code])
                ->update(['government_service_id' => $service->id]);
        }
    }

    public function down(): void
    {
        // Intentionally retain the catalog, approvals and historical request relationships.
    }
};
