<?php
namespace App\Services\WorkStudio\Services;


class ResourceGroupAccessService
{
    public function getRegionsForRole(string $role = 'planner'): array | string
    {
        $resGrps = [];

        if ($role === 'admin' || $role === 'sudo') {
            $resGrps = config('workstudio_resource_groups.all');
        } else if ($role !== '') {
            $resGrps = config("workstudio_resource_groups.roles.{$role}");
        } else if ($role === '*') {
            $resGrps = config('workstudio_resource_groups.all');
        } else {
            $resGrps = config('workstudio_resource_groups.default');
        }


        return $resGrps;
    }

    public function getRegionsForUser(string $username, string $role): array | string
    {
        // Check user-specific override first
        $userOverride = [];
        if ($username !== '') {
            $userOverride = config("workstudio_resource_groups.users.{$username}", null);
        }

        if ($userOverride !== null) {
            return $userOverride;
        }

        return $this->getRegionsForRole($role);
    }
}
