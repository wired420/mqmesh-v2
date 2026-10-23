<?php

namespace Tests\Feature;

use App\Models\Expansion;
use App\Models\FileVersion;
use App\Models\User;
use App\Models\Zone;
use App\Services\FileLibrary;
use App\Services\LegacyFeeds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MeshLibraryTest extends TestCase
{
    use RefreshDatabase;

    private string $feeds;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('meshes');
        Expansion::syncFromConfig();
        $this->feeds = sys_get_temp_dir().'/mqmesh-feeds-'.uniqid();
        mkdir($this->feeds);
        $this->app->usePublicPath($this->feeds);
    }

    private function meshFile(string $body = 'abc'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mesh');
        file_put_contents($path, FileLibrary::MESH_MAGIC."\x04\x00\x01\x00".$body);

        return $path;
    }

    private function zoneWithSiteMesh(): Zone
    {
        $zone = Zone::factory()->create(['short_name' => 'poknowledge', 'name' => 'Plane of Knowledge']);
        app(FileLibrary::class)->store($zone, 'mesh', $this->meshFile('site'), ['source' => 'depot', 'official' => true]);

        return $zone->fresh();
    }

    public function test_site_file_is_pinned_and_previous_one_superseded(): void
    {
        $zone = $this->zoneWithSiteMesh();
        $library = app(FileLibrary::class);

        $community = $library->store($zone, 'mesh', $this->meshFile('community'), ['source' => 'upload', 'status' => 'approved']);
        $this->assertSame($zone->siteMesh->id, $zone->versions()->siteFirst()->first()->id);

        $library->store($zone, 'mesh', $this->meshFile('newer'), ['source' => 'depot', 'official' => true]);

        $this->assertSame(3, $zone->versions()->count());
        $this->assertSame(1, $zone->versions()->where('is_official', true)->count());
        $this->assertSame('superseded', $zone->versions()->where('version', 1)->value('status'));
        $this->assertSame('approved', $community->fresh()->status);

        $this->get(route('files.download', $zone->versions()->where('version', 1)->sole()))->assertOk();
    }

    public function test_upload_waits_for_review_and_rejects_bad_or_duplicate_files(): void
    {
        $zone = $this->zoneWithSiteMesh();
        $user = User::factory()->create();

        Volt::actingAs($user)->test('uploads.create', ['zone' => 'poknowledge'])
            ->set('zoneName', 'poknowledge')
            ->set('file', UploadedFile::fake()->createWithContent('poknowledge.navmesh', 'not a mesh'))
            ->call('save')
            ->assertHasErrors('file');

        Volt::actingAs($user)->test('uploads.create')
            ->set('zoneName', 'poknowledge')
            ->set('file', UploadedFile::fake()->createWithContent('poknowledge.navmesh', file_get_contents($this->meshFile('site'))))
            ->call('save')
            ->assertHasErrors('file');

        Volt::actingAs($user)->test('uploads.create')
            ->set('zoneName', 'poknowledge')
            ->set('notes', 'Fixed the docks')
            ->set('file', UploadedFile::fake()->createWithContent('poknowledge.navmesh', file_get_contents($this->meshFile('mine'))))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('zones.show', $zone));

        $upload = $zone->versions()->where('user_id', $user->id)->sole();
        $this->assertSame('pending', $upload->status);
        $this->assertFalse($upload->isPublic());

        $this->app['auth']->forgetGuards();
        $this->get(route('files.download', $upload))->assertNotFound();
        $this->actingAs($user)->get(route('files.download', $upload))->assertOk();
    }

    public function test_moderator_approves_and_admin_promotes_which_rewrites_the_feeds(): void
    {
        $zone = $this->zoneWithSiteMesh();
        $upload = app(FileLibrary::class)->store($zone, 'mesh', $this->meshFile('mine'), ['source' => 'upload', 'user' => User::factory()->create()]);

        $moderator = User::factory()->create();
        $moderator->forceFill(['role' => 'moderator'])->save();
        $this->actingAs(User::factory()->create())->get(route('moderation'))->assertForbidden();

        Volt::actingAs($moderator)->test('moderation')->call('approve', $upload->id);
        $this->assertSame('approved', $upload->fresh()->status);

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        Volt::actingAs($moderator)->test('zones.show', ['zone' => $zone])->call('makeSiteFile', $upload->id)->assertForbidden();
        Volt::actingAs($admin)->test('zones.show', ['zone' => $zone])->call('makeSiteFile', $upload->id);

        $this->assertTrue($upload->fresh()->is_official);
        $feed = json_decode(file_get_contents($this->feeds.'/meshdb.json'), true);
        $this->assertSame(strtoupper($upload->md5), $feed['poknowledge']['hash']);
    }

    public function test_legacy_feeds_match_the_old_format(): void
    {
        $zone = $this->zoneWithSiteMesh();
        app(LegacyFeeds::class)->write();

        $zones = json_decode(file_get_contents($this->feeds.'/zones.json'), true);
        $updater = json_decode(file_get_contents($this->feeds.'/updater.json'), true);

        $entry = $zones['zone']['poknowledge'];
        $this->assertSame(['files', 'shortname', 'id', 'name', 'expansion'], array_keys($entry));
        $this->assertSame(['link', 'lastmodified', 'hash', 'size'], array_keys($entry['files']['mesh']));
        $this->assertStringEndsWith('/resources/meshes/poknowledge.navmesh', $entry['files']['mesh']['link']);
        $this->assertSame('12B', $entry['files']['mesh']['size']);
        $this->assertSame(['link', 'hash', 'size'], array_keys($updater['zones']['poknowledge']['files']['mesh']));

        $this->get('/resources/meshes/poknowledge.navmesh')
            ->assertOk()
            ->assertDownload('poknowledge.navmesh');
    }

    public function test_api_manifest_supports_etags_and_token_uploads(): void
    {
        $zone = $this->zoneWithSiteMesh();

        $response = $this->getJson('/api/v1/manifest')->assertOk()->assertJsonPath('zones.0.short_name', 'poknowledge');
        $this->getJson('/api/v1/manifest', ['If-None-Match' => $response->headers->get('ETag')])->assertStatus(304);

        // Downloads don't change the manifest, so they must not change its ETag.
        $this->get(route('files.download', $zone->siteMesh))->assertOk();
        $this->getJson('/api/v1/manifest')->assertHeader('ETag', $response->headers->get('ETag'));

        // ?include=community adds approved community versions and changes the ETag;
        // the plain manifest stays exactly as it was.
        $community = app(FileLibrary::class)->store($zone, 'mesh', $this->meshFile('shared'), ['source' => 'upload', 'status' => 'approved', 'user' => User::factory()->create(['name' => 'Kaen'])]);
        $this->getJson('/api/v1/manifest')->assertHeader('ETag', $response->headers->get('ETag'))->assertJsonMissingPath('zones.0.community');
        $withCommunity = $this->getJson('/api/v1/manifest?include=community')
            ->assertOk()
            ->assertJsonPath('zones.0.community.0.id', $community->id)
            ->assertJsonPath('zones.0.community.0.uploaded_by', 'Kaen');
        $this->assertNotSame($response->headers->get('ETag'), $withCommunity->headers->get('ETag'));
        $community->forceFill(['status' => 'rejected'])->save();
        $this->getJson('/api/v1/manifest?include=community')->assertJsonCount(0, 'zones.0.community');
        $this->getJson('/api/v1/manifest?include=everything')->assertUnprocessable();

        $this->postJson('/api/v1/zones/poknowledge/versions')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(), ['read']);
        $this->postJson('/api/v1/zones/poknowledge/versions')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(), ['read', 'upload']);
        $this->post('/api/v1/zones/poknowledge/versions', [
            'type' => 'mesh',
            'file' => UploadedFile::fake()->createWithContent('x.navmesh', file_get_contents($this->meshFile('api'))),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('status', 'pending');

        $this->assertSame(3, $zone->versions()->count());
    }

    public function test_moderators_are_emailed_about_pending_uploads(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $zone = $this->zoneWithSiteMesh();

        $moderator = User::factory()->create();
        $moderator->forceFill(['role' => 'moderator'])->save();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $uploader = User::factory()->create();

        app(FileLibrary::class)->store($zone, 'mesh', $this->meshFile('pending'), ['source' => 'upload', 'user' => $uploader]);
        app(FileLibrary::class)->store($zone, 'mesh', $this->meshFile('trusted'), ['source' => 'upload', 'status' => 'approved', 'user' => $uploader]);
        $this->app->terminate();

        \Illuminate\Support\Facades\Notification::assertSentTo([$moderator, $admin], \App\Notifications\UploadAwaitingReview::class);
        \Illuminate\Support\Facades\Notification::assertSentTimes(\App\Notifications\UploadAwaitingReview::class, 2);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($uploader, \App\Notifications\UploadAwaitingReview::class);
    }

    public function test_gitlab_webhook_needs_the_secret(): void
    {
        config(['mqmesh.depot.webhook_secret' => 'right-secret']);
        \Illuminate\Support\Facades\Artisan::partialMock()->shouldReceive('call')->with('mqmesh:sync-depot')->once();

        $this->postJson('/hooks/gitlab', [], ['X-Gitlab-Event' => 'Push Hook'])->assertForbidden();
        $this->postJson('/hooks/gitlab', [], ['X-Gitlab-Event' => 'Push Hook', 'X-Gitlab-Token' => 'wrong'])->assertForbidden();
        $this->postJson('/hooks/gitlab', [], ['X-Gitlab-Event' => 'Tag Push Hook', 'X-Gitlab-Token' => 'right-secret'])->assertOk();
        $this->postJson('/hooks/gitlab', [], ['X-Gitlab-Event' => 'Push Hook', 'X-Gitlab-Token' => 'right-secret'])->assertStatus(202);
    }

    public function test_zip_contains_site_files_under_their_mq2nav_names(): void
    {
        $this->zoneWithSiteMesh();

        $response = $this->get('/download/zip?zones=poknowledge');
        $response->assertOk()->assertHeader('Content-Type', 'application/zip');

        $path = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($path, $response->streamedContent());
        $zip = new \ZipArchive();
        $zip->open($path);

        $this->assertSame(1, $zip->numFiles);
        $this->assertSame('poknowledge.navmesh', $zip->getNameIndex(0));
    }
}
