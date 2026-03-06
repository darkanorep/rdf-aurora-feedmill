<?php

namespace App\Services;

use App\Models\Checklist;

class ChecklistService
{
    public function getChecklists()
    {
        return Checklist::with(['section'])->useFilters()->dynamicPaginate();
    }

    public function createChecklist(array $data)
    {
        return Checklist::create($data);
    }

    public function getChecklistById($id)
    {
        return Checklist::with(['section'])->find($id);
    }

    public function updateChecklist(Checklist $checklist, array $data)
    {
        $checklist->update($data);
        return $checklist;
    }

    public function deleteChecklist($id) {
        $checklist = Checklist::withTrashed()->find($id);
    
        if (!$checklist) {
            return null;
        }
    
        if ($checklist->trashed()) {
            $checklist->restore();
        } else {
            $checklist->delete();
        }
    
        return $checklist;
    }
}
