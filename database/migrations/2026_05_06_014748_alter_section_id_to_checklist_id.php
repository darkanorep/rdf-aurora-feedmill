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
            $table->dropForeign(['section_id']);
            $table->renameColumn('section_id', 'checklist_id');
            $table->foreign('checklist_id')->references('id')->on('checklists');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropForeign(['checklist_id']);
            $table->renameColumn('checklist_id', 'section_id');
            $table->foreign('section_id')->references('id')->on('sections');
        });
    }
};
