<?php

namespace App\Services;

use App\Models\MergeForm;

class MergeFormService
{
    /**
     * Create a new class instance.
     */
    private $mergeForm;
    public function __construct(MergeForm $mergeForm)
    {
        $this->mergeForm = $mergeForm;
    }

    public function getMergeForms()
    {
        return $this->mergeForm->all();
    }
    public function mergeForm(array $data) {
        return $this->mergeForm->create($data);
    }

    public function viewMergeFormById($id) {
        return $this->mergeForm->find($id);
    }

    public function updateMergeForm(MergeForm $mergeForm, array $data)
    {
        $mergeForm->update($data);
        return $mergeForm;
    }

    public function deleteMergeForm($id) {
        $mergeForm = $this->mergeForm->withTrashed()->find($id);

        if (!$mergeForm) {
            return null;
        }

        if ($mergeForm->trashed()) {
            $mergeForm->restore();
        } else {
            $mergeForm->delete();
        }

        return $mergeForm;
    }
}
