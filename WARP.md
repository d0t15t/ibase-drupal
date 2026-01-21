# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project overview

- Drupal 10 project based on `drupal/recommended-project` with the document root in `web/` (see `composer.json` and `extra.drupal-scaffold.locations`).
- Local development is designed to run inside DDEV (`.ddev/config.yaml`), using PHP 8.3, nginx-fpm, and a MariaDB database.
- The site is multi-site: Drupal site directories and DDEV hostnames exist for `ak`, `dyss`, `ibase`, `the`, and `zeigen` (see `web/sites/*` and `.ddev/config.multisite.yaml`).
- Shared settings live in `web/sites/all/settings.all.php`, which derives a `$site_name` from `$site_path` and configures per-site `hash_salt` and private/temp file paths. It also contains development overrides for DDEV (disabling caches, dev DB credentials, etc.).

## Key commands

### DDEV and Composer / Drush

Run backend commands inside the DDEV web container whenever possible:

- Start/stop DDEV: `ddev start`, `ddev stop`.
- Install PHP/Drupal dependencies (from project root): `ddev composer install`.
- Clear caches: `ddev drush cr`.
- Apply database updates: `ddev drush updb -y`.
- Import configuration (if using config sync): `ddev drush cim -y`.

There is a helper command `ddev cmd` (`.ddev/commands/host/cmd`) which executes an arbitrary shell command in the matching directory inside the web container. Example from a theme directory on the host:

- `ddev cmd npm install`
- `ddev cmd npm run build:sass`

### Custom Drush commands

Custom Drush commandfiles live under `web/modules/custom/*/src/Drush/Commands` and are wired via `drush.services.yml`.

- **AK Migrate** (`web/modules/custom/ak_migrate`)
  - Command: `ddev drush ak_migrate-articles_to_exhibitions` (alias `ddev drush akm:a2e`).
  - Purpose: Uses `ImportArticlesToExhibitions` to scan source "article" content and create/update related exhibition nodes, returning a tabular summary of operations.

- **Mixcloud media import** (`web/modules/custom/ibase_mixcloud_media`)
  - Update a Mixcloud channel node and import all episodes as media:
    - `ddev drush update:channel <nid>` (alias `ddev drush update-channel <nid>`), where `<nid>` is the Mixcloud channel node ID.
  - There is also a demo/progress example command:
    - `ddev drush migrate_opm:progress-bar` (alias `ddev drush opm-insert-users`). This shows a console progress bar; it is not part of normal site behavior.

### Front-end build and lint

#### `web/themes/custom/ak_b5subtheme` (Bootstrap 5 subtheme)

This theme is SCSS/JS based and has a full Node toolchain defined in its `package.json`.

From `web/themes/custom/ak_b5subtheme` (preferably via `ddev cmd`):

- Install dependencies: `ddev cmd npm install`.
- Compile SCSS once (per README and `package.json`): `ddev cmd npm run build:sass`.
- Watch SCSS for changes: `ddev cmd npm run watch:sass`.
- Lint styles: `ddev cmd npm run lint:sass`.
- Build ES6 JavaScript bundle: `ddev cmd npm run build:js`.
- Lint JavaScript: `ddev cmd npm run lint:js`.
- Static preview server: `ddev cmd npm run static:start` (serves the theme directory on port 8088 inside the container).
- Live reload of compiled CSS: `ddev cmd npm run livereload`.

#### `web/themes/the` (base theme)

Stylus/PostCSS-based theme generated from Drupal's Starterkit (`the.info.yml`, `README.md`). `package.json` defines several scripts:

From `web/themes/the`:

- Install dependencies: `ddev cmd npm install`.
- One-off Stylus compile via the custom compile script: `ddev cmd npm run compile`.
- File watching & live compilation: `ddev cmd npm run watch`.
- Live reload for CSS output: `ddev cmd npm run reload`.

(Additional `watch_foo` / `compile_foo` scripts exist but are superseded by `watch` / `compile`.)

#### `web/themes/zeigen`

A child theme of `the` (`zeigen.info.yml`). It has a very small Node setup:

From `web/themes/zeigen`:

- Install dependencies: `ddev cmd npm install`.
- Live reload for CSS: `ddev cmd npm run livereload`.

### Testing

- The repository does not currently contain custom PHPUnit test suites or `tests/` directories for modules or themes.
- After running `ddev composer install`, Drupal core and vendor libraries (including PHPUnit) will be available in the DDEV container. If you add custom tests under `web/modules/custom/<module>/tests`, run them using Drupal's standard PHPUnit runner from within the container (for example, `ddev exec ./vendor/bin/phpunit --filter <TestName>` once a suitable `phpunit.xml` configuration is present).
- Front-end validation is performed via the linting commands in `web/themes/custom/ak_b5subtheme` (`lint:sass`, `lint:js`) and any additional stylelint usage you add to `web/themes/the`.

## High-level architecture

### Drupal multi-site layout

- Document root: `web/` (configured in both `composer.json` and `.ddev/config.yaml`).
- Multi-site directories: `web/sites/ak`, `web/sites/dyss`, `web/sites/ibase`, `web/sites/the`, `web/sites/zeigen`. Each folder corresponds to a logical site, with shared behavior wired through `web/sites/all/settings.all.php`.
- DDEV maps additional hostnames `ak`, `dyss`, `the`, and `zeigen` so each site is accessible via its own `*.ddev.site` domain in local development (see `.ddev/config.multisite.yaml`).
- Shared settings (`settings.all.php`) derive a `$site_name` from the active `$site_path` and:
  - Use it as the `hash_salt`.
  - Point private and temp file systems to `../private/$site_name` and `../tmp/$site_name`.
  - When `IS_DDEV_PROJECT=true`, enable development-friendly settings: disable CSS/JS aggregation, disable render/page caches, set verbose error logging, and configure the database connection to the DDEV MariaDB service.

### Custom modules (backend capabilities)

Custom modules are under `web/modules/custom`. Only the most structurally important ones are described here.

- **`ak_migrate`**
  - Provides site-specific migrations for the AK site (see `ak_migrate.info.yml`).
  - Exposes a Drush command (`ak_migrate-articles_to_exhibitions` / `akm:a2e`) that orchestrates migrations via the `ImportArticlesToExhibitions` service, mapping source article content to exhibition nodes.

- **`ak_theme`**
  - A small helper module for the AK site (`ak_theme.info.yml`).
  - Contains a custom block plugin (`ExhibitionYearsBlock`) and Twig templates under `templates/` to render exhibition year listings.

- **`ibase_external_content`**
  - Integrates external JSON:API content into Drupal (see `ibase_external_content.info.yml`).
  - Core logic is in `src/RemoteApiClient.php`:
    - Reads remote endpoint configuration from Drupal settings (e.g. `ibase_external_content_url_json`, `ibase_external_content_api_endpoint`).
    - For each external UUID referenced on a node (field `field_external_artworks`), fetches remote JSON and maps it into local content:
      - Creates `artwork` nodes with local `field_images` media references.
      - Downloads remote images with Guzzle, stores them in `public://styles/<image_style>/...` via Drupal's file entity system, and wires them as `media` entities.
    - Provides a helper `getRemoteArtworkDetails()` to fetch and format a human-readable artwork label (`<title> / <artists>`), used where needed in the site.

- **`ibase_mixcloud_media`**
  - Handles Mixcloud integration for channel/episode content (see `ibase_mixcloud_media.info.yml`).
  - `MixcloudMediaService` encapsulates the core workflow:
    - Given a channel node with a configured API endpoint, recursively pulls paginated channel JSON from Mixcloud, normalizes data and stores it in a temporary store.
    - Derives episode endpoints, fetches each episode, creates taxonomy terms for tags, downloads cover art as files, and creates `media` entities of bundle `mixcloud` with rich metadata fields (`field_duration`, `field_play_count`, etc.).
    - De-duplicates episodes by `field_remote_url` so re-runs update existing items rather than duplicating them.
  - The `MixcloudMediaCommands` Drush commandfile wires this service into CLI commands (`update:channel`), setting up a batch that processes each episode endpoint and finally attaches the resulting media IDs back to the channel node (`field_mixcloud_media`).

- **`ibase_email_js`**
  - Provides JS-enhanced email/contact functionality (see `ibase_email_js.info.yml`, JS under `js/email-js-contact-form.js`) and a block plugin for embedding a custom contact form on the site.

- **`ibase_menu`**
  - Customizes the main navigation for (at least) the `the` site:
    - Provides a custom menu template `menu--main--the.html.twig` and associated JS/CSS (`js/the-main-menu.js`, `css/the-main-menu.css`).
    - Declares libraries in `ibase_menu.libraries.yml` to control how this enhanced menu behavior is attached.

- **`ibase_responsive_lb`, `ibase_block_styles`, `ibase_media_embed`, `ibase_sites`**
  - These modules fine-tune layout and presentation:
    - `ibase_responsive_lb` adds responsive behavior and breakpoints to Layout Builder (`ibase_responsive_lb.breakpoints.yml`, libraries, and JS for responsive layout behavior).
    - `ibase_block_styles` registers additional block style definitions (`*.blockstyle.yml`) consumed by contrib block-style modules.
    - `ibase_media_embed` augments media embedding behavior site-wide.
    - `ibase_sites` provides a small utility service (`IbaseSitesUtility`) and configuration glue for site-specific behavior across the multi-site setup.

- **`ibt_entity_label_override`**
  - Adds UI and configuration for overriding entity labels (see `ibt_entity_label_override.info.yml`, `*.schema.yml`, and form class `EntityLabelOverrideForm`).
  - Exposes an admin form via routing/menu YAML that lets editors configure custom labels per-entity/bundle.

- **`the_content`**
  - Provides additional content-related blocks for the `the` site (see `the_content.info.yml`).
  - Example: `SidebarNodeContentBlock` is a container factory block plugin that pulls the current route's node and renders it with the `sidebar` view mode into a sidebar region.

- **`the_pager`**
  - Adds custom pager functionality for lists (see `the_pager.info.yml`).
  - Exposes block plugins and a utility service `ThePagerUtility` to generate bespoke pager markup/logic beyond Drupal core's default.

- **`ibase_default_content`**
  - Stores default content YAML for nodes under `content/node/*.yml`, intended for seeding base content structures.

- **Update/sandbox modules**
  - `the_update` and `zeigen_update` contain update hooks and CSV data under `updates/10000/` to backfill or migrate field data.
  - `web/modules/sandbox/price` is a sandboxed version of the `price` module, decoupled from Commerce 2.x (per its `README.md`).

## Theming architecture

- **Base theme `the`** (`web/themes/the`)
  - A Starterkit-based Drupal theme using `stable9` as base (`the.info.yml`).
  - Splits assets into libraries (`the.libraries.yml`) for base styles, layout, and component-specific behavior; extends several core and contrib libraries (user UI, dropbutton, dialog, file, progress, etc.).
  - Regions (`header`, `primary_menu`, `content`, sidebars, `footer`, etc.) are defined here and inherited by child themes.

- **Child theme `Zeigen`** (`web/themes/zeigen`)
  - Declares `base theme: the` and adds a `zeigen/global` library for site-specific styling and JS.
  - Uses the same region set as `the`, making it straightforward to share templates and blocks between base and child theme.

- **Bootstrap subtheme `ak_b5subtheme`** (`web/themes/custom/ak_b5subtheme`)
  - A Bootstrap 5-based subtheme used for the AK site, with its own SCSS/JS build pipeline (see its `README.md` and `package.json`).
  - Relies on the global Node/Sass toolchain described above for compiling theme assets.

## Notes for future changes

- When adding new backend functionality, prefer placing it in a dedicated custom module under `web/modules/custom` and wiring services via Drupal's dependency injection container and, where appropriate, Drush commands for batch/CLI workflows.
- For front-end work, be mindful of which site/theme you are targeting (`ak_b5subtheme` vs `the`/`Zeigen`) and run Node tooling through `ddev cmd` from the corresponding theme directory to ensure paths and Node versions match the container environment.
