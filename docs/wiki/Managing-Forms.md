# Managing forms

Form owners build the questions, control access, and organize reviewers. Creating forms requires an administrator, internal evaluator, or external evaluator account. A regular respondent account cannot create forms.

## Create your first form

1. Sign in with a verified account and open **My Forms**.
2. Choose **Create Form**. Enter a clear title and explain who should respond and what information they need.
3. Choose the visibility and add categories. A category is simply a section, such as “Contact details” or “Supporting documents.”
4. Save the new form. It starts as a draft.
5. In **Form Fields**, add and arrange the questions within each category.
6. Use **Preview Form** to check the questions and conditional rules.
7. In **Settings**, set any availability dates, change the status to **Published**, and select **Save Settings**.

Use the preview while designing. Saving even a draft response creates a response record and locks the question structure. Test actual submissions on a disposable copy when you still expect to revise the questions.

## Choose the right question type

| Type | Use it for |
| --- | --- |
| Text | A short answer, such as a name or reference number |
| Textarea | A longer explanation |
| Select or radio | One answer from a list |
| Checkbox | One or more choices |
| File | A supporting attachment |
| Header or description | Instructions or context; these do not collect answers |

Mark questions as required only when the answer is necessary. Text questions can have character limits. Conditional rules can show a follow-up question when a particular earlier answer is selected.

Descriptions support Markdown, so you can use paragraphs, lists, emphasis, and links. An optional form banner accepts JPG, PNG, or WebP up to 4 MB; its position and accent colour can be adjusted after saving. Banners are public assets, so use an image suitable for public display.

## Decide who can respond

| Visibility | Who can open the published form to respond? |
| --- | --- |
| Public | Anyone, including guests |
| Authenticated Users Only | People who sign in |
| Private | The owner, administrators, assigned users, or people using a valid access link |

Visibility and publication are separate settings. A private form still needs to be published before it accepts responses. Opening and closing dates apply as well.

Create private links under **Access Links** in the form's access settings. You can give a link an expiry date and delete it when access should end. These links grant form access, not reviewer permissions. Email-address verification by a one-time invitation code is not implemented.

## Work with reviewers

Use **Assigned Users** to add eligible colleagues to a form. Reviewers must have their own accounts. Assign view access when they need to read submitted responses; grant edit access when they also need to edit the form. Assigned internal and external evaluators can also export responses through the browser, even without edit permission.

The owner and administrators manage sharing. In the browser, an assigned internal evaluator with edit access can also assign or remove users. API collaborator management is restricted to the owner and administrators. See [API and integrations](https://github.com/NC3-LU/submission-platform/wiki/API-and-Integrations) for the API's viewer/editor rules.

## Change or close a form

Once any response exists, the questions and sections are locked to preserve the meaning of saved answers. Duplicate the form to change its structure. The copy starts as a draft and has its own questions; responses, access links, collaborators, and availability dates are not carried over. Review its access settings before publishing it.

Set the status to **Archived** to stop accepting responses while keeping existing records. Deleting a form removes its records and schedules attachment cleanup; use archiving when you need to retain responses.

If an owner leaves, an operator can transfer their forms to another verified evaluator or administrator account. See [Running the platform](https://github.com/NC3-LU/submission-platform/wiki/Running-the-Platform).

Next: [Reviewing submissions](https://github.com/NC3-LU/submission-platform/wiki/Reviewing-Submissions) · [Project overview](https://github.com/NC3-LU/submission-platform/wiki/Home)
