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

    public function approveVenue(User $user, FacilityRequest $facilityRequest): bool
    {
        return $user->isCustodianVenue()
            && $this->managesAssignedVenue($user, $facilityRequest);
    }

    public function approveEquipment(User $user, FacilityRequest $facilityRequest): bool
    {
        return $user->isCustodianEquipment()
            && ! empty($facilityRequest->getAssignedEquipmentForCustodian($user->id));
    }

    public function rejectVenue(User $user, FacilityRequest $facilityRequest): bool
    {
        return $this->approveVenue($user, $facilityRequest);
    }

    public function rejectEquipment(User $user, FacilityRequest $facilityRequest): bool
    {
        return $this->approveEquipment($user, $facilityRequest);
    }

    public function returnEquipment(User $user, FacilityRequest $facilityRequest): bool
    {
        return $user->isAdmin()
            || ($user->isCustodianEquipment() && ! empty($facilityRequest->getAssignedEquipmentForCustodian($user->id)));
    }

    public function print(User $user, FacilityRequest $facilityRequest): bool
    {
        return $user->isAdmin();
    }

    private function managesAssignedVenue(User $user, FacilityRequest $facilityRequest): bool
    {
        $assignedVenues = $user->venues()->pluck('name')
            ->map(fn (string $name) => mb_strtolower($name));
        $requestedVenues = collect($facilityRequest->getVenueNames())
            ->map(fn (string $name) => mb_strtolower($name));

        return $assignedVenues->intersect($requestedVenues)->isNotEmpty();
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
