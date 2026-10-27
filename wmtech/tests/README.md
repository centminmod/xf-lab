# 1.5.51 synthetic lab checks

These author-written harnesses stay outside the release ZIP. They use the isolated
`xf_wmtech_1551` Docker project in `instances/wmtech-1551`, with `/review` mapped to
that directory and `/wmtech` mapped read-only to this directory's parent. Never
point them at a live forum. The fixture uses disposable test accounts and keys.

`xf1-bootstrap.php` requires CLI, the `/review/.xf1551-synthetic-lab` marker and an
allowlisted synthetic database. Set `XF_FORUM_ROOT` for another lab clone.

The recorded sequence is stock 1.5.24 on PHP 7.2.34, original 1.5.50 on PHP 7.4.33,
then the built 1.5.51 payload, followed by native 2.3.13 on PHP 8.3.36. The separate
MySQL 8.4 scratch service tests both tag paths. `seed.php` creates the fixture after
a stock installation; private baseline SQL and file snapshots remain in the ignored
lab directory. They contain only synthetic data but are not release artifacts.

Run XF1 checks before merging XF2 files. `serialization.php`, `queue-cases.php`
and `queue-worker.php` cover unsafe values, competing claims, errors and shutdown
recovery. `http-options.php` checks option preservation and pinned HTTP policy.
`io-checks.py` covers preparation, secret-free read-only preflight and cleanup.
`mail-drain.php` enables only a local file transport for the preserved business job.
`native-health.php`, `lint.php` and the HTTP scripts cover native integrity and flows.

`final-chain.py` replays the full sequence from the already-created synthetic
baseline into a new `xf_final` database/root. It intentionally refuses an existing
root. It calls `native-xf2.py`, which invokes the actual `cmd.php xf:upgrade` command.
`backup-restore.py` proves a matched 1.5.51 restore before the XF2 overlay.
`preflight-adversarial.py` adds and removes its probes on that restored clone.
`xf2-config.php`, `xf2-state.php`, `xf2-contracts.php`, `setup-tls.py` and
`xf2-http.py` check native translation, data/auth/file continuity, URL rejection and
certificate-verified loopback HTTPS. The test certificate is public; its private
key stays inside the disposable container. Runtime image source is pinned in
`php83.Dockerfile`. Production proxies, add-ons and live integrations need separate
site-specific tests.

The endpoint comparison adds `direct-xf2.py plain` and `direct-xf2.py prepared`.
Both restore the same stock baseline and complete the same synthetic envelope and
provider-cache fixture fields used by the staged replay. The prepared route also
pins the stock salt, maps cookies, aligns the fixture URL and drains mail before
native XF2 conversion. Neither installs wmtech core code.

`path-state.php` captures private per-table, config and permission snapshots before
HTTP logins. `path-compare.py` reports every raw difference. `path-registry.php`
binds selected decoded cache entries to their source bytes; `path-semantic.py`
compares natural phrase identities and the declared metadata fields. Its negative
controls check that user, auth, provider, phrase and cron-rule changes are detected.
`path-http.py` then checks both accounts and attachment downloads on all three
endpoints over trusted loopback TLS. Later logins can legitimately rehash passwords
and create session/activity metadata, so raw comparisons use the earlier snapshots.
`cleanup-boundary.py` proves the XF1 cleanup refuses a tree containing XF2 files.

`path-residuals.py` verifies the exact remaining normalized option/cache fields,
registry key order, file-set accounting and original snapshot fingerprints.
`source-inventory.py` replays only the source stages into a new disposable clone,
counts every XF1 table before conversion and compares linked-provider identities
with the original target snapshots. It also inserts native queued mail, proves
preflight detects it without database/config changes and delivers it to one local
file. The first inventory attempt needed a checker correction for integer/string
database scalars; the corrected source-only replay passed. Empty business tables
do not validate conversion of nonempty likes, moderation or payment history.

The endpoint comparison is taken before HTTP logins and before native XF2
post-upgrade jobs finish. Required native upgrade/rebuild commands completed, but
the queued jobs' eventual convergence is outside this comparison. Keep the original
snapshots; never overwrite them with a later authenticated state.
