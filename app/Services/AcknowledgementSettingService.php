<?php

namespace App\Services;

use App\Models\AcknowledgementSetting;

class AcknowledgementSettingService
{
    private $acknowledgementSetting;
    public function __construct(AcknowledgementSetting $acknowledgementSetting)
    {
        $this->acknowledgementSetting = $acknowledgementSetting;
    }

    public function getAcknowledgementSetting() {
        return $this->acknowledgementSetting->useFilters()->dynamicPaginate();
    }
    public function createAcknowledgementSetting(array $data) {
        return $this->acknowledgementSetting->create($data);
    }

    public function getAcknowledgementSettingById($id) {
        return $this->acknowledgementSetting->with(['users', 'hierarchies'])->find($id);
    }

    public function updateAcknowledgementSetting(AcknowledgementSetting $setting, array $data) {
        $setting->update($data);
        return $setting;
    }

    public function deleteAcknowledgementSetting($id) {
        $setting = $this->acknowledgementSetting->withTrashed()->find($id);

        if (!$setting) {
            return null;
        }

        if ($setting->trashed()) {
            $setting->restore();
        } else {
            $setting->delete();
        }

        return $setting;
    }
}
