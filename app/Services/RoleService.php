<?php

namespace App\Services;

use App\Models\Role;

class RoleService
{
    public function getRoles()
    {
        return Role::useFilters()->dynamicPaginate();
    }

    public function createRole(array $data)
    {
        return Role::create($data);
    }

    public function getRoleById($id)
    {
        return Role::find($id);
    }

    public function updateRole(Role $role, array $data)
    {
        $role->update($data);
        return $role;
    }

    public function deleteRole($id) {
        $role = Role::withTrashed()->find($id);
    
        if (!$role) {
            return null;
        }
    
        if ($role->trashed()) {
            $role->restore();
        } else {
            $role->delete();
        }
    
        return $role;
    }
}
