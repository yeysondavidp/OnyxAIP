<?php

use App\Ai\AiGateway;
use App\Ai\AiManager;
use App\Ai\AiRequest;
use App\Ai\AiUnavailableException;
use App\Ai\Drivers\FakeAiClient;
use App\Ai\SanitisedImage;
use App\Enums\AiDataCategory;
use App\Enums\AiMode;
use App\Jobs\WriteAuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function useFakeAi(FakeAiClient $fake): FakeAiClient
{
    config(['ai.default' => 'fake']);
    app(AiManager::class)->extend('fake', fn () => $fake);

    return $fake;
}

function labelCrop(): SanitisedImage
{
    $path = tempnam(sys_get_temp_dir(), 'ai-test-');
    $gd   = imagecreatetruecolor(40, 20);
    imagejpeg($gd, $path);

    return SanitisedImage::fromFile($path);
}

function textRequest(Client $client): AiRequest
{
    return new AiRequest($client, AiDataCategory::Text, 'You draft service reports.', 'Summarise PAN-PLY-0001.');
}

it('is disabled when no provider is configured', function () {
    app(AiGateway::class)->complete(textRequest(Client::factory()->create(['ai_mode' => AiMode::PublicPhotos])));
})->throws(AiUnavailableException::class);

it('new clients start with AI off and nothing is sent', function () {
    $fake = useFakeAi(new FakeAiClient);

    expect(fn () => app(AiGateway::class)->complete(textRequest(Client::factory()->create())))
        ->toThrow(AiUnavailableException::class, 'switched off');
    expect($fake->requests)->toBe([]);
});

it('label-crops mode allows label crops but never public-area photos', function () {
    useFakeAi(new FakeAiClient);
    $client = Client::factory()->create(['ai_mode' => AiMode::LabelCrops]);

    $crop = app(AiGateway::class)->complete(new AiRequest($client, AiDataCategory::LabelCrop, 's', 'p', [labelCrop()]));

    expect($crop->provider)->toBe('fake');
    expect(fn () => app(AiGateway::class)->complete(new AiRequest($client, AiDataCategory::PublicPhoto, 's', 'p', [labelCrop()])))
        ->toThrow(AiUnavailableException::class);
});

it('audits the data category and provider, never the content', function () {
    useFakeAi(new FakeAiClient('{"serial":"MIB192600317"}'));
    Bus::fake([WriteAuditLog::class]);
    $this->actingAs(User::factory()->pm()->create());

    app(AiGateway::class)->complete(textRequest(Client::factory()->create(['ai_mode' => AiMode::LabelCrops])));

    Bus::assertDispatched(WriteAuditLog::class, fn (WriteAuditLog $job) => $job->action === 'ai.completed'
        && $job->after['category']                                                      === 'text'
        && $job->after['provider']                                                      === 'fake'
        && ! str_contains(json_encode($job->after), 'PAN-PLY-0001')
        && ! str_contains(json_encode($job->after), 'MIB192600317'));
});

it('turns any driver failure into the one AI exception', function () {
    useFakeAi(new FakeAiClient(fail: true));

    app(AiGateway::class)->complete(textRequest(Client::factory()->create(['ai_mode' => AiMode::LabelCrops])));
})->throws(AiUnavailableException::class, 'not available');

it('limits AI calls per client per minute', function () {
    useFakeAi(new FakeAiClient);
    config(['ai.per_client_per_minute' => 2]);
    $client = Client::factory()->create(['ai_mode' => AiMode::LabelCrops]);

    app(AiGateway::class)->complete(textRequest($client));
    app(AiGateway::class)->complete(textRequest($client));

    app(AiGateway::class)->complete(textRequest($client));
})->throws(AiUnavailableException::class, 'Too many');

it('rejects a text request carrying images', function () {
    new AiRequest(Client::factory()->make(), AiDataCategory::Text, 's', 'p', [labelCrop()]);
})->throws(InvalidArgumentException::class);

it('rejects an image request without images', function () {
    new AiRequest(Client::factory()->make(), AiDataCategory::LabelCrop, 's', 'p');
})->throws(InvalidArgumentException::class);

it('pm can set a client AI mode and an unknown mode is refused', function () {
    $client = Client::factory()->create();
    $pm     = User::factory()->pm()->create();
    $base   = ['client_name' => $client->client_name, 'client_code' => $client->client_code];

    $this->actingAs($pm)->put(route('clients.update', $client), $base + ['ai_mode' => 'everything'])->assertSessionHasErrors('ai_mode');
    $this->actingAs($pm)->put(route('clients.update', $client), $base + ['ai_mode' => 'label_crops'])->assertSessionHasNoErrors();

    expect($client->fresh()->ai_mode)->toBe(AiMode::LabelCrops);
});
