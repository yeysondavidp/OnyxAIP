<?php

use App\Services\TechnicianUrlService;
use Illuminate\Support\Facades\URL;

it('technician url service generates a valid signed url', function () {
    $service = app(TechnicianUrlService::class);
    $url     = $service->generate(jobId: 1, technicianId: 42, ttlHours: 72);

    expect($url)->toBeString()
        ->toContain('technician_id=42')
        ->toContain('/job/1/'); // {job} is a route path segment, not a query param

    expect(URL::hasValidSignature(request()->create($url)))->toBeTrue();
});

it('tampered signed url returns 403 with invalid link page', function () {
    $service = app(TechnicianUrlService::class);
    $url     = $service->generate(jobId: 1, technicianId: 42, ttlHours: 72);

    // Tamper with a query parameter — signature no longer matches
    $tampered = str_replace('technician_id=42', 'technician_id=99', $url);

    $this->get($tampered)->assertStatus(403);
});

it('expired signed url returns 403', function () {
    $url = URL::temporarySignedRoute(
        'technician.job.overview',
        now()->subSecond(),
        ['job' => 1, 'technician_id' => 42]
    );

    $this->get($url)->assertStatus(403);
});

it('scope matches request checks technician id correctly', function () {
    $service = app(TechnicianUrlService::class);

    $request = request()->create('/job/1/start?technician_id=42');

    expect($service->scopeMatchesRequest($request, 42))->toBeTrue()
        ->and($service->scopeMatchesRequest($request, 99))->toBeFalse();
});
