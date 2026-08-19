<?php

namespace App\View\Components;

use App\Support\Media\MediaAssetResolver;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

final class ResponsiveImage extends Component
{
    /** @var array<string, mixed> */
    public array $media;

    public function __construct(
        MediaAssetResolver $assets,
        public string $asset,
        public string $alt,
        public string $sizes = '100vw',
        public string $loading = 'lazy',
        public string $fetchpriority = 'auto',
        public string $decoding = 'async',
    ) {
        $this->asset = trim($this->asset);
        $this->sizes = trim($this->sizes);
        $this->loading = trim($this->loading);
        $this->fetchpriority = trim($this->fetchpriority);
        $this->decoding = trim($this->decoding);

        if ($this->asset === '') {
            throw new InvalidArgumentException(
                'Responsive image [asset] must not be blank.'
            );
        }

        if ($this->sizes === '') {
            throw new InvalidArgumentException(
                'Responsive image [sizes] must not be blank.'
            );
        }

        if (! in_array($this->loading, ['lazy', 'eager'], true)) {
            throw new InvalidArgumentException(
                'Responsive image [loading] must be lazy or eager.'
            );
        }

        if (! in_array($this->fetchpriority, ['auto', 'high', 'low'], true)) {
            throw new InvalidArgumentException(
                'Responsive image [fetchpriority] must be auto, high, or low.'
            );
        }

        if (! in_array($this->decoding, ['auto', 'async', 'sync'], true)) {
            throw new InvalidArgumentException(
                'Responsive image [decoding] must be auto, async, or sync.'
            );
        }

        $this->media = $assets->resolve($this->asset);
    }

    public function render(): View
    {
        return view('components.responsive-image');
    }
}