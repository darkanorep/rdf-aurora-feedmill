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
            $table->foreignId('evaluator_id')->nullable()->references('id')->on('users');
            $table->json('evaluate')->nullable();
            $table->foreignId('assessor_id')->nullable()->references('id')->on('users');
            $table->json('assess')->nullable();
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
