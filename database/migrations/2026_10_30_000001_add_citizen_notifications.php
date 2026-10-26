<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->string('type');
                $t->morphs('notifiable');
                $t->text('data');
                $t->timestamp('read_at')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasColumn('notifications', 'event_key')) {
            Schema::table('notifications', function (Blueprint $t) {
                $t->string('event_key', 64)->nullable()->unique();
                $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
                $t->foreignId('request_id')->nullable()->constrained('citizen_service_requests')->nullOnDelete();
                $t->foreignId('document_id')->nullable()->constrained('citizen_documents')->nullOnDelete();
                $t->string('mail_status', 20)->default('disabled');
                $t->string('mail_recipient_hash', 64)->nullable();
                $t->unsignedTinyInteger('mail_attempts')->default(0);
                $t->dateTime('mail_queued_at')->nullable();
                $t->dateTime('mail_sent_at')->nullable();
                $t->index(['mail_status', 'created_at'], 'notification_mail_pending');
                $t->index(['user_id', 'read_at', 'created_at'], 'notification_inbox_reads');
            });
        }
        if (! Schema::hasIndex('citizen_documents', 'document_expiry_alert_lookup')) {
            Schema::table('citizen_documents', fn (Blueprint $t) => $t->index(['status', 'expires_at'], 'document_expiry_alert_lookup'));
        }
    }

    public function down(): void
    { /* Preserve inbox and delivery history. */
    }
};
