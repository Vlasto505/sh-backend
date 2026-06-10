<?php

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuditService;

test('audit service records an event with actor and metadata', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    AuditService::log('user.profile_updated', $user, ['field' => 'email']);

    expect(AuditEvent::count())->toBe(1);

    $event = AuditEvent::first();
    expect($event->actor_id)->toBe($user->id);
    expect($event->action)->toBe('user.profile_updated');
    expect($event->subject_type)->toBe(User::class);
    expect($event->subject_id)->toBe($user->id);
    expect($event->metadata['field'])->toBe('email');
});

test('audit service records an event without actor when unauthenticated', function () {
    AuditService::log('public.contact_form_submitted');

    expect(AuditEvent::count())->toBe(1);
    expect(AuditEvent::first()->actor_id)->toBeNull();
    expect(AuditEvent::first()->action)->toBe('public.contact_form_submitted');
});

test('audit service records event without subject when none given', function () {
    AuditService::log('system.startup');

    $event = AuditEvent::first();
    expect($event->subject_type)->toBeNull();
    expect($event->subject_id)->toBeNull();
});
