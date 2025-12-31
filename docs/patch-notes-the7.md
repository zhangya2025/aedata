# The7 demo content compatibility guard

## What changed
- Added `class_exists` checks around `The7_Demo_Content_Meta_Box` usage so Elementor page settings skip the demo content section when the demo-content module is disabled.
- Protected demo-content admin hooks behind the same guard to prevent fatal errors if the module is not loaded.

## Rationale
- MU plugin currently removes the The7 `demo-content` module on frontend/REST to reduce initialization overhead.
- The7's Elementor page settings file `inc/mods/compatibility/elementor/page-settings/the7-demo-content.php` unconditionally instantiates `The7_Demo_Content_Meta_Box`, causing a fatal when the module is absent.
- Guarding these references keeps the module optional while allowing us to continue measuring the performance impact of removing it.

## Verification
- Frontend pages (home, product) render without fatal errors when `demo-content` is removed by the MU plugin.
- Elementor Pro Theme Builder outputs (e.g., header/footer locations) load without fatal errors.
- Collect Nginx `upstream_response_time` samples before/after on the server:
  - `curl https://www.aegismax.com/` then `tail /www/wwwlogs/www.aegismax.com.timing.json`
  - `curl https://www.aegismax.com/index.php/wp-json/` then `tail /www/wwwlogs/www.aegismax.com.timing.json`
  - Record at least 3 samples each; replace the placeholder table below with observed averages.

## Timing samples (fill on server)
| Path | Before avg (s) | After avg (s) | Notes |
| --- | --- | --- | --- |
| / | TODO | TODO | Samples from timing.json |
| /wp-json | TODO | TODO | Samples from timing.json |

## Rollback
- Remove the `class_exists` guards if the demo-content module must remain mandatory; or
- Disable the MU plugin module slimming to re-enable the demo-content module.
