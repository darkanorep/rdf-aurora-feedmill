<?php

namespace Database\Seeders;

use App\Models\ApprovalStepType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ApprovalStepTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
                     ['key' => 'evaluate', 'label' => 'Evaluation', 'status_label' => 'For Acknowledgement'],
                     ['key' => 'approve',  'label' => 'Approval',   'status_label' => 'For Approval'],
                     ['key' => 'assess', 'label' => 'Assessment', 'status_label' => 'For Approval'],
                 ] as $row) {
            ApprovalStepType::updateOrCreate(['key' => $row['key']], $row);
        }
    }
}
