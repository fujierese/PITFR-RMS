<?php

namespace App\Policies;

use App\Models\FacilityRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FacilityRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FacilityRequest $facilityRequest): bool
    {
        return $user->id === $facilityRequest->requested_by_id
            || $user->isAdmin()
            || $this->managesAssignedResource($user, $facilityRequest);
    }

    public function create(User $user): bool
    {
        return $user->isRequestee() || $user->isAdmin();
    }

    public function update(User $user, FacilityRequest $facilityRequest): bool
    {
        return $user->id === $facilityRequest->requested_by_id || $user->isAdmin();
    }

    public function delete(User $user, FacilityRequest $facilityRequest): bool
    {
        return $user->id === $facilityRequest->requested_by_id || $user->isAdmin();
    }

    public function restore(User $user, FacilityRequest $facilityRequest): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, FacilityRequest $facilityRequest): bool
    {
        return $user->isAdmin();
    }

    public function approve(User $user, FacilityRequest $facilityRequest): bool
    {
        return $user->isAdmin() || $this->managesAssignedResource($user, $facilityRequest);
    }

    public function reject(User $user, FacilityRequest $facilityRequest): bool
    {
        return $user->isAdmin() || $this->managesAssignedResource($user, $facilityRequest);
    }

    public function returnEquipment(User $user, FacilityRequest $facilityRequest): bool
    {
        return $user->isAdmin()
            || ($user->isCustodianEquipment() && ! empty($facilityRequest->getAssignedEquipmentForCustodian($user->id)));
    }

    private function managesAssignedResource(User $user, FacilityRequest $facilityRequest): bool
    {
        if (! $user->isCustodian()) {
            return false;
        }

        $assignedVenues = $user->venues()->pluck('name')
            ->map(fn (string $name) => mb_strtolower($name));
        $requestedVenues = collect($facilityRequest->getVenueNames())
            ->map(fn (string $name) => mb_strtolower($name));

        return $assignedVenues->intersect($requestedVenues)->isNotEmpty()
            || ! empty($facilityRequest->getAssignedEquipmentForCustodian($user->id));
    }
}
