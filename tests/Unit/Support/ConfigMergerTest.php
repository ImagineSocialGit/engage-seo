<?php

namespace Tests\Unit\Support;

use App\Support\Config\ConfigMerger;
use PHPUnit\Framework\TestCase;

class ConfigMergerTest extends TestCase
{
    public function test_associative_arrays_merge_recursively_and_lists_replace(): void
    {
        $base = [
            'brand' => [
                'name' => 'Platform',
                'colors' => [
                    'primary' => 'black',
                    'secondary' => 'white',
                ],
            ],
            'navigation' => [
                'home',
                'about',
                'contact',
            ],
        ];

        $override = [
            'brand' => [
                'name' => 'Client',
                'colors' => [
                    'primary' => 'blue',
                ],
            ],
            'navigation' => [
                'home',
                'services',
            ],
        ];

        $this->assertSame([
            'brand' => [
                'name' => 'Client',
                'colors' => [
                    'primary' => 'blue',
                    'secondary' => 'white',
                ],
            ],
            'navigation' => [
                'home',
                'services',
            ],
        ], ConfigMerger::merge($base, $override));
    }
}