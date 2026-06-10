<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Organization;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Organization::class);

        $organizations = Organization::withCount('users')
            ->orderBy('name')
            ->paginate(20);

        return $this->success($organizations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'ico'         => ['nullable', 'string', 'max:20', 'unique:organizations,ico'],
            'website'     => ['nullable', 'url', 'max:255'],
            'sector'      => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'address'     => ['nullable', 'string', 'max:255'],
            'city'        => ['nullable', 'string', 'max:100'],
        ]);

        $organization = Organization::create($validated);

        $organization->users()->attach($request->user()->id, [
            'role_in_org' => 'contact',
            'is_primary'  => true,
        ]);

        AuditService::log('organization.created', $organization);

        return $this->success($organization, 201);
    }

    public function show(Organization $organization): JsonResponse
    {
        return $this->success($organization->load(['users:id,name,email']));
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'website'     => ['nullable', 'url', 'max:255'],
            'sector'      => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'address'     => ['nullable', 'string', 'max:255'],
            'city'        => ['nullable', 'string', 'max:100'],
        ]);

        $organization->update($validated);

        AuditService::log('organization.updated', $organization);

        return $this->success($organization);
    }
}
