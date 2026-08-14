<?php

declare(strict_types=1);

use App\Enums\ApiProvider;
use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardOutput;
use App\Models\Group;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function fakeDashboardIconsIndex(): void
{
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response([
            'sonarr' => ['base' => 'svg', 'aliases' => []],
        ], 200),
    ]);
}

beforeEach(fn () => Cache::flush());

it('creates a link card', function () {
    Livewire::test('card-manager')
        ->set('name', 'Router')
        ->set('type', 'link')
        ->set('url', 'http://192.168.1.1')
        ->call('save');

    $card = Card::where('name', 'Router')->sole();

    expect($card->type)->toBe(CardType::Link)
        ->and($card->url)->toBe('http://192.168.1.1');
});

it('creates an output card with its command', function () {
    Livewire::test('card-manager')
        ->set('name', 'Disk')
        ->set('type', 'output')
        ->set('command', 'df -h')
        ->call('save');

    $card = Card::where('name', 'Disk')->sole();

    expect($card->type)->toBe(CardType::Output)
        ->and($card->output->command)->toBe('df -h');
});

it('creates an output card with an auto-refresh interval in minutes', function () {
    Livewire::test('card-manager')
        ->set('name', 'Disk')
        ->set('type', 'output')
        ->set('command', 'df -h')
        ->set('refreshAmount', 5)
        ->set('refreshUnit', 'minutes')
        ->call('save');

    $card = Card::where('name', 'Disk')->sole();

    expect($card->output->refresh_interval_seconds)->toBe(300);
});

it('creates an output card with an auto-refresh interval in seconds', function () {
    Livewire::test('card-manager')
        ->set('name', 'Disk')
        ->set('type', 'output')
        ->set('command', 'df -h')
        ->set('refreshAmount', 30)
        ->set('refreshUnit', 'seconds')
        ->call('save');

    $card = Card::where('name', 'Disk')->sole();

    expect($card->output->refresh_interval_seconds)->toBe(30);
});

it('leaves auto-refresh off for an output card when no interval is given', function () {
    Livewire::test('card-manager')
        ->set('name', 'Disk')
        ->set('type', 'output')
        ->set('command', 'df -h')
        ->call('save');

    $card = Card::where('name', 'Disk')->sole();

    expect($card->output->refresh_interval_seconds)->toBeNull();
});

it('rejects a non-positive auto-refresh interval for an output card', function () {
    Livewire::test('card-manager')
        ->set('name', 'Disk')
        ->set('type', 'output')
        ->set('command', 'df -h')
        ->set('refreshAmount', 0)
        ->call('save')
        ->assertHasErrors(['refreshAmount' => 'min']);

    expect(Card::where('name', 'Disk')->exists())->toBeFalse();
});

it('rejects a sub-5-second auto-refresh interval for an output card', function () {
    Livewire::test('card-manager')
        ->set('name', 'Disk')
        ->set('type', 'output')
        ->set('command', 'df -h')
        ->set('refreshAmount', 1)
        ->set('refreshUnit', 'seconds')
        ->call('save')
        ->assertHasErrors(['refreshAmount']);

    expect(Card::where('name', 'Disk')->exists())->toBeFalse();
});

it('allows a 5-second auto-refresh interval for an output card', function () {
    Livewire::test('card-manager')
        ->set('name', 'Disk')
        ->set('type', 'output')
        ->set('command', 'df -h')
        ->set('refreshAmount', 5)
        ->set('refreshUnit', 'seconds')
        ->call('save')
        ->assertHasNoErrors();

    expect(Card::where('name', 'Disk')->sole()->output->refresh_interval_seconds)->toBe(5);
});

it('allows a 1-minute auto-refresh interval without hitting the seconds floor', function () {
    Livewire::test('card-manager')
        ->set('name', 'Disk')
        ->set('type', 'output')
        ->set('command', 'df -h')
        ->set('refreshAmount', 1)
        ->set('refreshUnit', 'minutes')
        ->call('save')
        ->assertHasNoErrors();

    expect(Card::where('name', 'Disk')->sole()->output->refresh_interval_seconds)->toBe(60);
});

it('restores the saved auto-refresh interval when editing an output card', function () {
    $card = Card::factory()->create(['type' => CardType::Output]);
    CardOutput::factory()->create([
        'card_id' => $card->id,
        'refresh_interval_seconds' => 120,
    ]);

    Livewire::test('card-manager')
        ->call('edit', $card->id)
        ->assertSet('refreshAmount', 2)
        ->assertSet('refreshUnit', 'minutes');
});

it('creates an api card with its connection details', function () {
    Livewire::test('card-manager')
        ->set('name', 'Sonarr')
        ->set('type', 'api')
        ->set('provider', 'sonarr')
        ->set('base_url', 'http://nas.lan:8989')
        ->set('api_key', 'secret')
        ->call('save');

    $card = Card::where('name', 'Sonarr')->sole();

    expect($card->type)->toBe(CardType::Api)
        ->and($card->url)->toBe('http://nas.lan:8989')
        ->and($card->api->provider)->toBe(ApiProvider::Sonarr)
        ->and($card->api->api_key)->toBe('secret');
});

it('creates an api card with username and password auth', function () {
    Livewire::test('card-manager')
        ->set('name', 'Router UI')
        ->set('type', 'api')
        ->set('provider', 'generic')
        ->set('base_url', 'http://192.168.1.1')
        ->set('auth_type', 'basic')
        ->set('username', 'admin')
        ->set('password', 'secret')
        ->call('save');

    $card = Card::where('name', 'Router UI')->sole();

    expect($card->api->auth_type)->toBe('basic')
        ->and($card->api->username)->toBe('admin')
        ->and($card->api->password)->toBe('secret')
        ->and($card->api->api_key)->toBeNull();
});

it('restores the saved auth type and username, but never the password, when editing an api card', function () {
    $card = Card::factory()->create(['type' => CardType::Api]);
    $card->api()->create([
        'provider' => ApiProvider::Generic,
        'base_url' => 'http://192.168.1.1',
        'auth_type' => 'basic',
        'username' => 'admin',
        'password' => 'secret',
    ]);

    Livewire::test('card-manager')
        ->call('edit', $card->id)
        ->assertSet('auth_type', 'basic')
        ->assertSet('username', 'admin')
        ->assertSet('password', '')
        ->assertSet('hasPassword', true);
});

it('never prefills the api key when editing an api-key-auth card', function () {
    $card = Card::factory()->create(['type' => CardType::Api]);
    $card->api()->create([
        'provider' => ApiProvider::Generic,
        'base_url' => 'http://192.168.1.1',
        'auth_type' => 'api_key',
        'api_key' => 'secret-key',
    ]);

    Livewire::test('card-manager')
        ->call('edit', $card->id)
        ->assertSet('auth_type', 'api_key')
        ->assertSet('api_key', '')
        ->assertSet('hasApiKey', true);
});

it('keeps the existing password when saving an edit with the password field left blank', function () {
    $card = Card::factory()->create(['type' => CardType::Api]);
    $card->api()->create([
        'provider' => ApiProvider::Generic,
        'base_url' => 'http://192.168.1.1',
        'auth_type' => 'basic',
        'username' => 'admin',
        'password' => 'secret',
    ]);

    Livewire::test('card-manager')
        ->call('edit', $card->id)
        ->set('username', 'admin2')
        ->call('save');

    expect($card->api()->first()->password)->toBe('secret')
        ->and($card->api()->first()->username)->toBe('admin2');
});

it('replaces the password when a new one is entered while editing', function () {
    $card = Card::factory()->create(['type' => CardType::Api]);
    $card->api()->create([
        'provider' => ApiProvider::Generic,
        'base_url' => 'http://192.168.1.1',
        'auth_type' => 'basic',
        'username' => 'admin',
        'password' => 'secret',
    ]);

    Livewire::test('card-manager')
        ->call('edit', $card->id)
        ->set('password', 'new-secret')
        ->call('save');

    expect($card->api()->first()->password)->toBe('new-secret');
});

it('keeps the existing api key when saving an edit with the api key field left blank', function () {
    $card = Card::factory()->create(['type' => CardType::Api]);
    $card->api()->create([
        'provider' => ApiProvider::Generic,
        'base_url' => 'http://192.168.1.1',
        'auth_type' => 'api_key',
        'api_key' => 'secret-key',
    ]);

    Livewire::test('card-manager')
        ->call('edit', $card->id)
        ->set('base_url', 'http://192.168.1.1:9999')
        ->call('save');

    expect($card->api()->first()->api_key)->toBe('secret-key')
        ->and($card->api()->first()->base_url)->toBe('http://192.168.1.1:9999');
});

it('requires a url for link cards', function () {
    Livewire::test('card-manager')
        ->set('name', 'Router')
        ->set('type', 'link')
        ->set('url', '')
        ->call('save')
        ->assertHasErrors('url');
});

it('assigns a card to a group', function () {
    $group = Group::factory()->create();

    Livewire::test('card-manager')
        ->set('name', 'Plex')
        ->set('type', 'link')
        ->set('url', 'http://plex.lan')
        ->set('group_id', $group->id)
        ->call('save');

    expect(Card::where('name', 'Plex')->sole()->group_id)->toBe($group->id);
});

it('drops the output record when an existing card is edited to a different type', function () {
    $card = Card::factory()->create(['type' => CardType::Output]);
    CardOutput::factory()->create(['card_id' => $card->id]);

    Livewire::test('card-manager')
        ->call('edit', $card->id)
        ->set('type', 'link')
        ->set('url', 'http://example.lan')
        ->call('save');

    expect($card->fresh()->output)->toBeNull();
});

it('deletes a card and its output record', function () {
    $card = Card::factory()->create(['type' => CardType::Output]);
    $output = CardOutput::factory()->create(['card_id' => $card->id]);

    Livewire::test('card-manager')->call('delete', $card->id);

    expect(Card::find($card->id))->toBeNull();
    expect(CardOutput::find($output->id))->toBeNull();
});

it('prefills the new-card form from a discovery result without saving it', function () {
    $component = Livewire::test('card-manager')
        ->call('prefillFromDiscovery', 'sonarr', 'http://sonarr.dev.local.test')
        ->assertSet('name', 'sonarr')
        ->assertSet('type', 'link')
        ->assertSet('url', 'http://sonarr.dev.local.test')
        ->assertSet('editingId', null);

    expect(Card::where('name', 'sonarr')->exists())->toBeFalse();

    $component->set('type', 'api')
        ->set('base_url', 'http://sonarr.dev.local.test')
        ->set('provider', 'sonarr')
        ->call('save');

    expect(Card::where('name', 'sonarr')->sole()->type)->toBe(CardType::Api);
});

it('saves a manually entered icon url', function () {
    Livewire::test('card-manager')
        ->set('name', 'Router')
        ->set('type', 'link')
        ->set('url', 'http://192.168.1.1')
        ->set('icon', 'https://example.test/router.svg')
        ->call('save');

    expect(Card::where('name', 'Router')->sole()->icon)->toBe('https://example.test/router.svg');
});

it('searches for matching icons as the icon query changes', function () {
    fakeDashboardIconsIndex();

    Livewire::test('card-manager')
        ->set('iconQuery', 'sonarr')
        ->assertSet('iconResults', [
            ['name' => 'sonarr', 'url' => 'https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/svg/sonarr.svg'],
        ]);
});

it('selects an icon from search results and clears the query', function () {
    fakeDashboardIconsIndex();

    Livewire::test('card-manager')
        ->set('iconQuery', 'sonarr')
        ->call('selectIcon', 'https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/svg/sonarr.svg')
        ->assertSet('icon', 'https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/svg/sonarr.svg')
        ->assertSet('iconQuery', '')
        ->assertSet('iconResults', []);
});

it('preloads icon suggestions when prefilling from a discovery result', function () {
    fakeDashboardIconsIndex();

    Livewire::test('card-manager')
        ->call('prefillFromDiscovery', 'sonarr', 'http://sonarr.dev.local.test')
        ->assertSet('iconResults', [
            ['name' => 'sonarr', 'url' => 'https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/svg/sonarr.svg'],
        ]);
});

it('carries the url over when switching from link to api and back', function () {
    Livewire::test('card-manager')
        ->set('name', 'Sonarr')
        ->set('url', 'http://nas.lan:8989')
        ->set('type', 'api')
        ->assertSet('base_url', 'http://nas.lan:8989')
        ->set('type', 'link')
        ->assertSet('url', 'http://nas.lan:8989');
});

it('restores the saved icon when editing a card', function () {
    $card = Card::factory()->create(['icon' => 'https://example.test/plex.svg']);

    Livewire::test('card-manager')
        ->call('edit', $card->id)
        ->assertSet('icon', 'https://example.test/plex.svg');
});
