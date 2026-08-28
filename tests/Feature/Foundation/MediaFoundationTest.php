<?php

namespace Tests\Feature\Foundation;

use App\Support\Media\MediaAssetResolver;
use App\Support\Media\MediaManifestRepository;
use App\Support\Media\MediaUrlResolver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MediaFoundationTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'engage-seo-media-'.bin2hex(random_bytes(6));

        File::ensureDirectoryExists(
            $this->temporaryRoot.'/client/media-test-client/resources/images/raw'
        );
        File::ensureDirectoryExists(
            $this->temporaryRoot.'/public/media/assets/hero'
        );

        File::put(
            $this->temporaryRoot.'/client/media-test-client/resources/images/raw/hero.jpg',
            'raw-image-fixture',
        );
        File::put(
            $this->temporaryRoot.'/public/media/assets/hero/320.avif',
            'avif-320',
        );
        File::put(
            $this->temporaryRoot.'/public/media/assets/hero/1280.avif',
            'avif-1280',
        );
        File::put(
            $this->temporaryRoot.'/public/media/assets/hero/320.webp',
            'webp-320',
        );
        File::put(
            $this->temporaryRoot.'/public/media/assets/hero/1280.webp',
            'webp-1280',
        );

        config()->set('client.key', 'media-test-client');
        config()->set(
            'media.manifest_path',
            $this->temporaryRoot.'/public/media/manifest.json',
        );
        config()->set('media.public_prefix', '/media');
        config()->set('media.base_url', null);

        $this->writeManifest();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryRoot);

        parent::tearDown();
    }

    public function test_manifest_resolves_to_stable_local_rendering_contract(): void
    {
        $errors = app(MediaManifestRepository::class)
            ->validationErrors($this->temporaryRoot);

        $this->assertSame([], $errors);

        $asset = app(MediaAssetResolver::class)->resolve('hero');

        $this->assertSame(
            '/media/assets/hero/1280.webp',
            $asset['fallback']['url'],
        );
        $this->assertSame(1280, $asset['fallback']['width']);
        $this->assertSame(720, $asset['fallback']['height']);
        $this->assertSame(
            '/media/assets/hero/320.avif 320w, /media/assets/hero/1280.avif 1280w',
            $asset['srcsets']['avif'],
        );
    }

    public function test_media_base_url_can_move_generated_assets_to_an_absolute_public_origin(): void
    {
        config()->set(
            'media.base_url',
            'https://cdn.example.test/client-assets',
        );

        $asset = app(MediaAssetResolver::class)->resolve('hero');

        $this->assertSame(
            'https://cdn.example.test/client-assets/assets/hero/1280.webp',
            $asset['fallback']['url'],
        );
    }

    public function test_responsive_image_component_renders_semantic_picture_sources_and_intrinsic_dimensions(): void
    {
        $html = Blade::render(
            '<x-responsive-image asset="hero" alt="" sizes="100vw" loading="eager" fetchpriority="high" />'
        );

        $this->assertStringContainsString('<picture>', $html);
        $this->assertStringContainsString('type="image/avif"', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringContainsString('width="1280"', $html);
        $this->assertStringContainsString('height="720"', $html);
        $this->assertStringContainsString('loading="eager"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
    }

    public function test_stale_raw_source_is_reported_by_manifest_validation(): void
    {
        File::put(
            $this->temporaryRoot.'/client/media-test-client/resources/images/raw/hero.jpg',
            'changed-raw-image',
        );

        $errors = app(MediaManifestRepository::class)
            ->validationErrors($this->temporaryRoot);

        $this->assertNotEmpty($errors);
    }

    public function test_raw_source_requires_a_generated_manifest(): void
    {
        File::delete(
            $this->temporaryRoot.'/public/media/manifest.json'
        );

        $errors = app(MediaManifestRepository::class)
            ->validationErrors($this->temporaryRoot);

        $this->assertNotEmpty($errors);
    }

    public function test_manifest_must_belong_to_selected_client(): void
    {
        $this->writeManifest('another-client');

        $errors = app(MediaManifestRepository::class)
            ->validationErrors($this->temporaryRoot);

        $this->assertNotEmpty($errors);
    }

    public function test_media_base_url_rejects_unsupported_schemes(): void
    {
        config()->set('media.base_url', 'ftp://cdn.example.test/media');

        $errors = app(MediaUrlResolver::class)
            ->validationErrors();

        $this->assertCount(1, $errors);
    }

    private function writeManifest(string $client = 'media-test-client'): void
    {
        $rawPath = $this->temporaryRoot
            .'/client/media-test-client/resources/images/raw/hero.jpg';

        $manifest = [
            'version' => 1,
            'client' => $client,
            'assets' => [
                'hero' => [
                    'source' => [
                        'path' => 'hero.jpg',
                        'sha256' => hash_file('sha256', $rawPath),
                    ],
                    'width' => 1280,
                    'height' => 720,
                    'placeholder' => 'data:image/webp;base64,'.base64_encode('placeholder'),
                    'fallback' => [
                        'path' => 'assets/hero/1280.webp',
                        'width' => 1280,
                        'height' => 720,
                    ],
                    'sources' => [
                        'avif' => [
                            [
                                'path' => 'assets/hero/320.avif',
                                'width' => 320,
                                'height' => 180,
                            ],
                            [
                                'path' => 'assets/hero/1280.avif',
                                'width' => 1280,
                                'height' => 720,
                            ],
                        ],
                        'webp' => [
                            [
                                'path' => 'assets/hero/320.webp',
                                'width' => 320,
                                'height' => 180,
                            ],
                            [
                                'path' => 'assets/hero/1280.webp',
                                'width' => 1280,
                                'height' => 720,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        File::put(
            $this->temporaryRoot.'/public/media/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }
}