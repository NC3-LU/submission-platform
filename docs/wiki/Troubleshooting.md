# Troubleshooting

Start with the symptom that matches what you see. Respondents should contact the form owner for access or answer corrections; operators can investigate the underlying service.

## Using forms

| What happens | What to check or do |
| --- | --- |
| I cannot find a form | Private forms are not in the public catalogue. Use the owner's link. The owner should check that the form is published. |
| The form asks me to sign in | It may require an authenticated account. Sign in, or confirm the intended access with the owner. |
| My private link no longer works | It may have expired or been revoked. Ask the owner for a new link. |
| A published form is not accepting responses | Check its opening and closing dates. Publication alone does not override those dates. |
| I cannot find a draft | Draft saving requires sign-in. Use the same account and reopen the form or **My Submissions**. Guests do not have persistent drafts. |
| I cannot edit a submitted response | Answers are fixed after submission. Contact the owner about a correction. |
| A file is rejected | Check its type and the 10 MiB limit. Renaming the extension does not bypass the type check. |
| An attachment is pending or blocked | Scanning may still be running, have failed, or have found a problem. An operator can check the worker and Pandora. |
| I cannot change the questions | A response, including a saved draft, already exists. Duplicate the form to revise its structure. |
| I cannot see someone else's draft | Drafts are restricted to their author and administrators. |

## The completed-response filter is empty

The browser list hides completed responses by default, and currently has no separate control to enable them. On the form's submissions-list URL, add `?showCompleted=1`, or `&showCompleted=1` if it already has a query string. Then select **Completed** in the status filter.

This is a current interface limitation. It does not remove completed responses from authorized form-wide exports or API results.

## I cannot open the admin panel

The current application requires all three: the `admin` role, a verified email address, and an address ending in `@nc3.lu`. Check these before treating a 403 response as a server failure. Other organizations need to adapt the panel access rule in `app/Models/User.php`.

For form creation alone, a verified internal or external evaluator account can use **My Forms** without entering the admin panel.

## Local installation problems

| Symptom | Operator or developer check |
| --- | --- |
| `app:create-user` is not found | Use `php artisan user:create --role=admin` or `--role=internal_evaluator`. |
| Composer reports an incompatible platform | Check PHP 8.3+ and the required extensions with `composer check-platform-reqs`. |
| The page has no built styles | Run `npm ci` and `npm run build`, or keep the Vite development server running while developing. |
| Form banners are missing | Check `APP_URL`, public storage, and `php artisan storage:link`. |
| Database or missing-table error | Check the database settings and apply migrations to the intended local database. |
| Verification or password-reset mail never arrives | With `MAIL_MAILER=log`, mail is written to `storage/logs/laravel.log`. For a live service, check the actual mail transport. |
| Docker starts but localhost does not show the app | The default Compose file expects routing over `dokploy-network`; it does not publish a host application port. |
| API documentation is denied | Check the signed-in account's email domain, `API_DOCS_ALLOWED_DOMAINS`, and saved API settings. The test-only public setting still applies to authenticated users. |

## Exports or webhooks are stuck

Run `php artisan app:health --full` and inspect `php artisan queue:failed`. Confirm that the worker and scheduler use the current release, database, and storage. Integrations need an asynchronous queue; running them inline with the `sync` connection is not supported.

An export link expires 24 hours after its request. Request a new export if necessary. Check that the requesting account still has access and that the issuing token was valid when generation ran.

Webhooks must be enabled, their endpoint must still be active and authorized, and the receiver must satisfy the public HTTPS destination rules. Inspect safe delivery status and error codes through the API. Use the [API contract](https://github.com/NC3-LU/submission-platform/blob/main/docs/api.md) before changing retry behavior.

## A deployment failed or the service is in maintenance

Read the deployment output and follow the [operations runbook](https://github.com/NC3-LU/submission-platform/blob/main/docs/operations.md). A failed deploy intentionally may keep maintenance enabled. Resolve the failed migration, readiness check, or background job before reopening traffic.

Retain the current database, storage volumes, and `APP_KEY`. Recovery procedures distinguish an application rollback from restoring data.

## Where to ask for help

For a particular form, start with the team that shared it. For general project enquiries, contact **info@lhc.lu**. Report security issues privately through [GitHub Security Advisories](https://github.com/NC3-LU/submission-platform/security/advisories) or **abuse@lhc.lu**; direct data-protection enquiries to **privacy@lhc.lu**.

When reporting an ordinary fault, include the page, approximate time, expected behavior, and exact error message. Remove tokens, private access links, personal answers, and credentials from any examples.

Next: [Project overview](https://github.com/NC3-LU/submission-platform/wiki/Home)
