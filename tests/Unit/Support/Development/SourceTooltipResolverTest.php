<?php

namespace Tests\Unit\Support\Development;

use App\Support\Development\SourceTooltipResolver;
use Tests\TestCase;

final class SourceTooltipResolverTest extends TestCase
{
    public function test_only_annotated_values_are_extracted_with_their_actual_lines(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'seo-copy-');
        self::assertIsString($path);

        try {
            file_put_contents($path, <<<'SOURCE'
<?php
return [
    'heading' => 'Visible editorial text', // copy:heading
    'private_value' => 'Should not be exported',
    'action' => ['label' => 'Contact us', 'url' => '/contact'], // copy:action
];
SOURCE
            );

            self::assertSame([
                [
                    'text' => 'Visible editorial text',
                    'source' => 'client/example/config/pages/home.php:3',
                    'scope' => 'page',
                ],
                [
                    'text' => 'Contact us',
                    'source' => 'client/example/config/pages/home.php:5',
                    'scope' => 'page',
                ],
            ], SourceTooltipResolver::annotatedFile(
                $path,
                'client/example/config/pages/home.php',
                'page',
            ));
        } finally {
            unlink($path);
        }
    }

    public function test_source_tooltips_remain_disabled_outside_local_even_when_requested(): void
    {
        config()->set('app.debug', true);
        config()->set('devtools.source_tooltips', true);

        self::assertFalse(app(SourceTooltipResolver::class)->enabled());
    }

    public function test_missing_source_files_produce_no_entries(): void
    {
        self::assertSame([], SourceTooltipResolver::annotatedFile(
            sys_get_temp_dir().'/nonexistent-seo-'.bin2hex(random_bytes(8)),
            'client/example/config/pages/missing.php',
            'page',
        ));
    }
}