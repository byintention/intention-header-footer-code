# Intention Header Footer Code

HFCM-style manager for sitewide HTML, CSS, and JavaScript snippets on Grav 2 Admin Next. Insert into the public site and/or the admin SPA without editing theme files.

## Requirements

- Grav 2.0+
- [API plugin](https://github.com/getgrav/grav-plugin-api) (Admin Next)
- Admin2 plugin
- PHP 8.3+

## Installation

1. Copy or install the plugin to `user/plugins/intention-header-footer-code`.
2. Enable in Admin2 **Plugins** or set `user/config/plugins/intention-header-footer-code.yaml`:

```yaml
enabled: true
```

3. Clear cache: `bin/grav cache`

## Usage

1. Open Admin Next → **Header Footer Code** in the sidebar.
2. Add a snippet: title, type (HTML / CSS / JS), location (header / footer), target (frontend / backend / both).
3. For JavaScript, optionally enable **Defer** and/or **Async**.
4. Toggle enable/disable from the list without opening the editor.

Snippets are stored in `user/data/intention-header-footer-code/snippets.yaml`.

## Permissions

| Permission | Purpose |
|------------|---------|
| `admin.intention-header-footer-code.read` | List/view snippets |
| `admin.intention-header-footer-code.write` | Create/update/delete |

Super admins (`api.super`) can manage everything.

## Agent / Cursor workspace

This directory is a product-scoped Cursor workspace. See [`AGENTS.md`](AGENTS.md) and [`.cursor/`](.cursor/).
