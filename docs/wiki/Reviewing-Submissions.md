# Reviewing submissions

Form owners, administrators, and assigned evaluators can read submitted responses. A person's unfinished draft stays hidden from other evaluators and the form owner; administrators can inspect drafts.

## Find and read a response

1. Open **My Forms** and find the form you own or have been assigned to.
2. Select **Submissions**.
3. Search for a submission ID or respondent, or narrow the list by status.
4. Open a response to read its answers and inspect its attachments.

Completed responses are hidden by default. The current interface needs a URL option to include them; see [the completed-response workaround](https://github.com/NC3-LU/submission-platform/wiki/Troubleshooting#the-completed-response-filter-is-empty).

## Understand the statuses

| Status | Meaning |
| --- | --- |
| `draft`, `ongoing` | The author is still preparing the response |
| `submitted` | The author has sent the response for review |
| `under_review`, `processing` | Review or follow-up work is in progress |
| `approved`, `rejected`, `completed` | A recorded review outcome or completion state |

These are supported labels, not an automatic sequence of approvals. The API can update review statuses when the caller has permission. The dedicated browser review page and full custom workflow builder are unfinished, so do not expect a complete approval or notification workflow in the interface.

## Download attachments

Attachments are stored privately and each download checks your permission to view the response.

If Pandora scanning and blocking are enabled, only a **clean** result allows a download. Pending, missing, failed, or malicious scan results block it. A failed scan means the check could not complete; it does not by itself prove that a file is malicious.

Use **View scan details** to inspect available scanner findings. Older results may not contain detailed reports. If a file remains pending, ask an operator to check the queue and scanner. If a malicious file has already been removed, disabling scanning will not bring it back.

If the operator disables blocking, retained files can be downloaded despite scan warnings. If scanning is disabled entirely, there is no scan gate.

## Export responses

| Need | Format |
| --- | --- |
| Readable copy of one submitted response | PDF |
| Structured data for one submitted response | JSON |
| Submitted responses for a form | JSON or XLSX spreadsheet |
| Export requested by an integration | Background JSON or XLSX export through the API |

Exports exclude draft and ongoing responses. Individual exports require permission to view the submitted response. Browser form-wide exports are available to the form owner, administrators, and assigned internal or external evaluators, including those without edit access. Background API exports require edit access for assigned evaluators.

Treat a form-wide export as an export of the form's submitted responses, not a copy of the currently filtered list. Depending on the export format, file answers contain filenames or authorized download links; they do not bundle attachment contents.

API exports are generated in the background and expire 24 hours after the request. See [API and integrations](https://github.com/NC3-LU/submission-platform/wiki/API-and-Integrations).

Next: [Troubleshooting](https://github.com/NC3-LU/submission-platform/wiki/Troubleshooting) · [Project overview](https://github.com/NC3-LU/submission-platform/wiki/Home)
