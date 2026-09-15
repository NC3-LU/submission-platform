# Publishing the GitHub wiki

The human-readable guide lives in [`docs/wiki/`](wiki/) and is published at [NC3 Submission Platform Wiki](https://github.com/NC3-LU/submission-platform/wiki). Keep edits in the source repository as well as the wiki so reviewers can see documentation changes with the code they describe.

GitHub stores a wiki in a separate Git repository. It becomes cloneable after its first page is created on the GitHub website. This project's wiki has been initialized. See [GitHub's guide to editing wiki pages](https://docs.github.com/en/communities/documenting-your-project-with-wikis/adding-or-editing-wiki-pages).

## Update the guide

1. Edit the relevant Markdown files in `docs/wiki/`.
2. Check the described screens, commands, configuration, and permissions against the current code.
3. Update the review date when reviewing the full guide. Add new pages to `Home.md`, `_Sidebar.md`, and the local [documentation index](README.md).
4. Review links and Markdown before publishing. Page filenames determine their wiki URLs; avoid renaming a published page without updating its links.

`Home.md` is the landing page. `_Sidebar.md` and `_Footer.md` are GitHub's special navigation files. Keep publishing instructions and internal audit evidence outside this directory; every ordinary Markdown file copied to the wiki becomes a page.

## Publish a reviewed change

Use an account that can push to the project's wiki. From the application repository root, clone the wiki into a fresh temporary directory and copy only the maintained pages:

```bash
wiki_checkout=$(mktemp -d "${TMPDIR:-/tmp}/submission-platform-wiki.XXXXXX")
git clone https://github.com/NC3-LU/submission-platform.wiki.git "$wiki_checkout"
cp docs/wiki/*.md "$wiki_checkout/"
git -C "$wiki_checkout" diff --check
git -C "$wiki_checkout" diff --stat
git -C "$wiki_checkout" diff
git -C "$wiki_checkout" status --short
```

Review the content of any new files too; unstaged new files are not shown by `git diff`. This copy preserves unrelated wiki pages. If someone edited a maintained page directly on GitHub, reconcile those changes into `docs/wiki/` before continuing.

After reviewing, stage the Markdown pages and check the complete staged change:

```bash
git -C "$wiki_checkout" add -- '*.md'
git -C "$wiki_checkout" diff --cached --check
git -C "$wiki_checkout" diff --cached
```

If there are changes to publish, commit and push the checked-out default branch:

```bash
git -C "$wiki_checkout" commit -m "docs: update project guide"
git -C "$wiki_checkout" push origin HEAD
```

A normal push preserves history. If it is rejected because the wiki changed, fetch and reconcile the new edits before trying again. Do not force-push.

Open the published Home page and follow the sidebar links. Verify the remote commit matches the local commit:

```bash
git -C "$wiki_checkout" rev-parse HEAD
git -C "$wiki_checkout" ls-remote origin HEAD
```

The application repository changes need their normal commit and review as well. A wiki push does not publish the source files to the application's branch, and an application push does not update the wiki automatically.
