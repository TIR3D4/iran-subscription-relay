# Development

PHP 8.1+ with cURL; Python 3 for HTTP integration tests. Run PHP lint on all PHP files, `php tests/relay-test.php` and `python3 tests/http-test.py`. CI runs PHP 8.1 and 8.3. Tests use synthetic tokens, never live accounts.

`relay.php` validates operator-controlled URLs and performs HTTPS requests. `index.php` restricts public paths to `/sub/TOKEN[/safe-suffix]`, forwards query strings and a small header allowlist, and returns the body unchanged. No incoming Host, cookies, Authorization or client-controlled destination is forwarded. `installer.php` authenticates with a manually provisioned setup key, checks session CSRF, previews settings and creates config once under a lock.

Limits: dedicated domain root; no dashboard, assets or arbitrary API proxy; no automatic HTML rewriting; no HTTP/self-signed origins; no redirects; no HWID-specific forwarding; no caching or database. Upstream templates are trusted content. Some panel versions or custom templates may need adaptation. Traffic limits apply to decoded response bytes.

The installer can probe operator-configured destinations after key authentication. Deploy only where outbound destinations are under the operator's control. Requests do not forward credentials. Session/security behavior and rewrite protection must be checked on the real host.

To package for cPanel, zip the contents of `src/`, including `.htaccess`, excluding actual `config.php`, `setup-key.php` and lock files. The repository ZIP also contains examples and docs; only `src/` contents belong in Document Root.
