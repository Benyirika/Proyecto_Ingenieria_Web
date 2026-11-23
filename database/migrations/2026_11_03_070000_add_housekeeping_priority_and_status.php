<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('rooms', 'priority')) {
                $table->string('priority')->nullable()->after('cleaning_status');
            }
        });

        DB::table('rooms')->whereNull('priority')->update(['priority' => 'normal']);
        DB::table('rooms')->whereNotIn('cleaning_status', ['limpia', 'sucia', 'en_proceso'])->update(['cleaning_status' => 'limpia']);
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            if (Schema::hasColumn('rooms', 'priority')) {
                $table->dropColumn('priority');
            }
        });
    }
};
