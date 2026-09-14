# API contract

The API base is `/api/v1`. Interactive OpenAPI documentation is at `/docs/api`, subject to the configured documentation access policy. Generate a document against a migrated disposable database with `php artisan scramble:export --path=/tmp/submission-openapi.json`; schema introspection needs the tables to exist.

## Authentication and authorization

Send `Authorization: Bearer <token>`. The OpenAPI document declares bearer authentication. API tokens are hashed at rest; the plaintext token is shown when issued or rotated. Preserve the existing token lifecycle and audit-event controls when managing credentials.

Operations require their route ability (`forms:read`, `forms:create`, `forms:update`, `forms:delete`, `submissions:read`, `submissions:create`, `submissions:update`, `submissions:delete`, `forms:share`, `submissions:export`, `webhooks:manage`, or `tokens:manage`) and the relevant account/form permission. The two `/me` operations explicitly require authentication only and operate exclusively on the caller. IP restrictions, expiry, revocation and configured quotas also apply. Tokens do not confer administrator rights on an ordinary account.

Published forms accept responses only within their availability window. Authenticated visibility accepts a valid authorized API identity; private visibility additionally requires form ownership, administrator access or a current form assignment. Private guest access links are a separate browser flow.

Draft and ongoing responses belong to their submitter. Other evaluators and form owners cannot read them through listings, individual responses or downloads; administrators can inspect them. Exports contain completed submission actions (all review statuses), excluding draft and ongoing responses. Submitted responses are visible to their author, the form owner, appointed evaluators and administrators. Edit permission is required to manage review status. Authors cannot alter a response after submission.

## Answer payloads

`POST /forms/{form}/submissions` accepts `values`, an object keyed by field ID. Fetch the form first to obtain its current field definitions. IDs outside that form are ignored; display-only and currently hidden fields are not saved. Required, option, character-limit and conditional-visibility validation use the same contract as browser submissions. Validation failures return HTTP 422 with field errors.

```json
{
  "values": {
    "12": "Example answer",
    "13": ["Security", "Usability"]
  }
}
```

Checkbox fields accept an array of selected option strings. Indexed boolean selections such as `[true, false]` are also supported for compatibility with the browser. A required group must contain at least one selected option. Stored responses and existing JSON exports retain comma-separated selections such as `Security, Usability`; they are not booleans.

For attachments use `multipart/form-data` and put the uploaded file in `values[FIELD_ID]`. Storage path strings are rejected. Maximum file size is 10 MiB; accepted extensions are jpeg, jpg, webp, svg, png, pdf, doc, docx, xls, xlsx and md, with server-side MIME validation. Files remain on the private disk. When enabled, malware scans run after commit and download gating blocks pending, failed or malicious scans.

## Review states and errors

Supported stored states are `draft`, `ongoing`, `submitted`, `under_review`, `processing`, `approved`, `rejected` and `completed`. The status update endpoint accepts review states only. It cannot promote an author's draft or reopen a submitted response for answer editing. Existing API review states are retained by the Laravel 13 migration without resetting stored values.

Lists accept validated status/date filters and `per_page` from 1 to 100. Request rate limits return HTTP 429 with `Retry-After`; resource capacity limits return 429 with a quota message; valid tokens use the authenticated bucket. Forbidden access returns 403, missing resources 404 and invalid payloads 422.

The browser uses a server-issued submission key to prevent duplicate responses when the same submission request is replayed. API clients should avoid automatic retries of an ambiguous successful POST: API creation currently has no client-provided idempotency-key contract. Resolve uncertain delivery by checking the response and integration logs before retrying.

## Token context and lifecycle history

`GET /me` returns `data.user` (ID, name, email and role), `data.token` (safe token metadata including fingerprint, abilities and expiration), and `data.limits.requests_per_minute` from the configured authenticated limiter. It requires any valid application API v1 token, including one without `tokens:manage`. No plaintext credential or stored hash is returned; the response is marked `no-store, private`.

`DELETE /me/token` revokes only the bearer credential used for that request, records a lifecycle event transactionally and returns 204. Input cannot select another token. Repeating the operation with the revoked credential returns 401; it does not create another revocation event.

`GET /token-events` requires `tokens:manage` and returns only events for the authenticated account. Filters are `action`, `token_id`, `from`, `to`, `page` and `per_page` (1–100, default 25). Dates use the application timezone; an end date without a time includes that whole day. Results are newest-first, with ID as a stable tie-breaker. Reversed date ranges and invalid filters return 422. Responses expose an event ID/action/time and an immutable token ID/name/fingerprint snapshot. Raw audit metadata, request headers and IP addresses are not returned.

Actions are `created`, `updated`, `rotated`, `revoked`, `used` and `expired`. A `used` event begins a usage session after at least one idle hour; the token's usage count and last-used time still track individual authorized operations. Expiration is recorded once per expiration value when an expired credential is presented. Events remain available after the target token is deleted. There is no automatic event-expiry schedule; operators must apply their approved audit-retention policy.

The legacy `/api/user` endpoint retains its existing Sanctum authentication and response contract during migration. It emits `Deprecation`, `Sunset: Thu, 01 Apr 2027 00:00:00 GMT` and a successor link to `/api/v1/me`. API v1 uses the application's `ApiToken` credentials, not the separate legacy Sanctum personal-access-token store. Legacy clients must obtain an appropriate v1 key before switching endpoints. The legacy route is scheduled for removal on **1 April 2027**.

## Batch token revocation

`POST /token-revocations` requires `tokens:manage` and allows **five requests/minute per account**, shared across that account's tokens. Supply exactly one mode:

```json
{"token_ids": [12, 13]}
```

```json
{"all_except_current": true}
```

Selected IDs must be distinct positive integers, with 1–100 IDs per request. Foreign and nonexistent IDs are ignored identically; no ownership information is returned. The caller is preserved unless selected-ID mode explicitly includes `"include_current": true` and the caller's ID. That opt-in is prohibited with `all_except_current`.

Both modes process at most 100 owned tokens transactionally with their audit events. The response is `data.revoked_count`, `data.current_token_revoked` and `data.has_more`. For `all_except_current`, repeat while `has_more` is true to finish a larger account safely. Concurrent issuance/revocation operations serialize on the account; a stale already-revoked token does not create a duplicate revocation event. An audit failure rolls back the entire batch.

## Form access-link routes

The canonical routes are `/forms/{form}/access-links` and `/forms/{form}/access-links/{access_link}`, named `api.forms.access-links.*`. Reads require `forms:read`; mutations require `forms:update`, plus the existing form-owner/editor policy. Deletion remains owner-only. Child links are scoped through their parent form.

The earlier singular `/form/{form}/access-links` paths remain compatibility aliases until **1 April 2027**, with identical controllers, validation, resources, abilities and throttling. They emit deprecation/sunset headers and a `Link` to the corresponding plural endpoint. New clients and generated route links must use the canonical plural paths.

## Submission files and scan verdicts

`GET /forms/{form}/submissions/{submission}/files/{value}` requires `submissions:read` and submission-view permission. All child IDs are scoped to their parents. The selector is a saved file-value ID; query-string filenames and paths have no effect. The response streams a private attachment with its detected MIME type, a safe filename, `nosniff` and `Cache-Control: no-store, private`.

File answers now contain an object with `filename`, `scan_status`, `download_blocked` and `download_url`, replacing the previous storage-path string. Invalid or removed file references return `null`. Consumers must migrate their file-answer parsing for this release. The download URL requires the same bearer authorization; it is not a public or signed storage URL. Missing, foreign and non-file values return 404. A valid file whose scan blocks access returns 423. Missing storage objects return a JSON 404.

The web and API use the same download policy: when Pandora and `PANDORA_BLOCK_MALICIOUS` are enabled, only an explicit clean result permits download. Pending, missing, failed and malicious results block access. With blocking disabled, scans provide warnings and retained files remain downloadable. Disabling scanning removes the scan gate. Malicious files already removed by an earlier blocking scan cannot be restored by changing the setting.

Authorized submission viewers can expand **View scan details** with a keyboard or pointer. New scans collect Pandora worker reports in addition to the overall verdict; worker-detail retrieval failure does not change a completed verdict or file-retention decision. Reports are escaped, credential fields are removed, and scanner access seeds/report links are not shown. Historical scans without stored details show a clear fallback. Worker reports are limited to 1 MiB and a separate ten-second request.

## Form duplication

`POST /forms/{form}/duplicate` requires `forms:create` and the existing duplicate policy: owner, administrator, or appointed evaluator with edit permission. Unauthorized sources return 404. Success returns 201 and the independent form resource.

Copies include title (with ` (Copy)`), description, visibility, header colour/position, section descriptions/order, field labels/types/options/content/required flags/character limits/order and conditional rules. Conditional references point to the new fields. Text is preserved verbatim, including multilingual text; this application has no separate translation records. Referenced managed raster headers under `form-headers/` are copied to new random paths. Missing or unrecognized asset paths are omitted. No arbitrary disk path or external image is fetched.

The new form belongs to the caller, starts as a draft, and has no availability dates, responses, access links, collaborators or historical events. Database failure rolls back the new structure and cleans up newly copied assets. Limits are 100 sections and 1,000 fields; this route shares a ten-per-minute account mutation bucket with collaborator writes and export requests. `Idempotency-Key` is not currently supported: reconcile an ambiguous response before retrying a duplicate request.

## Collaborators

All `/forms/{form}/users` operations require `forms:share` plus owner or administrator authority. Editor access does not grant API sharing authority. Mutations share the ten-per-minute account mutation limit; membership is capped at 100 per form.

| Method and path | Payload / result |
| --- | --- |
| `GET /forms/{form}/users` | Paginated ID, display name and effective permission; `per_page` 1–100, default 25. |
| `POST /forms/{form}/users` | `{"user_id":123,"permission":"viewer"}`; 201 for a new membership, 200 for an existing membership. |
| `PUT` or `PATCH /forms/{form}/users/{user}` | `{"permission":"editor"}`; updates an existing member. |
| `DELETE /forms/{form}/users/{user}` | 204; access is removed immediately. Missing membership returns 404. |

The closed permission set is `viewer` (read/review according to the evaluator policy) and `editor` (add form editing, review-status management and asynchronous export permission). Existing internal/external evaluator accounts are eligible; global account roles are never changed. User IDs come from existing authorized account administration; there is no email/account search endpoint. Missing and ineligible accounts receive the same `This collaborator is unavailable` validation response. The list excludes email addresses and private account fields. Owner/self changes return 422, so the owner cannot be demoted or removed and there is always a manager. Cross-form memberships return 404. Identical grants do not duplicate membership or audit events; grants, permission changes and removals are transactionally audited.

## Asynchronous exports

Export operations require `submissions:export` plus the form export policy: owner, administrator, or appointed evaluator with edit permission. Export records additionally belong to their requester; another account, including another administrator, cannot fetch them.

| Method and path | Behavior |
| --- | --- |
| `POST /forms/{form}/exports` | `{"format":"json"}` or `{"format":"xlsx"}`; returns 202 with queued export metadata. |
| `GET /forms/{form}/exports/{export}` | Returns ID, format, status, row count, bytes, safe error code, timestamps and an authorized download URL when ready. |
| `GET /forms/{form}/exports/{export}/download` | Streams the completed private file; 409 while unavailable, 410 after expiry, 404 for missing/foreign resources. |

Status progresses from `queued` to `processing` to `completed` or `failed`, then `expired`. Errors are `access_revoked`, `limit_exceeded`, `generation_failed` or `worker_unavailable`; exception details and internal storage paths are excluded. Authorization and the issuing token's export ability/expiry are checked when generating, and current requester access is checked again on download. Credential revocation before processing cancels generation. Duplicate jobs do not overwrite completed artifacts.

Defaults: 10,000 reviewable responses, 100 fields, 50 MiB of uncompressed answer data/output, five records per iteration, 100 seconds of generation and a 120-second job timeout. Draft/ongoing responses are excluded. Responses created after the request are excluded; subsequent status changes are reflected at processing time, so this is not a database snapshot. JSON contains form ID/title and submission ID/status/time plus field ID/label/value. XLSX contains the same answer data in columns, with explicit string cells to prevent formula execution. File answers contain authorized download URLs, never file contents or storage paths; following those URLs additionally requires `submissions:read`. IP addresses and account-profile data are excluded.

Capacity limits: two queued/processing exports per account and per form; ten creations per account and twenty per form in a rolling day. Exceeding capacity returns 429; a form already exceeding row/field limits returns 422. Creation never generates inline and requires an asynchronous `INTEGRATION_QUEUE_CONNECTION` (default `database`). Run the normal queue worker and scheduler. Files expire **24 hours after request**, even if generation was delayed. `app:prune-integration-artifacts` runs hourly, removes expired artifacts and temporary packaging files, and fails jobs stalled for thirty minutes. Creation, completion, download, failure and deletion are recorded in `integration_events`.

## Webhooks

Webhooks are opt-in: set `WEBHOOKS_ENABLED=true` and run an asynchronous integration queue and the scheduler. Endpoint management requires `webhooks:manage`, endpoint ownership and form-owner/administrator authority. Endpoints persist independently of the management token; disable/delete them explicitly when retiring an integration. Loss of form management permission cancels queued delivery. Each endpoint subscribes to one form.

| Method and path | Behavior |
| --- | --- |
| `POST /webhook-endpoints` | `{"form_id":12,"url":"https://receiver.example.com/events","events":["submission.created"]}`; 201 with `data` and a one-time top-level `secret`. |
| `GET /webhook-endpoints[/{endpoint}]` | Safe endpoint metadata; list `per_page` 1–100 (default 25). |
| `PUT` or `PATCH /webhook-endpoints/{endpoint}` | Update URL, event subscriptions or boolean `enabled`; form and owner are immutable. |
| `DELETE /webhook-endpoints/{endpoint}` | 204; removes secret, endpoint and delivery history; immutable management audit remains. |
| `POST /webhook-endpoints/{endpoint}/rotate-secret` | Returns a new one-time `secret`; queued attempts use the current secret. |
| `GET /webhook-endpoints/{endpoint}/deliveries` | Paginated IDs, event type, status, attempt counts, safe HTTP/error codes and timestamps. No payload, response body or secret. |
| `POST /webhook-endpoints/{endpoint}/test` | Queues a signed `webhook.test` event (202) using the real delivery controls. |
| `POST /webhook-endpoints/{endpoint}/deliveries/{delivery}/redeliver` | One additional round for a failed delivery less than 24 hours old; preserves IDs and payload. 409 when ineligible. |

Supported subscriptions are `submission.created`, `submission.status_changed` and `form.status_changed`. Creation means a response first becomes reviewable; saving a draft emits nothing. Events contain only identifiers and statuses, never answers, email addresses, attachments or access links:

```json
{"id":"EVENT_UUID","type":"submission.status_changed","created_at":"2026-09-14T12:00:00+00:00","data":{"form_id":12,"submission_id":"SUBMISSION_UUID","status":"approved","previous_status":"submitted"}}
```

`form.status_changed` omits `submission_id`. A test event contains only `form_id`. One event ID is shared across its subscribed endpoints; each endpoint delivery has its own stable ID. Delivery is **at least once**: a worker failure can repeat an already received event. Consumers must deduplicate, and should tolerate events arriving out of order. Existing records are not backfilled. Application model events create deliveries; direct database writes bypass model events.

Every attempt includes `X-Webhook-Delivery`, `X-Webhook-Event`, `X-Webhook-Timestamp` (Unix seconds) and `X-Webhook-Signature`. The signature is `v1=` followed by lowercase hex HMAC-SHA256 over `timestamp + "." + raw_request_body`, using the endpoint secret. Verify the unmodified body before JSON decoding, compare in constant time, reject timestamps outside a five-minute window and persist the delivery/event ID before processing:

```php
$timestamp = $request->header('X-Webhook-Timestamp', '');
$signature = $request->header('X-Webhook-Signature', '');
abort_unless(ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 300, 401);
$expected = 'v1='.hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
abort_unless(hash_equals($expected, $signature), 401);
// Atomically claim X-Webhook-Delivery before applying the event.
```

Secrets are encrypted at rest and shown only on creation/rotation with no-store responses. Keep clocks synchronized. Rotation takes effect on the next attempt; coordinate the new secret with the receiver before resuming delivery. Changing a URL or disabling an endpoint cancels pending retries. An in-flight request cannot be recalled.

Destinations must use HTTPS on port 443, a DNS hostname and no credentials, query or fragment. IP literals, ambiguous URL syntax and non-public DNS answers are rejected. All A/AAAA answers are validated at creation/update and before every attempt; a validated address is pinned to the socket while TLS still verifies the hostname. Redirects and proxies are disabled. Limits are three seconds to connect, ten seconds total, 64 KiB response body and 16 KiB response headers; response contents are discarded. These controls follow the [OWASP SSRF prevention guidance](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html) and [cURL DNS pinning contract](https://curl.se/libcurl/c/CURLOPT_RESOLVE.html).

A 2xx response succeeds. Network failures, 429 and 5xx retry after 60 seconds, 5 minutes, 30 minutes and 2 hours, up to five attempts. Redirects, other 4xx, unsafe DNS and oversized responses fail immediately. Manual redelivery permits one further round, with a lifetime cap of ten attempts and a 24-hour delivery deadline. Statuses are `pending`, `delivering`, `retrying`, `delivered`, `failed` and `canceled`. The scheduler recovers lost queue dispatches and workers, retaining the same IDs. Delivery history expires after seven days; safe integration audit metadata has no automatic expiry.

Management/test/redelivery writes allow ten requests per account per minute. Caps are ten endpoints per account and five per form, 200 outstanding deliveries and 1,000 new deliveries per account in a rolling day. Delivery attempts are limited to thirty per endpoint and 120 per account per minute. When event generation exceeds capacity, that event is not queued and a bounded `webhook.quota_exceeded` audit event records the condition; clients should reconcile through the submission API. No customer response data is sent during automated tests.
