<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', fn (Blueprint $t) => $t->text('private_reason')->nullable());
    }

    public function down(): void
    {
        throw new RuntimeException('Audit history must be retained.');
    }
};
