# AEGIS Hero Presets v1

This document describes the new preset workflow for the AEGIS Hero plugin. Hero content now lives in plugin-managed presets, and pages reference presets using the **AEGIS Hero Embed** block.

## Create a Hero preset

1. In the WordPress admin, go to **AEGIS Hero → Heroes**.
2. Click **Add Hero**.
3. Name the preset (this title is what editors see in the embed selector).
4. Configure the single **Aegis Hero** builder block (slides, promo overlay, autoplay, etc.).
5. Publish the preset.

> The preset editor is locked to a single **Aegis Hero** builder block. It cannot contain other blocks.

## Use a preset on a page

1. Open a page in the block editor.
2. Insert **AEGIS Hero Embed**.
3. In the block sidebar, choose a Hero preset from the dropdown.
4. Save or publish the page.

## Important notes

- Pages no longer place the **Aegis Hero** builder block directly. Use **AEGIS Hero Embed** instead.
- The embed block renders the preset on the front end and ensures the hero assets load.
- The existing AEGIS Hero settings (external video allowlist) still apply to presets.
