<?php

namespace App\Services;

use App\Models\Form;

class FormService
{
    public function getForms() {
        return Form::useFilters()->dynamicPaginate();
    }

    public function createForm($request)
    {
        $data = $request->all();
        
        foreach ($data['forms'] as $form) {
            foreach ($form['item'] as $item) {
                Form::create([
                    'checklist_id' => $data['checklist_id'],
                    'name' => $form['name'],
                    'item' => $item['name'],
                    'type' => $item['type'] ?? null,
                ]);
            }
        }
    }
}
