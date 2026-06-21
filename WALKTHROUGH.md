# WM Posts Blocks — Reverse-Engineering Walkthrough

A study guide that rebuilds the plugin from the *decisions* down to the *code*,
so you can explain any part of it in the interview. Read it top to bottom once,
then use Part 8 (Q&A) and Part 7 (data flow) as your night-before refresher.

---

## Part 0 — The 30-second pitch

> "It's a WordPress plugin with two Gutenberg blocks — a dynamic **Posts Grid**
> and a **Posts Filter** — that stay in sync anywhere on the page through a single
> shared **Interactivity API store**. Filtering and pagination hit a custom REST
> endpoint; the OR-within / AND-across logic is one `WP_Query`. Content lives in a
> prefixed **custom post type** seeded on activation, and a **settings page** lets
> an admin re-theme every grid without touching code."

If you can say that and then defend each clause, you pass.

---

## Part 1 — The one big idea (the mental model)

Everything hangs off a single concept: **the Interactivity API resolves
behaviour by *namespace*, not by DOM position.**

- Every interactive element in *both* blocks carries
  `data-wp-interactive="wmpb/posts"`.
- They therefore all read and write **one shared reactive store** (`src/store.js`).
- So the filter can sit at the top of the page, the grid in the middle, and the
  pagination inside the grid — and they all talk to the same `state` object.

That is the whole answer to the hardest line in the brief: *"the blocks must stay
in sync without being nested."* They share **state**, not a parent.

Reactive store = when you assign `state.posts = [...]`, anything bound to
`state.posts` (the grid template) re-renders automatically. You never touch the
DOM by hand.

---

## Part 2 — Requirements → decisions map

| Brief said | We decided | File |
| --- | --- | --- |
| Two blocks, dynamic posts | Server-rendered (`render.php`) dynamic blocks | `src/posts-grid`, `src/posts-filter` |
| Columns (2/3/4) + per-page controls | Block attributes + Inspector Controls | `posts-grid/edit.js` |
| Pagination **as an inner block** | Separate block, `parent` restricted | `src/posts-grid-pagination` |
| Filter: multi-select category + tag | Pill checkboxes, arrays in state | `posts-filter/render.php` |
| OR within / AND across | One `WP_Query` `tax_query` | `class-posts-query.php` |
| Sync without nesting | Shared Interactivity store | `src/store.js` |
| Unique prefix for post type + taxonomies | Custom `wmpb_post` CPT + `wmpb_*` taxonomies | `class-content-model.php` |
| Seed ≥10 posts, ≥3 cats, tags, images, page | Activation seeder, idempotent | `class-seeder.php` |
| (Added) admin control | Settings API page | `class-settings.php` |

---

## Part 3 — Architecture decisions & why (the part they grill)

For each: **the decision**, **why**, **the alternative you rejected**, and a
**🎤 one-liner** to say out loud.

### 3.1 Custom post type, not regular posts
- **Why:** the brief says *"unique prefix for your plugin slug, post type, and any
  custom taxonomy names."* You can't prefix the built-in `post`/`category`/`post_tag`.
  A CPT also isolates demo data from the real blog and makes uninstall clean.
- **Rejected:** standard posts — would fail the prefix requirement and pollute the blog.
- **🎤** "The prefix requirement only makes sense with a custom post type and custom
  taxonomies, and it keeps demo content isolated."

### 3.2 Interactivity API for inter-block sync
- **Why:** it's WordPress's official front-end interactivity layer (since 6.5),
  purpose-built for exactly this — shared reactive state across blocks, SSR-friendly,
  no framework to ship.
- **Rejected:**
  - *Custom DOM events* — an ad-hoc pub/sub you invent and maintain; no reactive state.
  - *`@wordpress/data` (Redux)* — great in the editor, heavy/wrong tool for the front end.
  - *Nesting the grid in the filter* — explicitly disallowed, and couples two
    independent blocks.
- **🎤** "Same problem the Interactivity API was designed for: independent blocks
  sharing state. Custom events would reinvent a worse version of it."

### 3.3 Custom REST endpoint, not core `/wp/v2`
- **Why:** returns exactly the 4–7 fields a card needs (not the full post object),
  owns the OR/AND logic and the pagination shape, and returns the *same shape* we
  seed into the store so the front end never reshapes data.
- **Rejected:** core REST — heavier payload, less control over the taxonomy logic.
- **🎤** "A focused endpoint that returns render-ready cards and keeps the filter
  logic in one place."

### 3.4 OR/AND done in the database
- **Why:** correctness of pagination counts. If you filtered in PHP/JS after the
  query, `max_num_pages`/`found_posts` would be wrong.
- **How:** `tax_query` with `relation => 'AND'` between clauses, `operator => 'IN'`
  inside each. (Details in Part 6.)
- **🎤** "Filtering in the query keeps the pagination totals honest."

### 3.5 Server-seeded initial state + a single render path
- **Why:** the first page of posts is seeded inline via `wp_interactivity_state()`,
  so the grid paints on hydration with **no extra request and no flash**. One
  template (`data-wp-each`) renders both the first paint and every later update.
- **Rejected:** *also* server-rendering static cards — `data-wp-each` **appends**
  rather than reconciling with existing siblings, so you'd get every card twice
  (we hit this live — see Part 9).
- **Trade-off (say it before they ask):** a no-JS client sees the data only in the
  inline JSON. A fully no-JS/SEO version would use the **Interactivity Router
  region** (`data-wp-router-region`) pattern that core's Query Loop uses.
- **🎤** "I seed the first page into the store server-side, so there's no fetch on
  load and a single declarative render path — no duplicate-rendering trap."

### 3.6 Settings: per-instance layout vs site-wide presentation
- **Why this split:** columns/per-page are an editor's per-block choice → on the
  block. Brand colour, card style, image ratio, which fields show → a team/brand
  decision → one settings page that enforces consistency.
- **The override:** block `columns`/`perPage` default to `0` = "inherit global".
- **🎤** "Layout per instance where editors expect it; presentation centralised so
  the brand stays consistent, with per-block override."

### 3.7 Pagination as an inner block
- **Why:** the brief required it, and because it lives *inside* the grid's
  `data-wp-interactive` scope it automatically shares the same store — it just
  binds buttons to `prevPage`/`nextPage` and reads `state.page`/`state.totalPages`.
- **How restricted:** `"parent": ["wmpb/posts-grid"]` + `"inserter": false` in its
  block.json, and a locked inner-block template in the grid.

---

## Part 4 — Project structure (single responsibility)

```
wm-posts-blocks.php          Plugin header, constants, lifecycle hooks
uninstall.php                Full teardown on delete
includes/
  class-plugin.php           Hook wiring ONLY (the "when")
  class-content-model.php    CPT + 2 taxonomies (the "what data")
  class-settings.php         Admin settings page (Settings API)
  class-posts-query.php      The ONE query + OR/AND + card shaping
  class-rest-controller.php  GET /wmpb/v1/posts → delegates to Posts_Query
  class-blocks.php           register_block_type() x3 from /build
  class-seeder.php           Activation seeding (idempotent)
src/
  store.js                   The shared Interactivity store (the brain)
  posts-grid/                Block 1
  posts-grid-pagination/     Inner block
  posts-filter/              Block 2
build/                       Compiled output (npm run build)
```

The mental rule: **each PHP class does one thing.** `Plugin` decides *when*
things run; it contains no business logic. That's what makes it a "team lead"
codebase — a junior can read it top to bottom.

---

## Part 5 — UX decisions & why

| Choice | Why |
| --- | --- |
| **Seeded state → no loading flash** | Cards appear instantly on load; fetch only happens on interaction. |
| **Pill toggles, not raw checkboxes** | Modern, tappable, on-brand — but a real `<input type=checkbox>` sits underneath (visually hidden) for full keyboard + screen-reader support. |
| **Category chip on the image** | One-glance categorisation without reading. |
| **Meta line (date · reading time)** | Signals freshness + effort; reading time computed from word count. |
| **Tag row** | Shows *why* a post matched a tag filter. |
| **Result count ("Showing 12 posts")** | Immediate feedback that filtering worked. |
| **Clear-filters button (auto-hides)** | Easy escape hatch; only shown when filters are active (`data-wp-bind--hidden="!state.hasFilters"`). |
| **Loading bar + dimmed grid** | Perceived performance during the fetch. |
| **"Inherit global" (0) default** | Editors get site consistency for free, can still override. |
| **Accent colour as a CSS variable** | One setting re-themes chips, links, pills, buttons, the loading bar. |
| **`prefers-reduced-motion`** | Respects accessibility settings — no animations for users who opt out. |
| **Responsive breakpoints** | 4/3 cols → 2 cols (≤781px) → 1 col (≤480px). |

---

## Part 6 — Code walkthrough (why each thing)

### 6.1 `wm-posts-blocks.php` (bootstrap)
1. `defined('ABSPATH') || exit;` — never let the file run if loaded directly (security).
2. Defines `WMPB_VERSION/FILE/DIR/URL` constants — one prefix for everything.
3. `require_once` each class — manual loading (no Composer) keeps a small plugin
   dependency-free and readable.
4. `register_activation_hook(... Seeder::activate)` and the deactivation hook.
5. `add_action('plugins_loaded', Plugin::init)` — boot after all plugins load.

**🎤** "The entry file only wires lifecycle; logic lives in classes."

### 6.2 `class-plugin.php` (the wiring)
- `init()` adds: `Content_Model::register` and `Blocks::register` on `init`;
  `REST_Controller::register_routes` on `rest_api_init`; `Settings::register_hooks()`.
- **Why `init`?** CPT/taxonomies and blocks must exist for editor, REST and front
  end — not just activation.

### 6.3 `class-content-model.php` (the data)
- Constants `POST_TYPE`, `TAX_CATEGORY`, `TAX_TAG` — referenced everywhere so the
  slugs are defined once.
- `register_taxonomy()` first, then `register_post_type()`.
- `wmpb_category` is **hierarchical** (like categories), `wmpb_tag` is **flat**
  (like tags). Both `show_in_rest => true` so Gutenberg + our scripts can read them.
- CPT is `public => true` so cards can link to a real single view;
  `supports => ['title','editor','excerpt','thumbnail']`.

### 6.4 `class-posts-query.php` (the heart)
- `get_posts($args)`:
  1. `sanitize_ids()` — coerce category/tag inputs (array or comma string) into
     positive ints. **Never trust input.**
  2. Clamp `per_page` (1–50) and `page` (≥1).
  3. Build `tax_query`:
     ```php
     $tax_query = ['relation' => 'AND'];          // AND across types
     if ($categories) $tax_query[] = [..., 'operator' => 'IN'];  // OR within
     if ($tags)       $tax_query[] = [..., 'operator' => 'IN'];  // OR within
     ```
     `IN` = match any listed term (OR). `relation AND` = both clauses must pass (AND).
  4. If no filters, pass `tax_query => []` (don't send a relation-only array).
  5. Return `['posts'=>..., 'page'=>..., 'totalPages'=>max_num_pages, 'total'=>found_posts]`.
- `shape_post()` — reduces a `WP_Post` to render-ready fields: `id, title, excerpt
  (trimmed to settings word count), image, link, categoryLabel (primary),
  tagsLabel (joined), metaLabel (date · reading time)`.
- `reading_time()` — `str_word_count / 200`, min 1.

**🎤** "One query class is the single source of truth, used by both the server
render and REST — the filtering rules can't drift."

### 6.5 `class-rest-controller.php` (the endpoint)
- `register_rest_route('wmpb/v1', '/posts', ...)`, method `READABLE` (GET).
- `permission_callback => '__return_true'` — it's public, read-only, published data.
- `args` declare + `sanitize_callback` each param (defence in depth).
- `get_posts()` just maps the request to `Posts_Query::get_posts()` and wraps it in
  `rest_ensure_response()`. The controller is thin on purpose.

### 6.6 `class-blocks.php` (registration)
- Loops `['posts-grid','posts-grid-pagination','posts-filter']`, registers each from
  `build/<name>` **if** `block.json` exists — so a forgotten `npm run build` fails
  loudly instead of silently.

### 6.7 `class-settings.php` (admin)
- `defaults()` — single source of truth for valid keys + values.
- `get($key=null)` — `wp_parse_args(saved, defaults)` so missing keys always resolve.
- `css_ratio()` — maps `'3:2'` → `'3 / 2'` for CSS.
- `register_setting()` with a `sanitize` callback that validates *every* field
  (whitelist for selects, clamp for numbers, `sanitize_hex_color`, booleans from
  checkbox presence).
- `render_page()` — a standard Settings API form (`settings_fields()` + fields),
  grouped Layout / Content / Style.

**🎤** "Settings API gives me nonces, capability checks and the options table for
free; I only add a strict sanitiser."

### 6.8 `class-seeder.php` (activation)
- `activate()`: register the content model (it's not on `init` yet during
  activation), seed **only if** `wmpb_seeded` option is unset (idempotent),
  then `flush_rewrite_rules()` for the CPT permalinks.
- `seed_terms()` — create terms, return name→id map.
- `seed_posts()` — a `$blueprint` of 12 posts with deliberately **overlapping**
  category/tag assignments so filter combinations return meaningful results.
- `create_featured_image()` — draws a diagonal gradient on a 64×64 canvas (cheap),
  upscales smoothly with `imagecopyresampled`, adds two soft translucent circles,
  saves a JPEG, then `wp_insert_attachment` + `wp_generate_attachment_metadata`
  + `set_post_thumbnail`. **No network call** (robust on locked-down hosts).
- `seed_demo_page()` — inserts a page whose content is block markup:
  ```
  <!-- wp:wmpb/posts-filter /-->
  <!-- wp:wmpb/posts-grid --><!-- wp:wmpb/posts-grid-pagination /--><!-- /wp:wmpb/posts-grid -->
  ```
  No grid attributes → it inherits the global settings.
- `uninstall.php` — on delete, removes posts, their attachments, terms, the demo
  page and the options. Leaves the site as it was.

### 6.9 `src/store.js` (the brain) — the most important file
```js
const { state, actions } = store('wmpb/posts', { state: {...}, actions: {...} });
```
- **State getters** (computed, reactive):
  `hasPrev`, `hasNext`, `hasPosts`, `isEmpty`, `hasFilters`, `resultLabel`
  (uses labels from `getConfig`), and `isChecked` (reads the element's
  `getContext()` `{termId, type}` so one getter serves every checkbox).
- **Actions:**
  - `toggleTerm` — reads `{termId, type}` from context, toggles the right array
    (immutably, so the proxy detects the change), resets `page=1`, then
    `yield actions.refresh()`.
  - `clearFilters`, `prevPage`, `nextPage` — same pattern.
  - `refresh` — a **generator**: sets `loading=true`, builds the REST URL from
    `state`, `yield fetch(...)`, writes `state.posts/totalPages/total/page`,
    `loading=false` in `finally`.
- **`yield` not `yield*`:** the runtime wraps generator actions, so
  `actions.refresh()` returns a Promise — you `yield` (await) it, not iterate it.
  (We hit this bug live — Part 9.)
- **Immutable updates:** `state.selectedCategories = [...]` (new array) so the
  reactive proxy notices; mutating in place can be missed.

### 6.10 `src/posts-grid/block.json`
- `apiVersion: 3`, `supports.interactivity: true`, `viewScriptModule:
  "file:./view.js"` (the Interactivity API needs a **script module**, not a
  classic script), `render: "file:./render.php"`.
- `columns`/`perPage` default `0` = inherit global.

### 6.11 `src/posts-grid/render.php`
1. Resolve `columns/perPage` = block attribute **or** global setting (0 = inherit).
2. Read display settings (`show_image/excerpt/meta`, `card_style`, `accent`).
3. `Posts_Query::get_posts()` for the first page.
4. `wp_interactivity_state('wmpb/posts', [... posts, page, totalPages, total,
   selected..., loading ...])` — seed the store.
5. `wp_interactivity_config('wmpb/posts', ['restUrl'=>..., 'labels'=>...])` — static
   read-only config.
6. Wrapper via `get_block_wrapper_attributes()` with `data-wp-interactive`,
   the card-style class, and inline CSS vars (`--wmpb-columns/accent/ratio`).
7. Toolbar (count + clear), progress bar, the `data-wp-each` template (the single
   render path), empty state, then `echo $content` (the pagination inner block).

### 6.12 `src/posts-grid-pagination/render.php`
- Inside the grid scope, so it just uses the shared store: buttons with
  `data-wp-on--click="actions.prevPage/nextPage"`,
  `data-wp-bind--disabled="!state.hasPrev/hasNext"`, and
  `data-wp-text="state.page / state.totalPages"`. Whole nav hidden when
  `!state.hasPosts`.

### 6.13 `src/posts-filter/render.php`
- Queries the terms, seeds default filter state (so it works even on a page with
  no grid), and renders each term as a pill: a label carrying
  `data-wp-context='{"termId":N,"type":"category|tag"}'` (printed safely with
  `wp_interactivity_data_wp_context()`), a visually-hidden checkbox with
  `data-wp-on--change="actions.toggleTerm"` and
  `data-wp-bind--checked="state.isChecked"`, and the styled label text.

### 6.14 `edit.js` files (the editor side)
- Grid: `useBlockProps`, Inspector `SelectControl` (columns incl. "Global default")
  + `RangeControl` (perPage, 0 = global), a live preview via
  `useSelect(getEntityRecords('postType','wmpb_post'))`, and a locked
  `useInnerBlocksProps` template for the pagination.
- Filter: Inspector toggles for show categories/tags + a preview of the pills.
- Pagination: a static, disabled preview (real behaviour is front-end only).
- All `save: () => null` except the grid which returns `<InnerBlocks.Content />`
  (so the inner pagination block is serialised — Part 9 bug).

### 6.15 The build (`package.json`)
- `wp-scripts build --experimental-modules` — the `--experimental-modules` flag is
  required to compile `viewScriptModule` files. Without it the blocks render but
  are dead. (Part 9.)

---

## Part 7 — End-to-end data flow (memorise this trace)

**User clicks the "Design" category pill:**

1. The browser fires `change` on the visually-hidden checkbox.
2. `data-wp-on--change="actions.toggleTerm"` invokes the store action.
3. `getContext()` returns `{ termId: 3, type: "category" }` from that label's
   `data-wp-context`.
4. `toggleTerm` adds `3` to `state.selectedCategories` (new array), sets
   `state.page = 1`, then `yield actions.refresh()`.
5. `refresh` sets `state.loading = true` → the progress bar shows
   (`data-wp-bind--hidden="!state.loading"`) and the grid dims
   (`data-wp-class--is-loading`).
6. It builds `…/wmpb/v1/posts?per_page=6&page=1&categories=3` and `fetch`es it.
7. `REST_Controller::get_posts` → `Posts_Query::get_posts` runs the `WP_Query` with
   the tax_query and returns shaped cards + pagination.
8. Back in JS: `state.posts / totalPages / total / page` are assigned.
9. Reactivity fans out automatically:
   - `data-wp-each` re-renders the cards (keyed by id),
   - `state.resultLabel` updates "Showing N posts",
   - pagination `disabled` states recompute via `hasPrev/hasNext`,
   - the pills stay in sync via `isChecked`,
   - `loading = false` hides the bar.

No page reload. The **filter block and grid block are separate DOM subtrees** —
they only share the `wmpb/posts` namespace. That's the headline.

---

## Part 8 — Likely interview questions & model answers

**Q: How do two un-nested blocks communicate?**
A: A shared Interactivity API store. Directives resolve by namespace, not DOM
position, so both blocks read/write the same `state`.

**Q: Why the Interactivity API over X?**
A: (Part 3.2.) Official, reactive, SSR-friendly, no shipped framework; custom
events would reinvent a worse pub/sub; `@wordpress/data` is the editor's tool.

**Q: Implement the OR/AND filtering.**
A: One `WP_Query` `tax_query`: `relation => 'AND'` between a category clause and a
tag clause, each `operator => 'IN'`. IN = OR within; AND = across. In the DB so
pagination counts stay correct.

**Q: How is pagination "an inner block"?**
A: A separate block with `parent: ['wmpb/posts-grid']`, inserted via a locked
template. It lives in the grid's interactive scope and binds to the shared store.

**Q: Dynamic vs static block?**
A: Dynamic — `render.php` produces markup per request; no `save` output (except the
grid serialises inner blocks). Good for always-current data.

**Q: How does the grid render without a fetch on load?**
A: The first page is seeded into the store with `wp_interactivity_state()`, so
`data-wp-each` renders immediately on hydration.

**Q: Security?**
A: `ABSPATH` guard; sanitise all REST/settings input; escape all output
(`esc_html/esc_url/esc_attr`, `wp_kses_data`); public endpoint only exposes
published data; settings page behind `manage_options` + nonces (Settings API).

**Q: How would you scale to multiple grids on one page?**
A: Today the store is global to one filter/grid pair (documented limitation). I'd
scope state by an instance ID passed through context.

**Q: How would you add no-JS/SEO rendering?**
A: Switch the grid to the Interactivity Router region pattern
(`data-wp-router-region`) that core's Query Loop uses for enhanced pagination.

---

## Part 9 — Bugs we caught by testing in a real browser (great story)

Telling these shows engineering maturity ("I verify, I don't assume"):

1. **`viewScriptModule` didn't compile** — needs `wp-scripts build
   --experimental-modules`. Without it the blocks rendered but were completely
   non-interactive.
2. **`yield* actions.refresh()` threw "not iterable"** — the runtime wraps generator
   actions as Promises, so it must be `yield actions.refresh()`.
3. **Cards rendered twice** — `data-wp-each` *appends* its output instead of
   reconciling with server-rendered siblings, so we dropped the static SSR cards
   and seed `state.posts` instead.
4. **Grid `save` returned `null`** — would have dropped the pagination inner block;
   fixed to `<InnerBlocks.Content />`.

**🎤** "I ran it headless in Chrome against `wp-env` and caught a few issues curl
couldn't — a non-building view module, a generator-delegation bug, and a
double-render from `data-wp-each`. That's why I test behaviour, not just status codes."

---

## Part 10 — "What would you do next?" (shows seniority)

- Scope state by instance ID for multiple grids per page.
- No-JS/SEO via the Interactivity Router region.
- Persist filters in the URL (shareable, back-button friendly) via the History API.
- Free-text search param on the same endpoint.
- PHPUnit tests on `Posts_Query` (it's pure and isolated — the natural first target)
  and Playwright e2e for the filter/pagination flow.
- Cache the REST response per filter combination.

---

### How to study this
1. Read Part 1 + Part 7 until you can say them from memory.
2. Skim Part 3 — know the *alternative* you rejected for each decision.
3. Open each file alongside Part 6 and read the code against the explanation.
4. Rehearse Part 8 out loud.
