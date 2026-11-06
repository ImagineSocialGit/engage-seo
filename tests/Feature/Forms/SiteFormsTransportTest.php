<?php

namespace Tests\Feature\Forms;

use App\Features\Forms\Services\SiteFormBindings;
use App\Integrations\EngageCore\Data\FormSubmissionRequest;
use App\Integrations\EngageCore\Data\FormSubmissionVerification;
use App\Integrations\EngageCore\Data\PublishedForm;
use App\Integrations\EngageCore\Services\HttpEngageCoreFormsClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SiteFormsTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('engage_core.base_url', 'https://webhooks.example.test');
        config()->set('engage_core.client_key', 'fixture.seo');
        config()->set('engage_core.signing_secret', str_repeat('s', 48));
        config()->set('engage_core.forms.cache_seconds', 0);
        config()->set('engage_core.forms.retry_attempts', 1);
    }

    public function test_signed_form_retrieval_and_submission_follow_core_contract(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(['data' => $this->formData()], 200);
            }

            return Http::response(['data' => [
                'submission_id' => 12,
                'form_key' => 'example_inquiry',
                'form_version' => ['id' => 6, 'number' => 2],
                'contact_id' => null,
                'status' => 'received',
                'submitted_at' => now()->toIso8601String(),
                'replayed' => false,
            ]], 201);
        });

        $client = app(HttpEngageCoreFormsClient::class);
        $form = $client->publishedForm('example_inquiry');
        $this->assertSame(['email'], $form->fieldKeys());

        $result = $client->submit(new FormSubmissionRequest(
            formKey: 'example_inquiry',
            externalId: (string) Str::uuid(),
            values: ['email' => 'hello@example.test'],
            verification: new FormSubmissionVerification(
                provider: 'turnstile',
                verifiedAt: now()->toDateTimeImmutable(),
                hostname: 'seo.example.test',
                action: 'forms',
            ),
        ));
        $this->assertSame(12, $result->submissionId);

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->hasHeader('X-Engage-Signature')
                && $request->hasHeader('X-Engage-Client', 'fixture.seo')
                && $request->url() === 'https://webhooks.example.test/forms/example_inquiry/submissions'
                && ($request->data()['verification']['provider'] ?? null) === 'turnstile'
                && ! array_key_exists('cf-turnstile-response', $request->data());
        });
    }

    public function test_unsupported_layout_and_unknown_submitted_keys_fail_closed(): void
    {
        $form = PublishedForm::fromArray($this->formData());
        $bindings = app(SiteFormBindings::class);
        $this->assertTrue($bindings->supports($form));
        $this->assertSame(['email' => 'hello@example.test'],
            $bindings->values($form, ['email' => '  hello@example.test  ']));

        $this->expectException(\InvalidArgumentException::class);
        $bindings->values($form, ['email' => 'hello@example.test', 'forged' => '1']);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'definition_id' => 3,
            'version_id' => 6,
            'version_number' => 2,
            'key' => 'example_inquiry',
            'name' => 'Example inquiry',
            'description' => null,
            'category' => 'intake',
            'is_public' => true,
            'schema' => ['sections' => [[
                'key' => 'contact',
                'label' => 'Contact',
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