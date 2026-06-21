/**
 * Posts Grid — front-end view module.
 *
 * The grid has no behaviour of its own: it only renders from the shared store
 * (via the `data-wp-each` template and `data-wp-*` bindings in render.php).
 * Importing the store here registers the `wmpb/posts` namespace on any page
 * where a grid appears, so its directives resolve even if no filter is present.
 */

import '../store';
