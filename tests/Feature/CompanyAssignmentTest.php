<?php

use App\Models\Assignment;
use App\Models\Organization;
use App\Models\User;

function companyUser(): User
{
    $u = User::factory()->create(['account_type' => 'company']);
    $u->assignRole('company_contact');

    return $u;
}

function orgWithOwner(User $owner): Organization
{
    $org = Organization::create(['name' => 'ACME', 'sector' => 'IT']);
    $org->users()->attach($owner->id, ['role_in_org' => 'owner', 'is_primary' => true]);

    return $org;
}

it('lets a company user register an organization and become primary contact', function () {
    $user = companyUser();

    $this->actingAs($user)->post('/organizations', ['name' => 'ACME s.r.o.', 'ico' => '12345678'])
        ->assertRedirect();

    $org = Organization::first();
    expect($org->name)->toBe('ACME s.r.o.')
        ->and($org->users()->where('users.id', $user->id)->wherePivot('is_primary', true)->exists())->toBeTrue();
});

it('lets a company member create an assignment under their organization', function () {
    $user = companyUser();
    $org = orgWithOwner($user);

    $this->actingAs($user)->post('/assignments', [
        'organization_id' => $org->id, 'title' => 'CRM systém', 'budget' => 5000,
    ])->assertRedirect();

    $a = Assignment::first();
    expect($a->title)->toBe('CRM systém')
        ->and($a->status->value)->toBe('draft')
        ->and($a->organization_id)->toBe($org->id);
});

it('forbids creating an assignment for an organization you do not belong to', function () {
    $user = companyUser();
    $foreignOrg = orgWithOwner(companyUser());

    $this->actingAs($user)->post('/assignments', [
        'organization_id' => $foreignOrg->id, 'title' => 'X',
    ])->assertForbidden();
});

it('lets a company publish to backlog but not move to matching', function () {
    $user = companyUser();
    $org = orgWithOwner($user);
    $a = $org->assignments()->create(['public_id' => (string) Str::ulid(), 'title' => 'A', 'status' => 'draft', 'created_by' => $user->id]);

    // draft -> backlog allowed
    $this->actingAs($user)->patch("/assignments/{$a->id}/status", ['status' => 'backlog'])
        ->assertSessionHasNoErrors();
    expect($a->fresh()->status->value)->toBe('backlog');

    // backlog -> matching NOT allowed for company
    $this->actingAs($user)->patch("/assignments/{$a->id}/status", ['status' => 'matching']);
    expect($a->fresh()->status->value)->toBe('backlog'); // unchanged
});

it('lets an admin drive the pairing pipeline and see the whole backlog', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $company = companyUser();
    $org = orgWithOwner($company);
    $a = $org->assignments()->create(['public_id' => (string) Str::ulid(), 'title' => 'A', 'status' => 'backlog', 'created_by' => $company->id]);

    $this->actingAs($admin)->patch("/assignments/{$a->id}/status", ['status' => 'matching'])
        ->assertSessionHasNoErrors();
    expect($a->fresh()->status->value)->toBe('matching');

    $this->actingAs($admin)->get('/assignments')->assertOk();
});

it('lets the company add and remove contacts but not the primary', function () {
    $owner = companyUser();
    $org = orgWithOwner($owner);
    $mate = User::factory()->create();

    $this->actingAs($owner)->post("/organizations/{$org->id}/members", ['email' => $mate->email])
        ->assertSessionHasNoErrors();
    expect($org->users()->where('users.id', $mate->id)->exists())->toBeTrue();

    // cannot remove the primary contact
    $this->actingAs($owner)->delete("/organizations/{$org->id}/members/{$owner->id}");
    expect($org->users()->where('users.id', $owner->id)->exists())->toBeTrue();
});

it('forbids a stranger from viewing an organization', function () {
    $org = orgWithOwner(companyUser());
    $stranger = companyUser();

    $this->actingAs($stranger)->get("/organizations/{$org->id}")->assertForbidden();
});
