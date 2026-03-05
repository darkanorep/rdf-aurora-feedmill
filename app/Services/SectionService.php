<?php

namespace App\Services;

use App\Models\Sections;

class SectionService
{
    public function getSections()
    {
        return Sections::useFilters()->dynamicPaginate();
    }

    public function createSection(array $data)
    {
        return Sections::create($data);
    }

    public function getSectionById($id)
    {
        return Sections::find($id);
    }

    public function updateSection(Sections $section, array $data)
    {
        $section->update($data);
        return $section;
    }

    public function deleteSection($id) {
        $section = Sections::withTrashed()->find($id);
    
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
    