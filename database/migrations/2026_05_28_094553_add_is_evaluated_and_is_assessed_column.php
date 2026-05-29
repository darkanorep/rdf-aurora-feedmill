<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->boolean('is_evaluated')->nullable()->default(null)->after('is_approved');
            $table->boolean('is_assessed')->nullable()->default(null)->after('is_evaluated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            //
        });
    }
};
