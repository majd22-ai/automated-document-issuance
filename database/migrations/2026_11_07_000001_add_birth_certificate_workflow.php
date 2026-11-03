<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('government_services')->where('code', 'birth-certificate-first-issue')->exists()) {
            DB::table('government_services')->insert([
                'code' => 'birth-certificate-first-issue',
                'name' => 'إصدار شهادة ميلاد',
                'document_type' => 'birth_certificate',
                'operation' => 'issue',
                'description' => 'مسار تجريبي لتقديم ومراجعة بيانات الميلاد وإصدار نموذج داخلي غير رسمي.',
                'required_documents' => json_encode(['إشعار أو بلاغ واقعة الميلاد', 'إثبات هوية الأب', 'إثبات هوية الأم'], JSON_THROW_ON_ERROR),
                'required_fields' => json_encode(['name', 'username'], JSON_THROW_ON_ERROR),
                'application_fields' => json_encode([
                    ['key' => 'child_name', 'label' => 'اسم المولود كاملًا', 'type' => 'text', 'required' => true, 'max_length' => 200],
                    ['key' => 'child_gender', 'label' => 'جنس المولود', 'type' => 'text', 'required' => true, 'max_length' => 10],
                    ['key' => 'birth_date', 'label' => 'تاريخ الميلاد', 'type' => 'date', 'required' => true],
                    ['key' => 'birth_place', 'label' => 'مكان الميلاد', 'type' => 'text', 'required' => true, 'max_length' => 200],
                    ['key' => 'father_name', 'label' => 'اسم الأب كاملًا', 'type' => 'text', 'required' => true, 'max_length' => 200],
                    ['key' => 'father_national_id', 'label' => 'رقم هوية الأب', 'type' => 'text', 'required' => true, 'max_length' => 50],
                    ['key' => 'mother_name', 'label' => 'اسم الأم كاملًا', 'type' => 'text', 'required' => true, 'max_length' => 200],
                    ['key' => 'mother_national_id', 'label' => 'رقم هوية الأم', 'type' => 'text', 'required' => true, 'max_length' => 50],
                    ['key' => 'birth_record_number', 'label' => 'رقم واقعة الميلاد', 'type' => 'text', 'required' => true, 'max_length' => 100],
                    ['key' => 'hospital_name', 'label' => 'المستشفى أو المركز الصحي', 'type' => 'text', 'required' => false, 'max_length' => 200],
                    ['key' => 'birth_record_date', 'label' => 'تاريخ تسجيل واقعة الميلاد', 'type' => 'date', 'required' => false],
                ], JSON_THROW_ON_ERROR),
                'verification_rules' => json_encode(['documents' => [
                    ['code' => 'birth_notice', 'name' => 'إشعار أو بلاغ واقعة الميلاد', 'kind' => 'document', 'allowed_mime_types' => ['image/jpeg', 'image/png', 'application/pdf'], 'requires_review' => true],
                    ['code' => 'father_identity', 'name' => 'إثبات هوية الأب', 'kind' => 'document', 'allowed_mime_types' => ['image/jpeg', 'image/png', 'application/pdf'], 'requires_review' => true],
                    ['code' => 'mother_identity', 'name' => 'إثبات هوية الأم', 'kind' => 'document', 'allowed_mime_types' => ['image/jpeg', 'image/png', 'application/pdf'], 'requires_review' => true],
                ], 'restriction_source' => 'manual'], JSON_THROW_ON_ERROR),
                'is_active' => false,
                'rules_approved' => false,
                'source_reference' => null,
                'rules_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('birth_certificate_drafts')) {
            Schema::create('birth_certificate_drafts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('government_service_id')->constrained()->restrictOnDelete();
                $table->longText('payload');
                $table->timestamps();
                $table->unique(['user_id', 'government_service_id'], 'birth_draft_owner_service');
            });
        }

        if (! Schema::hasTable('birth_certificates')) {
            Schema::create('birth_certificates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('citizen_document_id')->unique()->constrained()->restrictOnDelete();
                $table->foreignId('citizen_service_request_id')->unique()->constrained()->restrictOnDelete();
                $table->string('certificate_number', 100)->unique();
                $table->text('verification_token');
                $table->string('verification_token_hash', 64)->unique();
                $table->longText('certificate_data');
                $table->string('pdf_path')->unique();
                $table->string('pdf_sha256', 64);
                $table->unsignedBigInteger('pdf_byte_size');
                $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
                $table->dateTime('issued_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Retain requests, drafts, issued certificates and verification history.
    }
};
