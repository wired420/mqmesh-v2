<?php

namespace Tests\Feature;

use App\Support\UpdaterReleases;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UpdaterPageTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(UpdaterReleases::directory());

        parent::tearDown();
    }

    public function test_the_newest_build_per_platform_is_offered_and_nothing_else_downloads(): void
    {
        $dir = UpdaterReleases::directory();
        File::ensureDirectoryExists($dir);
        file_put_contents("{$dir}/MQMeshUpdater-2.0.9-win-x64.zip", 'old');
        file_put_contents("{$dir}/MQMeshUpdater-2.1.0-win-x64.zip", 'new windows');
        file_put_contents("{$dir}/mqmesh-updater-2.1.0-linux-x64.tar.gz", 'new linux');
        file_put_contents("{$dir}/notes.txt", 'private');

        $this->get('/updater')
            ->assertOk()
            ->assertSee('Download for Windows')
            ->assertSee('Download for Linux')
            ->assertSee(hash('sha256', 'new windows'))
            ->assertDontSee('2.0.9');

        $this->get('/updater/download/MQMeshUpdater-2.1.0-win-x64.zip')->assertOk()->assertDownload('MQMeshUpdater-2.1.0-win-x64.zip');
        $this->get('/updater/download/MQMeshUpdater-2.0.9-win-x64.zip')->assertNotFound();
        $this->get('/updater/download/notes.txt')->assertNotFound();
        $this->get('/updater/download/..%2F..%2F.env')->assertNotFound();
    }

    public function test_admins_publish_both_builds_together_and_nobody_else_can(): void
    {
        $zip = fn ($v) => \Illuminate\Http\UploadedFile::fake()->createWithContent("MQMeshUpdater-{$v}-win-x64.zip", "PK\x03\x04windows {$v}");
        $tgz = fn ($v) => \Illuminate\Http\UploadedFile::fake()->createWithContent("mqmesh-updater-{$v}-linux-x64.tar.gz", "\x1f\x8blinux {$v}");

        $user = \App\Models\User::factory()->create();
        $admin = \App\Models\User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($user)->get('/updater')->assertOk()->assertDontSee('Publish a new build');
        \Livewire\Volt\Volt::actingAs($user)->test('updater-publish')->assertForbidden();

        $form = \Livewire\Volt\Volt::actingAs($admin)->test('updater-publish');
        $form->set('windows', $zip('2.2.0'))->call('publish')->assertHasErrors(['linux' => 'required']);
        $form->set('linux', $tgz('2.3.0'))->call('publish')->assertHasErrors('linux');
        $form->set('linux', \Illuminate\Http\UploadedFile::fake()->createWithContent('mqmesh-updater-2.2.0-linux-x64.tar.gz', 'not gzip'))->call('publish')->assertHasErrors('linux');

        $form->set('linux', $tgz('2.2.0'))->call('publish')->assertHasNoErrors()->assertDispatched('updater-published');
        $this->assertSame(['2.2.0'], UpdaterReleases::latest()->pluck('version')->unique()->values()->all());

        \Livewire\Volt\Volt::actingAs($admin)->test('updater-publish')
            ->set('windows', $zip('2.2.0'))->set('linux', $tgz('2.2.0'))
            ->call('publish')->assertHasErrors('windows');
    }
}
