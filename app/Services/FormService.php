<?php

namespace App\Services;

use App\Models\Form;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FormService
{
    protected $form;

    public function __construct(Form $form)
    {
        $this->form = $form;
    }

    public function getForms() {
        $forms = $this->form->useFilters()->get();

        $grouped = $forms->groupBy('checklist_id')->map(function ($checklistForms, $checklistId) {
            return [
                'checklist_id' => (int) $checklistId,
                'forms' => $checklistForms->groupBy('name')->map(function ($items, $name) {
                    return [
                        'name' => $name,
                        'item' => $items->map(function ($item) {
                            return [
                                'name' => $item->item,
                                'type' => $item->type,
                            ];
                        })->values(),
                    ];
                })->values(),
            ];
        })->values();

        $page = request()->input('page', 1);
        $perPage = request()->input('per_page', 15);
        $total = $grouped->count();

        $items = $grouped->forPage($page, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    public function createForm($data)
    {
        $records = $this->buildFormRecords($data['checklist_id'], $data['forms']);
        $this->form->insert($records);
    }

    public function getFormByChecklistId($checkListId)
    {
        $forms = $this->form->where('checklist_id', $checkListId)->get();

        $grouped = $forms->groupBy('name')->map(function ($items, $name) {
            return [
                'name' => $name,
                'item' => $items->map(function ($item) {
                    return [
                        'name' => $item->item,
                        'type' => $item->type,
                    ];
                })->values(),
            ];
        })->values();

        return [
            'checklist_id' => (int) $checkListId,
            'forms' => $grouped,
        ];
    }

    public function updateByChecklistId($data, $checklistId)
    {
        DB::transaction(function () use ($data, $checklistId) {
            $this->form->where('checklist_id', $checklistId)->delete();
            
            $records = $this->buildFormRecords($checklistId, $data['forms']);
            $this->form->insert($records);
        });
    }

    public function deleteByChecklistId($checklistId)
    {
        $this->form->where('checklist_id', $checklistId)->delete();
    }

    private function buildFormRecords($checklistId, array $forms): array
    {
        $records = [];
        $now = now();

        foreach ($forms as $form) {
            foreach ($form['item'] as $item) {
                $records[] = [
                    'checklist_id' => $checklistId,
                    'name' => $form['name'],
                    'item' => $item['name'],
                    'type' => $item['type'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $records;
    }
}
