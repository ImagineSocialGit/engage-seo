@props([
    'site',
])

@php
    $footer = $site['shell']['footer'];
    $business = $site['business'];
    $brand = $site['brand'];
    $hasAddress = $business['address']['lines'] !== [];
    $hasContact = $business['phone'] !== null
        || $business['email'] !== null
        || $hasAddress;
@endphp

<div class="site-container site-footer__inner">
    @if($footer['cta'])
        <section class="site-footer__cta" aria-labelledby="site-footer-cta-title">
            <div>
                <h2 id="site-footer-cta-title" class="site-footer__cta-title">
                    {{ $footer['cta']['title'] }}
                </h2>

                @if($footer['cta']['description'])
                    <p class="site-footer__cta-description">
                        {{ $footer['cta']['description'] }}
                    </p>
                @endif
            </div>

            <div class="site-footer__cta-actions">
                @foreach($footer['cta']['actions'] as $action)
                    <a
                        href="{{ $action['url'] }}"
                        class="site-primary-cta"
                        @if($action['active']) aria-current="page" @endif
                        @if($action['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                    >
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="site-footer__main">
        <div class="site-footer__identity">
            <a href="/" class="site-footer__brand">
                @if($brand['logo'])
                    <img
                        src="{{ $brand['logo'] }}"
                        alt="{{ $brand['logo_alt'] }}"
                        class="site-footer__logo"
                    >
                @else
                    {{ $site['name'] }}
                @endif
            </a>

            @if($footer['intro'])
                <p class="site-footer__intro">{{ $footer['intro'] }}</p>
            @endif

            @if($hasContact)
                <address class="site-footer__contact">
                    @if($business['phone'])
                        <div class="site-footer__contact-item">
                            <span class="site-footer__contact-label">
                                {{ $business['phone']['label'] }}
                            </span>
                            <a
                                href="{{ $business['phone']['url'] }}"
                                @if($business['phone']['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                            >
                                {{ $business['phone']['value'] }}
                            </a>
                        </div>
                    @endif

                    @if($business['email'])
                        <div class="site-footer__contact-item">
                            <span class="site-footer__contact-label">
                                {{ $business['email']['label'] }}
                            </span>
                            <a
                                href="{{ $business['email']['url'] }}"
                                @if($business['email']['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                            >
                                {{ $business['email']['value'] }}
                            </a>
                        </div>
                    @endif

                    @if($hasAddress)
                        <div class="site-footer__address">
                            @if($business['address']['url'])
                                <a
                                    href="{{ $business['address']['url'] }}"
                                    @if($business['address']['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                                >
                            @endif

                            @foreach($business['address']['lines'] as $line)
                                <span>{{ $line }}</span>@if(! $loop->last)<br>@endif
                            @endforeach

                            @if($business['address']['url'])
                                </a>
                            @endif
                        </div>
                    @endif
                </address>
            @endif

            @if($business['social_links'] !== [])
                <nav aria-label="Social links" class="site-footer__social">
                    <ul class="site-footer__link-list site-footer__link-list--inline">
                        @foreach($business['social_links'] as $link)
                            <li>
                                <a
                                    href="{{ $link['url'] }}"
                                    @if($link['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                                >
                                    {{ $link['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>

        @if($footer['groups'] !== [])
            <div class="site-footer__groups">
                @foreach($footer['groups'] as $group)
                    <nav
                        class="site-footer__group"
                        aria-labelledby="site-footer-group-{{ $loop->index }}"
                    >
                        <h2
                            id="site-footer-group-{{ $loop->index }}"
                            class="site-footer__group-title"
                        >
                            {{ $group['label'] }}
                        </h2>

                        <ul class="site-footer__link-list">
                            @foreach($group['items'] as $link)
                                <li>
                                    <a
                                        href="{{ $link['url'] }}"
                                        @if($link['active']) aria-current="page" @endif
                                        @if($link['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                                    >
                                        {{ $link['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endforeach
            </div>
        @endif
    </div>

    @if($footer['legal']['lines'] !== [] || $footer['legal']['links'] !== [])
        <div class="site-footer__legal">
            @if($footer['legal']['lines'] !== [])
                <div class="site-footer__legal-copy">
                    @foreach($footer['legal']['lines'] as $line)
                        <p>{{ $line }}</p>
                    @endforeach
                </div>
            @endif

            @if($footer['legal']['links'] !== [])
                <nav aria-label="Legal links">
                    <ul class="site-footer__link-list site-footer__link-list--inline">
                        @foreach($footer['legal']['links'] as $link)
                            <li>
                                <a
                                    href="{{ $link['url'] }}"
                                    @if($link['active']) aria-current="page" @endif
                                    @if($link['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                                >
                                    {{ $link['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    @endif
</div>