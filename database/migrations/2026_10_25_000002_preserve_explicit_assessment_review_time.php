<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            // An explicit default removes legacy MariaDB's implicit ON UPDATE on the first TIMESTAMP.
            // Only an authorized assessment action should change the recorded review time.
            Schema::table('citizen_service_assessments', function (Blueprint $table) {
                $table->timestamp('reviewed_at')->useCurrent()->change();
            });
        }
    }

    public function down(): void
    {
        // Do not restore implicit review-time changes or rewrite historical values.
    }
};
