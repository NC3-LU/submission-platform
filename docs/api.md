# API contract

The API base is `/api/v1`. Interactive OpenAPI documentation is at `/docs/api`, subject to the configured documentation access policy. Generate a document against a migrated disposable database with `php artisan scramble:export --path=/tmp/submission-openapi.json`; schema introspection needs the tables to exist.

## Authentication and authorization

Send `Authorization: Bearer <token>`. The OpenAPI document declares bearer authentication. API tokens are hashed at rest; the plaintext token is shown when issued or rotated. Preserve the existing token lifecycle and audit-event controls when managing credentials.

Every operation requires its route ability (`forms:read`, `forms:create`, `forms:update`, `forms:delete`, `submissions:read`, `submissions:create`, `submissions:update`, `submissions:delete`, or `tokens:manage`) and the relevant account/form permission. IP restrictions, expiry, revocation and configured quotas also apply. Tokens do not confer administrator rights on an ordinary account.

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

Lists accept validated status/date filters and `per_page` from 1 to 100. Rate limits return HTTP 429 with `Retry-After`; valid tokens use the authenticated bucket. Forbidden access returns 403, missing resources 404 and invalid payloads 422.

The browser uses a server-issued submission key to prevent duplicate responses when the same submission request is replayed. API clients should avoid automatic retries of an ambiguous successful POST: API creation currently has no client-provided idempotency-key contract. Resolve uncertain delivery by checking the response and integration logs before retrying.
