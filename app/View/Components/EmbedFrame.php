<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

final class EmbedFrame extends Component
{
    public function __construct(
        public string $src,
        public string $title,
        public string $loading = 'lazy',
    ) {
        $this->src = trim($this->src);
        $this->title = trim($this->title);
        $this->loading = trim($this->loading);

        if ($this->title === '') {
            throw new InvalidArgumentException(
                'Embedded media [title] must not be blank.'
            );
        }

        $scheme = strtolower((string) parse_url($this->src, PHP_URL_SCHEME));
        $host = parse_url($this->src, PHP_URL_HOST);

        if (
            ! in_array($scheme, ['http', 'https'], true)
            || ! is_string($host)
            || trim($host) === ''
        ) {
            throw new InvalidArgumentException(
                'Embedded media [src] must be an absolute http or https URL.'
            );
        }

        if (! in_array($this->loading, ['lazy', 'eager'], true)) {
            throw new InvalidArgumentException(
                'Embedded media [loading] must be lazy or eager.'
            );
        }
    }

    public function render(): View
    {
        return view('components.embed-frame');
    }
}