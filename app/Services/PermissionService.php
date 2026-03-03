<?php

namespace App\Services;

use App\Models\Permission;


class PermissionService
{

    public function getPermissions()
    {
        return Permission::useFilters()->dynamicPaginate();
    }


    public function createPermission(array $data)
    {
        return Permission::create($data);
    }

    public function getPermissionById($id)
    {
        return Permission::find($id);
    }

    public function updatePermission(Permission $permission, array $data)
    {
        $permission->update($data);
        return $permission;
    }

    public function deletePermission($id) {
        $permission = Permission::withTrashed()->find($id);
    
        if (!$permission) {
            return null;
        }
    
        if ($permission->trashed()) {
            $permission->restore();
        } else {
            $permission->delete();
        }
    
        return $permission;
    }
}
