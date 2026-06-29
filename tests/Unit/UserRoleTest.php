<?php

use App\Enums\UserRole;

it('pm role helpers are correct', function () {
    expect(UserRole::Pm->isPm())->toBeTrue()
        ->and(UserRole::Pm->isTechnician())->toBeFalse()
        ->and(UserRole::Pm->isClientUser())->toBeFalse()
        ->and(UserRole::Pm->canAccessPmPortal())->toBeTrue()
        ->and(UserRole::Pm->canAccessTechnicianFlow())->toBeFalse();
});

it('technician role helpers are correct', function () {
    expect(UserRole::Technician->isTechnician())->toBeTrue()
        ->and(UserRole::Technician->isPm())->toBeFalse()
        ->and(UserRole::Technician->canAccessPmPortal())->toBeFalse()
        ->and(UserRole::Technician->canAccessTechnicianFlow())->toBeTrue();
});

it('client_user role helpers are correct', function () {
    expect(UserRole::ClientUser->isClientUser())->toBeTrue()
        ->and(UserRole::ClientUser->isPm())->toBeFalse()
        ->and(UserRole::ClientUser->canAccessPmPortal())->toBeFalse()
        ->and(UserRole::ClientUser->canAccessTechnicianFlow())->toBeFalse();
});

it('all roles have labels', function () {
    foreach (UserRole::cases() as $role) {
        expect($role->label())->toBeString()->not->toBeEmpty();
    }
});

it('pm role is the enum default for factory', function () {
    expect(UserRole::Pm->value)->toBe('pm');
});
