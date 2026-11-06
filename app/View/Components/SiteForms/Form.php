<?php

namespace App\View\Components\SiteForms;

use App\Features\Forms\Services\SiteFormBindings;
use App\Integrations\EngageCore\Contracts\EngageCoreFormsClient;
use App\Integrations\EngageCore\Data\PublishedForm;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Throwable;

final class Form extends Component
{
    public ?PublishedForm $published = null;

    /** @var array<string, mixed>|null */
    public ?array $binding = null;

    public bool $ready = false;

    public function __construct(public string $formKey)
    {
        $bindings = app(SiteFormBindings::class);
        $this->binding = $bindings->get($formKey);

        if ($this->binding === null || ! is_string(config('site_forms.turnstile.site_key'))
            || trim((string) config('site_forms.turnstile.site_key')) === ''
            || ! is_string(config('site_forms.turnstile.secret_key'))
            || trim((string) config('site_forms.turnstile.secret_key')) === ''
        ) {
            return;
        }

        try {
            $this->published = app(EngageCoreFormsClient::class)->publishedForm($formKey);
            $this->ready = $bindings->supports($this->published);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function render(): View
    {
        return view('components.site-forms.form');
    }
}