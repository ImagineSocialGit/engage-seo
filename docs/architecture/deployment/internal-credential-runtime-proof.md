# Internal credential runtime proof

The console-only `engage:credential-runtime:inspect` command supports two modes:

- `--inventory --expected-client=KEY --expected-environment=ENV` lists active
  package-owned runtime declarations, without returning credential values.
- `PURPOSE --caller-id=CALLER --challenge=NONCE --expected-client=KEY
  --expected-environment=ENV` returns a challenge-bound HMAC using the effective
  credential from the package's registered configuration path.

The existing `SeoInternalCredentialDefinition` identifies each package's actual
consumer configuration path and its three client-owned environment keys. No
new package-specific switch is added to SEO. Existing package registration is
sufficient; packages remain responsible for keeping their declared consumer
path aligned with their actual request transport.

The probe reuses the existing installation contract to compare the effective
caller identity, destination origin, and signing credential against their
persisted selected-client assignments. Missing registration, inactive features,
unowned keys, incomplete assignments, root-scoped values, cached old values,
invalid credentials and conflicting caller identities all block proof. No
secret or reusable unkeyed secret fingerprint appears in a response.

The command validates the actual selected client and environment before
constructing any client path, reads only package-declared configuration, and
never accepts a configuration path or environment key from CLI arguments.
Errors return fixed diagnostics, never underlying exception messages.

## Wire protocol

The controller generates a fresh, unpredictable 32-byte challenge, encoded as
64 lowercase hexadecimal characters, for each verification attempt. The
canonical UTF-8 message consists of these fields separated by one LF, with no
trailing LF:

1. `engage-internal-runtime-v1`
2. Challenge
3. Responding platform (`core` or `seo`)
4. Responding selected-client key
5. Environment (`local`, `staging`, or `production`)
6. Package-owned purpose
7. Peer platform (`seo` for Core, `core` for SEO)
8. Caller identity
9. Source site origin
10. Core destination webhook origin

The response's `proof` is lowercase hexadecimal HMAC-SHA256, keyed by the
runtime credential. The challenge is public; a response must never be accepted
by status alone. The controller independently checks every identity field and
computes the expected MAC with its existing protected credential record using
constant-time comparison. Use separate challenge attempts and verify each
platform's context. Never log proofs, private runtime snapshots, or keys.

## Verification boundary

A valid proof establishes credential use in a newly booted console runtime.
It does not establish PHP-FPM or long-running worker configuration, web-server
routing, DNS ownership, authenticated HTTP traffic, or successful checkout.
`credential_equality_verified` remains false in the platform report because
only the controller can compare the peer reports with protected material.
`network_verified` and `writes_allowed` always remain false.

This operation does not rebuild configuration caches, edit environments,
generate or rotate keys, write database records, or call payment providers.
If a cached runtime disagrees with persisted configuration, verification must
fail and the normal deployment workflow must refresh that cache explicitly.