<?php

namespace App\Features\Forms\Http\Controllers;

use App\Features\Forms\Services\SiteFormBindings;
use App\Features\Forms\Services\TurnstileFormVerifier;
use App\Integrations\EngageCore\Contracts\EngageCoreFormsClient;
use App\Integrations\EngageCore\Data\FormSubmissionRequest;
use App\Integrations\EngageCore\Exceptions\EngageCoreFormsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class SiteFormSubmissionController
{
    public function __invoke(
        Request $request,
        string $formKey,
        SiteFormBindings $bindings,
        EngageCoreFormsClient $core,
        TurnstileFormVerifier $verifier,
    ): RedirectResponse {
        $binding = $bindings->get($formKey);
        abort_if($binding === null, 404);
        $errorBag = 'site_form_'.$formKey;

        // Silent honeypot: never send bot traffic to Core or the challenge provider.
        if ($request->filled('company_website')) {
            return back()->with('site_form_success_'.$formKey, true);
        }

        try {
            $form = $core->publishedForm($formKey);
            if (! $bindings->supports($form)) {
                throw new InvalidArgumentException('The published form is not supported by this site.');
            }
            $values = $bindings->values($form, $request->input('values', []));
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors([
                '_form' => 'This form is temporarily unavailable. Please try again later.',
            ], $errorBag);
        }

        $action = $binding['verification_action'] ?? 'forms';
        if (! is_string($action)) {
            $action = '';
        }
        $verification = $verifier->verify($request, $action);
        if ($verification === null) {
            return back()->withErrors([
                'human_verification' => 'Please complete the verification and try again.',
            ], $errorBag)->withInput($request->except('cf-turnstile-response'));
        }

        $externalId = $request->input('external_id');
        if (! is_string($externalId) || ! Str::isUuid($externalId)) {
            return back()->withErrors([
                '_form' => 'This submission has expired. Please reload the page.',
            ], $errorBag);
        }

        try {
            $core->submit(new FormSubmissionRequest(
                formKey: $formKey,
                externalId: $externalId,
                values: $values,
                ipAddress: $request->ip(),
                userAgent: mb_substr((string) $request->userAgent(), 0, 65535),
                verification: $verification,
            ));
        } catch (EngageCoreFormsException $exception) {
            if ($exception->kind === EngageCoreFormsException::VALIDATION) {
                return back()->withErrors(
                    $exception->validationErrors ?: [
                        '_form' => 'Please correct the highlighted fields.',
                    ],
                    $errorBag,
                )->withInput($request->except('cf-turnstile-response'));
            }
            report($exception);
            return back()->withErrors([
                '_form' => 'Unable to submit your inquiry right now. Please try again.',
            ], $errorBag)->withInput($request->except('cf-turnstile-response'));
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors([
                '_form' => 'Unable to submit your inquiry right now. Please try again.',
            ], $errorBag)->withInput($request->except('cf-turnstile-response'));
        }

        return back()->with('site_form_success_'.$formKey, true);
    }
}