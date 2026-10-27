# Original 1.5.50 claim checks

These checks run only in the isolated `xf_wmtech_claims` project. They compare the
unchanged supplied 1.5.24 and original 1.5.50 archives. They do not use 1.5.51 core
files. `bootstrap.php` reuses the standalone read-only bootstrap helper and adds
native lazy loaders for instrumented CLI methods; actual web requests use normal
native initialization.

`static.py` binds archive hashes and counts XML entries, hooks, markers and omitted
files. `customizations.php` seeds native child templates, an add-on modification and
a translated phrase, then runs the original native upgrade and rebuild.
`runtime.php` exercises original methods with synthetic data and reports warnings
with E_ALL enabled. Its object hook only increments an in-memory counter.
`password-reset.php` routes one real reset message into a private local file and
restores the authentication bytes. It forces a private RNG buffer only to measure
the existing trim boundary; it does not measure production unpredictability.

`transport.php` uses an internal OpenSSL server. Its normal PayPal path uses the
native socket adapter. Disabling `stream_get_transports` in a separate PHP CLI
process selects the genuine native cURL fallback; its warnings belong to that test
control. Disabling only `curl_version` tests the unpinned-detection fallback, not an
uninstalled extension. No PayPal endpoint or payment callback is called.

`lint.php` checks every shipped PHP file, including dead code, without suppressing
deprecations. `http-checks.py` tests native cookie headers, rendered customizations
and rgba expiry. Cookie cases use separate immutable private configuration folders;
they do not rewrite the forum's configuration between live requests. Native phrase
and template compilation runs before rendering the seeded second language.
`browser-probe.html` compares full and minified libraries in isolated browser frames
with jQuery ready held. It creates and clears a synthetic prototype-pollution marker
inside each frame's own JavaScript realm. It does not initialize forum AJAX/widgets.

Author-harness corrections made before accepting final evidence include lazy-loader
registration, a complete restore barrier before fixture seeding, nontransactional
session-row cleanup, exact native method/model names, lazy HTTP-adapter setup,
the shipped rollup path and same-realm browser pollution controls. Initial harness
failures are not XenForo defects. Private mail, keys, config and raw HTML stay ignored.

`panel-followup.php` closes the panel's configuration-only session-binding gap with
native IPv4/IPv6 acceptance checks. It also distinguishes pre-existing writer
rejection from the new reader false positive, and inspects generated config only in
memory. The 60 IP decisions are separate from the original 100 completed probe cases.

Recheck the saved evidence from the repository root:

```sh
python3 wmtech/tests/claims-1.5.50/static.py
python3 wmtech/tests/claims-1.5.50/verify-evidence.py
```

The private lab compose file and synthetic snapshots remain under
`instances/wmtech-claims/`. With those fixtures running, native leaf probes use:

```sh
docker compose -p xf_wmtech_claims -f instances/wmtech-claims/compose.yml exec -T \
  -e XF_CLAIMS_ROOT=/claims/wmtech-webroot php74 \
  php /wmtech/tests/claims-1.5.50/panel-followup.php
```

Select `/claims/base-webroot` for stock code; select `php73` for the 7.3 runtime.
The cookie matrix additionally needs the local `claims-cookie.php` endpoints and
the browser probe needs its local HTML copy. Fixture seeding is for a fresh restored
synthetic database. Do not rerun it over an already-seeded fixture.
