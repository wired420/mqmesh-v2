<?php

namespace Tests\Feature;

use App\Support\UpdaterReleases;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UpdaterPageTest extends TestCase
{
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
}
