# Aegis Forms Capabilities

## Capability list

- `aegis_forms_view`: View the submissions list and submission detail pages.
- `aegis_forms_manage_settings`: Manage plugin settings.
- `aegis_forms_edit_submission`: Update submission status or admin notes.
- `aegis_forms_delete_submission`: Move submissions to the trash.
- `aegis_forms_restore_submission`: Restore trashed submissions.
- `aegis_forms_export`: Export submissions as CSV.

## Page and action mapping

| Page/Action | Capability | Notes |
| --- | --- | --- |
| Submissions list (tab=submissions) | `aegis_forms_view` | Also required for menu visibility.
| Submission detail (page=aegis-forms-view) | `aegis_forms_view` | Read-only view when other caps are missing.
| Settings tab (tab=settings) | `aegis_forms_manage_settings` | Controls settings screen access.
| Update submission (`admin_post_aegis_forms_update`) | `aegis_forms_edit_submission` | Requires nonce validation.
| Delete submission (`admin_post_aegis_forms_delete_submission`) | `aegis_forms_delete_submission` | Requires nonce validation.
| Restore submission (`admin_post_aegis_forms_restore_submission`) | `aegis_forms_restore_submission` | Requires nonce validation.
| Export CSV (`admin_post_aegis_forms_export_csv`) | `aegis_forms_export` | Requires nonce validation.
| Save settings (`admin_post_aegis_forms_settings`) | `aegis_forms_manage_settings` | Requires nonce validation.

## Default grants

On activation, the plugin **adds** all Aegis Forms capabilities to the `administrator` role. No other roles are modified by default; additional roles can be granted capabilities via your user management plugin.

## Compatibility fallback

`manage_options` is treated as a compatibility fallback. Users with `manage_options` are treated as if they have all Aegis Forms capabilities so administrators are not locked out. If you choose to remove this fallback in the future, do so deliberately and ensure administrators are assigned the plugin-specific capabilities.
