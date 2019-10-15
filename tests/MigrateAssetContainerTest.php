<?php

namespace Tests;

use Tests\TestCase;
use Statamic\Support\Arr;
use Statamic\Migrator\YAML;
use Tests\Console\Foundation\InteractsWithConsole;

class MigrateAssetContainerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // If doesn't exist, backup original config/filesystems.php.
        if (! $this->files->exists(config_path('filesystems-original.php'))) {
            $this->files->copy(config_path('filesystems.php'), config_path('filesystems-original.php'));
        }

        $this->restoreConfig();
    }

    protected function tearDown(): void
    {
        $this->restoreConfig();

        parent::tearDown();
    }

    protected function restoreConfig()
    {
        $this->files->copy(config_path('filesystems-original.php'), config_path('filesystems.php'));
    }

    protected function paths()
    {
        return [
            base_path('content/assets'),
            public_path('assets'),
        ];
    }

    protected function containerPath($append = null)
    {
        return collect([base_path('content/assets'), $append])->filter()->implode('/');
    }

    /** @test */
    function it_migrates_yaml_config()
    {
        $this->artisan('statamic:migrate:asset-container', ['handle' => 'main']);

        $expected = [
            'title' => 'Main Assets',
            'disk' => 'assets',
        ];

        $this->assertParsedYamlEquals($expected, $this->containerPath('main.yaml'));
    }

    /** @test */
    function it_migrates_assets_disk_into_default_laravel_config()
    {
        $this->files->copy(__DIR__.'/Fixtures/config/filesystem-default.php', config_path('filesystems.php'));

        $this->artisan('statamic:migrate:asset-container', ['handle' => 'main']);

        $this->assertFilesystemConfigFileContains(<<<EOT
    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
        ],

        'assets' => [
            'driver' => 'local',
            'root' => public_path('assets'),
            'url' => '/assets',
            'visibility' => 'public',
        ],

    ],
EOT
        );

        $this->assertFilesystemDiskExists('local');
        $this->assertFilesystemDiskExists('public');
        $this->assertFilesystemDiskExists('s3');
        $this->assertFilesystemDiskExists('assets');
    }

    /** @test */
    function it_migrates_assets_disk_into_sanely_user_edited_config()
    {
        $this->files->copy(__DIR__.'/Fixtures/config/filesystem-edited.php', config_path('filesystems.php'));

        $this->artisan('statamic:migrate:asset-container', ['handle' => 'main']);

        $this->assertFilesystemConfigFileContains(<<<EOT
    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'custom' => [
            'driver' => 'local',
            'root' => storage_path('app/custom'),
        ],

        'assets' => [
            'driver' => 'local',
            'root' => public_path('assets'),
            'url' => '/assets',
            'visibility' => 'public',
        ],

    ],
EOT
        );

        $this->assertFilesystemConfigFileContains(<<<EOT
    'extra-config' => [
        'from-some-other-package' => true
    ],
EOT
        );

        $this->assertFilesystemDiskExists('local');
        $this->assertFilesystemDiskExists('custom');
        $this->assertFilesystemDiskExists('assets');
    }

    /** @test */
    function it_migrates_assets_disk_into_weirdly_mangled_config()
    {
        $this->files->copy(__DIR__.'/Fixtures/config/filesystem-weird.php', config_path('filesystems.php'));

        $this->artisan('statamic:migrate:asset-container', ['handle' => 'main']);

        $this->assertFilesystemConfigFileContains(<<<EOT
'disks' => [

        'assets' => [
            'driver' => 'local',
            'root' => public_path('assets'),
            'url' => '/assets',
            'visibility' => 'public',
        ],
EOT
        );

        $this->assertFilesystemConfigFileContains(<<<EOT
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app'),
    ],
    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
    ],
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
    ],
EOT
        );

        $this->assertFilesystemConfigFileContains(<<<EOT
'extra-config' => [
    'from-some-other-package' => true
],
EOT
        );

        $this->assertFilesystemDiskExists('local');
        $this->assertFilesystemDiskExists('public');
        $this->assertFilesystemDiskExists('s3');
        $this->assertFilesystemDiskExists('assets');
    }

    /**
     * Assert filesystem config file replacement is valid and contains specific content.
     *
     * @param string $content
     */
    protected function assertFilesystemConfigFileContains($content)
    {
        $config = config_path('filesystems.php');

        $beginning = <<<EOT
<?php

return [
EOT;

        $end = '];';

        $irrelevantConfig = "'default' => env('FILESYSTEM_DRIVER', 'local'),";

        // Assert valid PHP array.
        $this->assertEquals('array', gettype(include $config));

        // Assert begining and end of config is untouched.
        $this->assertContains($beginning, $this->files->get($config));
        $this->assertContains($end, $this->files->get($config));

        // Assert irrelevant config is untouched.
        $this->assertContains($irrelevantConfig, $this->files->get($config));

        // Assert config file contains specific content.
        return $this->assertContains($content, $this->files->get($config));
    }

    /**
     * Assert filesystem disk array key exists.
     *
     * @param string $disk
     */
    protected function assertFilesystemDiskExists($disk)
    {
        return $this->assertTrue(Arr::has(include config_path('filesystems.php'), "disks.{$disk}"));
    }
}
