# Aegis Rewrite Doctor

Aegis Rewrite Doctor provides a read-only diagnostics page and a few opt-in, admin-controlled actions to help restore WooCommerce product permalinks in **index.php** mode. It is designed for emergency troubleshooting and can be disabled at any time.

## Features

- Read-only diagnostics for permalink structure, rewrite rules, WooCommerce permalink settings, and product rewrite config.
- Quick check tool for a single product ID or slug.
- Three admin-controlled actions (all require nonce and manual click):
  1. Flush rewrite rules.
  2. Normalize WooCommerce `product_base` (removes leading/trailing slashes).
  3. Toggle optional fixes:
     - Product request fallback for `index.php/product/{slug}`.
     - Optional normalization of `index.php//product/` in product permalinks only.

## Access & Security

- **Admins only**: Multisite uses `is_super_admin()`; non-multisite uses `manage_options`.
- No frontend output, no logging, and no automatic writes on page load.
- All writes require a manual admin action and nonce verification.

## How to uninstall / disable

- Deactivate the plugin from the Plugins page, or remove the directory:
  `wp-content/plugins/aegis-rewrite-doctor/`

## Risk notes

- The fallback routing is a temporary, opt-in rescue path for index.php-based sites. Disable it once rewrite rules are stable.
- Avoid frequent flushes to prevent performance impact.
