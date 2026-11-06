<?php

namespace App\Features\Donations\Http\Controllers;

use App\Features\Forms\Services\TurnstileFormVerifier;
use App\Integrations\EngageCore\Exceptions\EngageCoreDonationsException;
use App\Integrations\EngageCore\Services\HttpEngageCoreDonationsClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class SiteDonationController
{
    public function store(Request $request, HttpEngageCoreDonationsClient $core, TurnstileFormVerifier $verifier): RedirectResponse
    {
        abort_unless(config('site_donations.enabled') === true, 404);

        $validated = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'amount_option' => ['required', 'string', 'max:20'],
            'custom_amount' => ['nullable', 'string', 'max:16'],
            'external_id' => ['required', 'uuid'],
            'company_website' => ['nullable', 'size:0'],
        ])->validate();

        $allowed = array_values(array_filter(
            config('site_donations.suggested_amounts', []),
            static fn (mixed $value): bool => is_int($value) && $value > 0,
        ));
        $selected = $validated['amount_option'];
        $amount = null;
        if ($selected === 'custom') {
            $custom = trim((string) ($validated['custom_amount'] ?? ''));
            if (preg_match('/^([0-9]{1,7})(?:\.([0-9]{1,2}))?$/D', $custom, $parts) === 1) {
                $amount = (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
            }
        } elseif (ctype_digit($selected) && in_array((int) $selected, $allowed, true)) {
            $amount = (int) $selected * 100;
        }

        $minimum = max(500, (int) config('site_donations.minimum_amount_cents', 500));
        $maximum = min(100000000, max($minimum, (int) config('site_donations.maximum_amount_cents', 100000000)));
        if (! is_int($amount) || $amount < $minimum || $amount > $maximum) {
            return back()->withErrors(['amount_option' => 'Choose a valid donation amount.'])
                ->withInput($request->except('cf-turnstile-response'));
        }

        // Use the existing rigorously validated SEO-side Turnstile verifier.
        // The Core API receives only a signed, fresh successful attestation.
        $action = (string) config('site_donations.verification_action', 'donations');
        if ($action !== 'donations' || ($verified = $verifier->verify($request, $action)) === null) {
            return back()->withErrors(['human_verification' => 'Please complete the verification.'])
                ->withInput($request->except('cf-turnstile-response'));
        }

        try {
            $url = $core->checkout([
                'external_id' => strtolower($validated['external_id']),
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'amount_cents' => $amount,
                'verification' => $verified->toArray(),
            ]);
        } catch (EngageCoreDonationsException $exception) {
            if ($exception->kind === 'validation' && $exception->fields !== []) {
                return back()->withErrors($exception->fields)
                    ->withInput($request->except('cf-turnstile-response'));
            }
            report($exception);
            return back()->withErrors(['donation' => 'Payment checkout is temporarily unavailable. Please try again.'])
                ->withInput($request->except('cf-turnstile-response'));
        }

        return redirect()->away($url);
    }

    public function returned(Request $request, string $token, HttpEngageCoreDonationsClient $core): RedirectResponse
    {
        abort_unless(config('site_donations.enabled') === true, 404);
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/D', $token) === 1, 404);

        $checkoutCancelled = $request->query('checkout') === 'cancelled';
        $status = 'pending';
        try {
            $result = $core->status($token);
            $status = $result['status'];
        } catch (EngageCoreDonationsException $exception) {
            report($exception);
        }

        return redirect()->to((string) config('site_donations.page_path', '/donate'))
            ->with('site_donation_return', [
                'status' => $status,
                'cancelled' => $checkoutCancelled,
                'retry_url' => '/_site-donations/return/'.$token,
            ]);
    }
}