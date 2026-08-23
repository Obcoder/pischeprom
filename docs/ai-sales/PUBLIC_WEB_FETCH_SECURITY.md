# Public Web Fetch Security

`SafePublicPageFetcher` is a bounded server service, not an arbitrary browser URL endpoint and not an AI tool. It accepts only a persisted, non-duplicate `ProspectingSearchResult` whose URL/domain hashes and untrusted markers still match provenance.

## Request path

```text
persisted normalized SearchResult
  → URL/provenance validation
  → public DNS validation
  → per-domain budget reservation
  → robots.txt
  → pinned exact-host HTTP(S) fetch
  → bounded text/channel extraction
  → DLP
  → safe evidence persistence
```

## Network guards

- only `http`/`https`, default ports and credential-free URLs;
- localhost, `.local`, metadata hosts, literal private/reserved/link-local IPs blocked;
- all DNS answers must be public;
- DNS is re-resolved before/after robots and page requests;
- curl `CURLOPT_RESOLVE` pins the validated address; absence of pinning support fails closed;
- redirects are handled manually, capped at 2, re-resolved and restricted to the exact original host;
- relative or cross-host redirects are blocked;
- TLS verification remains enabled;
- no cookies, auth, form submission, JavaScript or login flow;
- no retry or fallback.

`RegistrableDomainResolver` supports grouping/deduplication only. It is not an authorization boundary: redirects, same-page links and Candidate evidence aggregation use exact normalized host checks.

## Content guards

- response content type: `text/html` or `text/plain` only;
- PDF, Office, images and other binary formats blocked;
- identity encoding only; compressed response blocked;
- Content-Length and actual body capped at 524,288 bytes;
- visible text capped at 24,576 characters/bytes policy bound;
- DTD, ENTITY, active HTML, control material and prompt-injection markers blocked;
- benign exact `<!doctype html>` is accepted;
- robots body capped at 65,536 bytes and must be plain text;
- connect timeout 3 seconds, total timeout 10 seconds;
- maximum 5 page reservations per registrable-domain cache window.

Query parameters are reduced to a small public-navigation allowlist; tracking and unknown/token-like parameters are dropped before persistence/fetch.

## Persistence

`prospecting_public_fetches` stores only final safe URL/hash, content type/count, redacted title/description/headings/text, exact-host links, hashes, safe status/error taxonomy and timestamps. Full HTML and HTTP/provider bodies are never persisted.

Extracted public email/telephone values are removed from all clear-text excerpts. They are stored in the encrypted `protected_channels` cast and later remain `review_required`. Evidence is always marked:

```text
trust_level=untrusted
instruction_authority=none
```
