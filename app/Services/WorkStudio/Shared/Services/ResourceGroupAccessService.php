<?php

namespace App\Services\WorkStudio\Shared\Services;

class ResourceGroupAccessService
{
    public function getRegionsForRole(string $role = 'planner'): array|string
    {
        $resGrps = [];

        if ($role === 'admin' || $role === 'sudo') {
            $resGrps = config('workstudio_resource_groups.all');
        } elseif ($role !== '') {
            $resGrps = config('workstudio_resource_groups.roles.'.$role);
        } elseif ($role === '*') {
            $resGrps = config('workstudio_resource_groups.all');
        } else {
            $resGrps = config('workstudio_resource_groups.default');
        }

        return $resGrps;
    }

    /**
     * Resolve VEGJOB.REGION values from WS group memberships.
     *
     * 1. Strips domain prefix from each group (e.g., ASPLUNDH\VEG_PLANNERS → VEG_PLANNERS)
     * 2. Checks group_to_region_map config for explicit mappings
     * 3. Checks if the group name directly matches a known region
     * 4. Falls back to planner role regions if nothing resolved
     *
     * @param  array<int, string>  $wsGroups  Raw groups from GETUSERDETAILS (e.g., ['WorkStudio\\Everyone', 'ASPLUNDH\\VEG_PLANNERS'])
     * @return array<int, string> Resolved VEGJOB.REGION values
     */
    public function resolveRegionsFromGroups(array $wsGroups): array
    {
        $regionMap = config('workstudio_resource_groups.group_to_region_map', []);
        $allKnownRegions = config('workstudio_resource_groups.all', []);
        $resolved = [];

        foreach ($wsGroups as $group) {
            // Strip domain prefix: "ASPLUNDH\VEG_PLANNERS" → "VEG_PLANNERS"
            $groupName = str_contains($group, '\\')
                ? substr($group, strrpos($group, '\\') + 1)
                : $group;

            // Check explicit mapping first
            if (isset($regionMap[$groupName])) {
                $resolved = array_merge($resolved, $regionMap[$groupName]);

                continue;
            }

            // Check if group name directly matches a known region
            if (in_array($groupName, $allKnownRegions, true)) {
                $resolved[] = $groupName;
            }
        }

        $resolved = array_unique($resolved);

        // Fallback: if no regions resolved, use planner defaults
        if (empty($resolved)) {
            return $this->getRegionsForRole('planner');
        }

        return array_values($resolved);
    }
}
