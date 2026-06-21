# WM Posts Blocks

A small WordPress plugin that registers **two Gutenberg blocks** — a dynamic
**Posts Grid** and a companion **Posts Filter** — that stay in sync anywhere on
the page, and seeds a complete demo environment automatically on activation.

Built for the *WordPress Web Development Team Lead* technical assessment.

---

## Table of contents

1. [What it does](#what-it-does)
2. [Quick start](#quick-start)
3. [Architecture overview](#architecture-overview)
4. [The key decision: inter-block communication](#the-key-decision-inter-block-communication)
5. [How the filtering logic works (OR / AND)](#how-the-filtering-logic-works-or--and)
6. [Demo content seeding](#demo-content-seeding)
7. [Project structure](#project-structure)
8. [Trade-offs & known limitations](#trade-offs--known-limitations)
9. [Coding standards](#coding-standards)

---

## What it does

| Requirement | Where it's implemented |
| --- | --- |
| **Posts Grid** — dynamic, not hardcoded | `src/posts-grid/render.php` + `includes/class-posts-query.php` |
| Title, featured image, excerpt | `shape_post()` in `class-posts-query.php` |
| Inspector Controls: columns (2/3/4) & posts per page | `src/posts-grid/edit.js` |
| **Pagination as an inner block** of the grid | `src/posts-grid-pagination/` (restricted via `parent` in `block.json`) |
| **Posts Filter** — category + tag, multi-select | `src/posts-filter/render.php` |
| OR within a filter, AND across filters | `Posts_Query::get_posts()` (`tax_query`) |
| Blocks sync **without nesting** | Shared Interactivity API store, `src/store.js` |
| Seed ≥10 posts, ≥3 categories, multiple tags, featured images, demo page | `includes/class-seeder.php` |
| Unique prefix on slug / post type / taxonomies | `wmpb_` everywhere (`class-content-model.php`) |
| **Admin settings page** (columns, fields shown, brand colour, card style, image ratio, excerpt length) | `includes/class-settings.php` |
| **Generated gradient featured images** (no network on activation) | `Seeder::create_featured_image()` |
| **Card chips + meta** (category chip, date · reading time, tag row) | `Posts_Query::shape_post()` |
| **Result count, Clear-filters, loading bar** | `src/posts-grid/render.php` + `src/store.js` |

---

## Quick start

### Option A — zero-config with `wp-env` (recommended)

Requires [Docker](https://www.docker.com/) and Node.js 18+.

```bash
# 1. Install build tooling
npm install

# 2. Compile the blocks (creates the /build folder)
npm run build

# 3. Boot a throwaway WordPress with this plugin already active
npm run env:start
```

Then open **http://localhost:8888/wp-admin** (user `admin`, password `password`).
On activation the plugin creates everything, so visit the page titled
**“WM Posts Blocks Demo”** on the front end and try the filters + pagination.

To stop / reset:

```bash
npm run env:stop     # stop containers
npm run env:clean    # wipe the environment entirely
```

### Option B — install into an existing WordPress site

```bash
npm install
npm run build        # the /build folder is required at runtime
```

Then copy the whole `wm-posts-blocks` folder into `wp-content/plugins/` (or zip it
and upload via **Plugins → Add New → Upload**) and click **Activate**.

> **Important:** the plugin runs from the compiled `/build` directory, so
> `npm run build` must be run before activating. If you received a `.zip`, the
> build is already included.

### Development

```bash
npm start            # rebuild on save (watch mode)
```

---

## Architecture overview

```
                       ┌─────────────────────────────────────────┐
                       │            wmpb/posts (shared store)      │
   Posts Filter ──────▶│  state.selectedCategories / selectedTags  │◀────── Pagination
   (checkboxes)        │  state.posts / page / totalPages          │        (prev / next)
                       └───────────────────┬───────────────────────┘
                                           │ actions.refresh()
                                           ▼
                          GET /wp-json/wmpb/v1/posts?...   (REST_Controller)
                                           │
                                           ▼
                              Posts_Query::get_posts()      (one WP_Query, OR/AND)
                                           │
                                           ▼
                       state.posts updated ─▶ data-wp-each re-renders the grid
```

**PHP side** (small, single-responsibility classes under `includes/`):

- `Content_Model` — registers the `wmpb_post` CPT and the `wmpb_category` /
  `wmpb_tag` taxonomies.
- `Posts_Query` — the **only** place posts are queried and shaped. Used by both
  the server render and the REST endpoint, so the filtering rules can't drift.
- `REST_Controller` — exposes `GET /wmpb/v1/posts`, delegates to `Posts_Query`.
- `Blocks` — registers the three compiled blocks from `/build`.
- `Seeder` — activation seeding (terms, posts, images, demo page); idempotent.
- `Plugin` — wires every class to its WordPress hooks (the only “glue” file).

**JS side** (`src/`): three blocks, each with an editor component (`edit.js`),
a server render (`render.php`) and a tiny front-end module (`view.js`) that
imports the single shared store in `src/store.js`.

---

## The key decision: inter-block communication

> *“The filter and grid blocks must stay in sync without being nested inside
> each other. Choose the approach you think is most appropriate.”*

I chose the **WordPress Interactivity API** with a **single shared store**
(namespace `wmpb/posts`).

### Why

Every interactive element in **both** blocks declares
`data-wp-interactive="wmpb/posts"`. The Interactivity API resolves directives by
**namespace, not by DOM position**, so all of them read and write the *same*
global store no matter where they sit on the page. That is precisely the problem
the brief describes — independent blocks that must share state — and it is the
exact scenario the Interactivity API was designed for since WordPress 6.5.

Concretely:

- The filter writes `state.selectedCategories` / `state.selectedTags`.
- That triggers `actions.refresh()`, which fetches the matching posts.
- The result is written to `state.posts`, and the grid's `data-wp-each`
  template re-renders automatically.
- The pagination inner block writes `state.page` and calls the same `refresh()`.

There is **no custom event bus, no nesting, and no manual DOM querying** — the
reactive store is the single source of truth.

### Alternatives I considered (and why I didn't pick them)

| Approach | Why not |
| --- | --- |
| **Custom DOM events** (`document.dispatchEvent`) | Works, but it's an ad-hoc pub/sub I'd have to invent, document and guard against typos. The Interactivity API gives the same decoupling with reactive state for free. |
| **`@wordpress/data` (Redux-like) store** | Great in the editor, but it's not the idiomatic tool for *front-end* interactivity and would mean shipping a heavier runtime to render server-side content. |
| **Nesting the grid inside the filter** | Explicitly disallowed by the brief, and it couples two concerns that should be placeable independently. |

### Initial render: server-seeded state, single render path

The grid uses **one** rendering path: a `data-wp-each` template (in
`render.php`) that renders one card per item in `state.posts`. Crucially, the
first page of posts is **seeded into the store server-side** with
`wp_interactivity_state()`, so the data ships inline in the initial HTML and the
grid populates the moment the runtime hydrates — **no extra fetch, no loading
flash** for the first view. The same template then handles every filter and
pagination change.

I deliberately do **not** also print the cards as static `<article>` HTML:
`data-wp-each` *appends* its rendered output rather than reconciling against
existing sibling nodes, so doing both renders every card twice. (I verified this
in a real browser — see "Trade-offs" below.)

**Trade-off:** because the cards are rendered client-side, a JavaScript-disabled
client sees the post data only in the inline JSON, not as visible cards. A fully
no-JS / crawler-friendly version would render server HTML and swap it with the
**Interactivity Router region** pattern (`data-wp-router-region`) that the core
Query Loop block uses for enhanced pagination — a sensible next step, but heavier
than this brief needs.

---

## Admin settings page

**Settings → WM Posts Blocks** is a single control centre (`class-settings.php`,
built on the WordPress Settings API with a sanitiser for every field):

- **Layout** — default columns per row, posts per page.
- **Content** — which fields show (image / excerpt / meta) and excerpt length.
- **Style** — brand accent colour, card style (elevated / bordered / minimal),
  image aspect ratio.

The model is intentionally easy to explain:

- **Per-instance layout** (columns, posts-per-page) lives on the grid block, where
  editors expect it. Each grid can also choose **“Global default”** (stored as
  `0`) to follow the site setting — so a team gets one consistent look while any
  page can still override.
- **Site-wide presentation & content** lives in the settings page. `render.php`
  reads it into CSS custom properties (`--wmpb-accent`, `--wmpb-ratio`) and a card
  style class, and toggles which fields the card template outputs. One change
  re-themes every grid on the site with no code edits.

## How the filtering logic works (OR / AND)

The rule — *OR within a filter type, AND across filter types* — maps cleanly onto
a single `WP_Query` `tax_query` (`includes/class-posts-query.php`):

```php
$tax_query = array( 'relation' => 'AND' );          // AND across types

if ( $categories ) {
    $tax_query[] = array(
        'taxonomy' => 'wmpb_category',
        'terms'    => $categories,
        'operator' => 'IN',                          // OR within categories
    );
}
if ( $tags ) {
    $tax_query[] = array(
        'taxonomy' => 'wmpb_tag',
        'terms'    => $tags,
        'operator' => 'IN',                          // OR within tags
    );
}
```

- `operator => 'IN'` → “match **any** of these terms” = **OR** within a type.
- `relation => 'AND'` → both clauses must pass = **AND** across types.

So selecting *Engineering, Design* + *Tutorial* returns posts that are
(Engineering **OR** Design) **AND** Tutorial. Doing it in the database (rather
than filtering in PHP/JS) keeps pagination counts correct.

---

## Demo content seeding

On activation (`includes/class-seeder.php`), and **only once** (guarded by the
`wmpb_seeded` option so re-activation never duplicates):

- **4 categories** and **6 tags** are created.
- **12 posts** are inserted with deliberately **overlapping** category/tag
  assignments, so filter combinations produce meaningful, non-empty results.
- Each post gets a **diagonal-gradient featured image generated locally with
  PHP's GD extension** (drawn small, upscaled smoothly, with soft overlay
  circles) — no external HTTP request, which would be fragile on locked-down
  hosts. Body length varies per post so the cards show a range of reading times.
- A **demo page** is created with both blocks already placed:

  ```html
  <!-- wp:wmpb/posts-filter /-->
  <!-- wp:wmpb/posts-grid {"columns":3,"perPage":6} -->
      <!-- wp:wmpb/posts-grid-pagination /-->
  <!-- /wp:wmpb/posts-grid -->
  ```

Deleting the plugin from wp-admin runs `uninstall.php`, which removes every
post, attachment, term, the demo page and the options — leaving the site clean.

---

## Project structure

```
wm-posts-blocks/
├── wm-posts-blocks.php          # Plugin header, constants, lifecycle hooks
├── uninstall.php                # Full teardown on delete
├── includes/
│   ├── class-plugin.php         # Hook wiring
│   ├── class-content-model.php  # CPT + taxonomies (wmpb_ prefix)
│   ├── class-settings.php       # Admin settings page (Settings API)
│   ├── class-posts-query.php    # Shared query + OR/AND logic + card shaping
│   ├── class-rest-controller.php# GET /wmpb/v1/posts
│   ├── class-blocks.php         # register_block_type() x3
│   └── class-seeder.php         # Activation seeding
├── src/
│   ├── store.js                 # The shared Interactivity store (the “brain”)
│   ├── posts-grid/              # Block 1 (block.json, edit, render.php, view, scss)
│   ├── posts-grid-pagination/   # Inner block (parent: posts-grid)
│   └── posts-filter/            # Block 2
├── build/                       # Compiled output (generated by npm run build)
├── .wp-env.json                 # One-command local WordPress
└── package.json
```

---

## Trade-offs & known limitations

- **One filter + one grid per page.** The shared store is global to the
  `wmpb/posts` namespace, which is the simplest correct model for the brief.
  Supporting multiple independent grid/filter pairs on a single page would mean
  scoping state by an instance ID — a deliberate next step, not needed here.
- **The store is duplicated into each block's view bundle.** It lives once in
  source (`src/store.js`) but `@wordpress/scripts` inlines it into each view
  module. Re-declaring the same namespace just merges, so this is correct;
  sharing a single runtime module would shave a few KB at the cost of a more
  complex build.
- **Generated placeholder images** are solid-colour JPEGs (intentionally, to
  avoid network calls on activation). Swapping in real imagery is a one-line
  change in `Seeder::create_featured_image()`.
- **No automated tests.** For a plugin this size I prioritised clear structure
  and a strong manual demo; the `Posts_Query` class is deliberately pure and
  isolated so it would be the natural first unit-test target.

---

## Coding standards

- PHP follows the **WordPress Coding Standards** (tabs, Yoda-free readable
  conditionals, full DocBlocks, escaping on all output, sanitising on all
  input). Lint with `composer`/`phpcs` if desired.
- JS/CSS follow **`@wordpress/scripts`** defaults: `npm run lint:js`,
  `npm run lint:css`, `npm run format`.
- Modern PHP namespaces (`WMPB\`) are used instead of long prefixed function
  names — a deliberate choice for a small, class-based plugin.
```
