<?php

namespace App\Services;

use App\Models\Section;

class SectionService
{
    public function getSections()
    {
        return Section::with(['checklists'])->useFilters()->dynamicPaginate();
    }

    public function createSection(array $data)
    {
        return Section::create($data);
    }

    public function getSectionById($id)
    {
        return Section::with(['checklists'])->find($id);
    }

    public function updateSection(Section $section, array $data)
    {
        $section->update($data);
        return $section;
    }

    public function deleteSection($id) {
        $section = Section::withTrashed()->find($id);
    
        if (!$section) {
            return null;
        }
    
        if ($section->trashed()) {
            $section->restore();
        } else {
            $section->delete();
        }
    
        return $section;
    }
}
    