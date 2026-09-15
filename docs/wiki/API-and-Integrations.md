# API and integrations

The API lets another application create or read forms, submit responses, manage collaborators, and retrieve results. It lives under **`/api/v1`**. This page is an introduction; use the [API contract](https://github.com/NC3-LU/submission-platform/blob/main/docs/api.md) for exact payloads, limits, and error handling.

Interactive documentation is available at `/docs/api` on your installation, subject to its documentation access policy.

## Start with a token and a read request

Ask an authorized administrator to issue an application API token for the account your integration will use. Tokens can also be managed through the API by a caller with `tokens:manage`. Copy a newly issued or rotated token immediately: its plaintext value is shown only once.

Give the token only the abilities the integration needs. A token also remains subject to its account's form permissions, expiry, revocation, and any IP restriction. For example, `forms:read` does not grant access to every private form.

Send the token in the `Authorization` header. The example below assumes `SUBMISSION_API_TOKEN` has been supplied by your local secret-management process; replace the example hostname with your installation:

```bash
curl --fail-with-body \
  --header 'Accept: application/json' \
  --header "Authorization: Bearer ${SUBMISSION_API_TOKEN}" \
  'https://submission.example.org/api/v1/me'
```

`GET /me` returns safe information about the current account and token. It is a useful first check because it needs a valid token without an additional route ability.

## Common tasks

Paths below are relative to `/api/v1`.

| Task | Endpoint |
| --- | --- |
| List forms owned by the token's account | `GET /forms` |
| Read a form and its questions | `GET /forms/{form}` |
| Submit answers | `POST /forms/{form}/submissions` |
| Read submitted responses you can access | `GET /forms/{form}/submissions` |
| Duplicate a form | `POST /forms/{form}/duplicate` |
| List or manage collaborators | `/forms/{form}/users` |
| List or manage private access links | `/forms/{form}/access-links` |
| Request a background export | `POST /forms/{form}/exports` |
| Configure event notifications | `/webhook-endpoints` |
| Revoke the current token | `DELETE /me/token` |

## Send answers

Fetch the form first. Answer keys are its actual field IDs, not question labels or positions:

```json
{
  "values": {
    "12": "Example answer",
    "13": ["Security", "Usability"]
  }
}
```

Replace the example IDs and choice values with the form's definitions. Required fields, allowed choices, character limits, and conditional questions follow the same rules as the browser. File answers must use `multipart/form-data` with `values[FIELD_ID]`; a storage path string is not an upload.

There is currently no client-supplied idempotency key for response creation or form duplication. If a POST times out after it may have succeeded, reconcile the result before retrying to avoid creating duplicates.

## Download attachments and exports

A file answer is an object containing `filename`, `scan_status`, `download_blocked`, and `download_url`, or `null` when the reference is unavailable. The URL still requires bearer authorization and permission to read the submission. A blocked scan produces HTTP 423; a missing file produces 404.

For a background export:

1. POST `{"format":"json"}` or `{"format":"xlsx"}` to `/forms/{form}/exports`.
2. Poll `/forms/{form}/exports/{export}` using the returned export ID.
3. Download the file when its status is `completed`.

Export creation requires `submissions:export` and form export permission. Export records belong to the requesting account. Exports omit draft and ongoing responses, and their files expire **24 hours after the request**. The queue worker and scheduler must be running. Attachment links inside an export additionally require `submissions:read` when followed.

## Receive webhook notifications

Webhooks notify a receiver when a response is submitted, a response status changes, or a form status changes. They are disabled by default; an operator must enable `WEBHOOKS_ENABLED` and the background processes.

The receiver must use a public HTTPS hostname on port 443. Events contain identifiers and statuses, not answer contents or attachments. Fetch authorized response data through the API when needed.

Verify each request's signature using the endpoint secret and the raw request body. Check the timestamp and record the delivery ID so duplicate attempts do not repeat your work. Events can be repeated or arrive out of order. Use the [webhook contract](https://github.com/NC3-LU/submission-platform/blob/main/docs/api.md#webhooks) for the signature algorithm, example verification code, retry schedule, and quotas.

## Handle common responses

| HTTP status | What to check |
| --- | --- |
| 401 | Missing, expired, revoked, or otherwise invalid authentication |
| 403 | Token ability, account permission, or IP restriction |
| 404 | Resource ID, parent form, or whether the resource is visible to this caller |
| 409 | Resource not ready or operation not currently allowed |
| 410 | Export has expired |
| 422 | Validation errors in the response body |
| 423 | Attachment is blocked by its scan state |
| 429 | Request rate or resource quota; use `Retry-After` when supplied |

New clients should use `/api/v1/me` and the plural `/forms/{form}/access-links` paths. The legacy `/api/user` route and singular access-link aliases are scheduled to be removed on **1 April 2027**. Legacy Sanctum credentials are separate from the application's API v1 tokens.

Next: [API contract](https://github.com/NC3-LU/submission-platform/blob/main/docs/api.md) · [Development](https://github.com/NC3-LU/submission-platform/wiki/Development)
