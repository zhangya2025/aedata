# AEGIS Hero Presets v1

## Create a Hero preset

1. In the WordPress admin, open **AEGIS Hero → Heroes**.
2. Click **Add Hero**.
3. The editor is locked to a single **Aegis Hero** builder block. Configure slides, autoplay, arrows/dots, and promo content as needed.
4. Publish the preset.

## Embed a preset on a page

1. Edit any page or post in the block editor.
2. Insert the **AEGIS Hero Embed** block.
3. Choose a preset from the **Hero Preset** dropdown.
4. Update/publish the page. The preset is rendered on the front-end.

## Notes

- Presets are stored as a custom post type (`aegis_hero`) inside the plugin.
- The embed block only renders published presets on the front-end. Drafts and private presets render only in the editor preview for users with access.
- Front-end assets (`view.js` and `style.css`) are loaded automatically when a preset is embedded.
