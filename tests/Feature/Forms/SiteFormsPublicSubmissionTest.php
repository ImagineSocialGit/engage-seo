<?php

namespace Tests\Feature\Forms;

use App\Features\Forms\FormsServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SiteFormsPublicSubmissionTest extends TestCase
{
    private const FORM = 'example_inquiry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('engage_core.base_url', 'https://webhooks.example.test');
        config()->set('engage_core.client_key', 'fixture.seo');
        config()->set('engage_core.signing_secret', str_repeat('s', 48));
        config()->set('engage_core.forms.cache_seconds', 0);
        config()->set('engage_core.forms.retry_attempts', 1);
        config()->set('site_forms.turnstile.site_key', 'fixture-public-key');
        config()->set('site_forms.turnstile.secret_key', 'fixture-private-key');
        config()->set('site_forms.bindings', [
            self::FORM => [
                'enabled' => true,
                'verification_action' => 'forms',
                'submit_label' => 'Submit',
            ],
        ]);
        // Exercise the feature's route without selecting a real client repository.
        $this->app->register(FormsServiceProvider::class);
    }

    public function test_a_verified_site_submission_is_forwarded_to_core_without_the_raw_challenge(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'siteverify')) {
                return Http::response([
                    'success' => true,
                    'hostname' => 'seo.example.test',
                    'action' => 'forms',
                    'challenge_ts' => CarbonImmutable::now()->toIso8601String(),
                ]);
            }
            if ($request->method() === 'GET') {
                return Http::response(['data' => $this->published()], 200);
            }
            return Http::response(['data' => [
                'submission_id' => 22,
                'form_key' => self::FORM,
                'form_version' => ['id' => 2, 'number' => 1],
                'contact_id' => 4,
                'status' => 'received',
                'submitted_at' => now()->toIso8601String(),
                'replayed' => false,
            ]], 201);
        });

        $this->from('https://seo.example.test/donate')
            ->post('https://seo.example.test/_site-forms/'.self::FORM, [
                'external_id' => (string) Str::uuid(),
                'values' => ['email' => 'contact@example.test'],
                'cf-turnstile-response' => 'passed-token',
            ])
            ->assertRedirect('https://seo.example.test/donate')
            ->assertSessionHas('site_form_success_'.self::FORM, true);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'submissions')
                && ! str_contains($request->body(), 'passed-token')
                && ($request->data()['verification']['hostname'] ?? null) === 'seo.example.test';
        });
    }

    public function test_invalid_verification_cannot_reach_core_submission_endpoint(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(['data' => $this->published()], 200);
            }
            return Http::response(['success' => false], 200);
        });

        $this->from('https://seo.example.test/donate')
            ->post('https://seo.example.test/_site-forms/'.self::FORM, [
                'external_id' => (string) Str::uuid(),
                'values' => ['email' => 'contact@example.test'],
                'cf-turnstile-response' => 'invalid',
            ])
            ->assertRedirect('https://seo.example.test/donate')
            ->assertSessionHasErrors(['human_verification'], null, 'site_form_'.self::FORM);

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/submissions'));
    }

    public function test_unbound_form_is_not_publicly_submittable(): void
    {
        $this->post('https://seo.example.test/_site-forms/not_bound', [
            'values' => ['email' => 'contact@example.test'],
        ])->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function published(): array
    {
        return [
            'definition_id' => 1,
            'version_id' => 2,
            'version_number' => 1,
            'key' => self::FORM,
            'name' => 'Example inquiry',
            'description' => null,
            'category' => 'intake',
            'is_public' => true,
            'schema' => ['sections' => [[
                'key' => 'contact', 'label' => 'Contact',
                'fields' => [[
                    'key' => 'email', 'type' => 'email',
                    'label' => 'Email', 'required' => true,
                ]],
            ]]],
            'rules' => ['email' => ['required', 'email']],
            'layout' => [],
            'settings' => [],
            'fields' => [[
                'key' => 'email', 'type' => 'email',
                'label' => 'Email', 'required' => true,
            ]],
        ];
    }
}