<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $fkColumns = ['approver_id', 'evaluator_id', 'assessor_id'];

    private array $columns = [
        'approver_id', 'evaluator_id', 'assessor_id',
        'is_approved', 'is_evaluated', 'is_assessed',
        'approve', 'evaluate', 'assess',
    ];

    public function up(): void
    {
        $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', 'responses')
            ->whereIn('COLUMN_NAME', $this->fkColumns)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME')
            ->unique();

        Schema::table('responses', function (Blueprint $table) use ($constraints) {
            foreach ($constraints as $name) {
                $table->dropForeign($name);
            }
        });

        Schema::table('responses', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                $this->columns,
                fn ($c) => Schema::hasColumn('responses', $c)
            )));
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            foreach ($this->fkColumns as $col) {
                $table->foreignId($col)->nullable()->constrained('users');
            }
            foreach (['is_approved', 'is_evaluated', 'is_assessed'] as $col) {
                $table->boolean($col)->nullable();
            }
            foreach (['approve', 'evaluate', 'assess'] as $col) {
                $table->json($col)->nullable();
            }
        });
    }
};
