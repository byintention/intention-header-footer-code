# Header Footer Code — agent guide

Grav **2.x** Admin Next HFCM-style snippet manager. Workspace root is this directory only.

## Scope

- Edit **only** files under this plugin tree.
- Do **not** change `user/config/`, `user/pages/`, themes, other plugins, or parent Grav site `.cursor/`.
- Plugin enablement, site config, and snippet data under `user/data/`: **describe steps for the user**; do not apply them here.

See [`.cursor/rules/scope-header-footer-code.mdc`](.cursor/rules/scope-header-footer-code.mdc).

## Skills

Before implementation that touches API routes, Admin2, or `admin-next/`, read [`.cursor/skills/`](.cursor/skills/) and follow the matching skill:

| Task | Skill |
|------|--------|
| REST API only | `grav-api-integration.md` |
| Admin2 / `onApi*` / `admin-next/` / widgets / plugin pages | `grav-api-admin-next-integration.md` |

## What this plugin does

Sitewide HTML / CSS / JS inserts with:

- Admin Next sidebar + list/edit page (CodeMirror)
- Enable/disable per snippet
- Target: frontend, backend (Admin SPA), or both
- Location: header / footer (CSS always head)
- Optional JS `defer` / `async`

Runtime data path (not in this repo): `user/data/header-footer-code/snippets.yaml`.

## Layout

| Path | Role |
|------|------|
| [`header-footer-code.php`](header-footer-code.php) | Events |
| [`classes/SnippetRepository.php`](classes/SnippetRepository.php) | YAML persistence |
| [`classes/SnippetInjector.php`](classes/SnippetInjector.php) | Front-end inject |
| [`classes/Api/HeaderFooterCodeController.php`](classes/Api/HeaderFooterCodeController.php) | CRUD + `/active` |
| [`admin-next/pages/header-footer-code.js`](admin-next/pages/header-footer-code.js) | List/edit UI |
| [`admin-next/widgets/header-footer-code.js`](admin-next/widgets/header-footer-code.js) | Backend autoLoad injector |
| [`assets/admin/page.css`](assets/admin/page.css) | Admin styles |

## Conventions

See [`.cursor/rules/conventions.mdc`](.cursor/rules/conventions.mdc). Class prefix: `hfc-*`. No native `confirm()` in Admin Next — use `__GRAV_DIALOGS`.

## Changelog

After completing a task, prepend two lines to [`.cursor/notes/changelog.md`](.cursor/notes/changelog.md) (date + ≤30-word summary). Do **not** write to the parent Grav site changelog.

## Out of scope here

Site plugin enablement (`user/config/plugins/header-footer-code.yaml`), snippet YAML under `user/data/`, and theme chrome belong in the **Grav site** workspace or Admin UI — not this repo.

## Out of scope (product v1)

Page/route targeting, device targeting, shortcodes, import/export, clone, PHP snippets.
