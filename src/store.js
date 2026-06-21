/**
 * Shared Interactivity API store for WM Posts Blocks.
 *
 * This is the heart of the inter-block communication. Every interactive element
 * in BOTH blocks declares `data-wp-interactive="wmpb/posts"`, so they all read
 * and write the SAME global store — regardless of where they sit in the DOM.
 * That is exactly why the filter and the grid stay in sync without being nested.
 *
 * Data flow:
 *   filter checkbox  → actions.toggleTerm → updates selectedCategories/Tags
 *                    → actions.refresh    → fetch /wmpb/v1/posts
 *                    → writes state.posts → data-wp-each re-renders the grid
 *   pagination button → actions.prev/nextPage → actions.refresh → same path
 *
 * The store is imported by all three view modules so the logic exists once in
 * source. Calling `store()` with the same namespace simply merges definitions.
 */

import { store, getContext, getConfig } from '@wordpress/interactivity';

const NAMESPACE = 'wmpb/posts';

/**
 * Add or remove an ID from a list (immutably, so the proxy detects the change).
 *
 * @param {number[]} list Current IDs.
 * @param {number}   id   ID to toggle.
 * @return {number[]} New list.
 */
const toggleId = ( list, id ) => {
	const numericId = Number( id );
	return list.includes( numericId )
		? list.filter( ( item ) => item !== numericId )
		: [ ...list, numericId ];
};

const { state, actions } = store( NAMESPACE, {
	state: {
		/** True when there is a previous page to go to. */
		get hasPrev() {
			return state.page > 1;
		},
		/** True when there is a next page to go to. */
		get hasNext() {
			return state.page < state.totalPages;
		},
		/** True when the current result set has at least one post. */
		get hasPosts() {
			return state.posts.length > 0;
		},
		/** True when a finished request returned no posts (for the empty state). */
		get isEmpty() {
			return ! state.loading && state.posts.length === 0;
		},
		/** True when any category or tag filter is active. */
		get hasFilters() {
			return (
				state.selectedCategories.length > 0 ||
				state.selectedTags.length > 0
			);
		},
		/** Human-readable result count, e.g. "12 posts". Labels come from PHP. */
		get resultLabel() {
			const { labels } = getConfig( NAMESPACE );
			const noun = state.total === 1 ? labels.post : labels.posts;
			return `${ labels.showing } ${ state.total } ${ noun }`;
		},
		/**
		 * Whether the checkbox in the current element's context is selected.
		 * `getContext()` resolves to the `{ termId, type }` declared on each
		 * filter option, so one getter serves every checkbox.
		 */
		get isChecked() {
			const { termId, type } = getContext();
			const list =
				type === 'tag' ? state.selectedTags : state.selectedCategories;
			return list.includes( Number( termId ) );
		},
	},

	actions: {
		/**
		 * Toggle a category or tag, reset to page 1, then reload.
		 * The term ID and type come from the checkbox's own context.
		 */
		*toggleTerm() {
			const { termId, type } = getContext();

			if ( type === 'tag' ) {
				state.selectedTags = toggleId( state.selectedTags, termId );
			} else {
				state.selectedCategories = toggleId(
					state.selectedCategories,
					termId
				);
			}

			state.page = 1;
			// NB: `yield` (not `yield*`). The runtime wraps generator actions, so
			// `actions.refresh()` resolves to a Promise — we await it, not iterate it.
			yield actions.refresh();
		},

		/** Clear every active filter and reload from the first page. */
		*clearFilters() {
			state.selectedCategories = [];
			state.selectedTags = [];
			state.page = 1;
			yield actions.refresh();
		},

		/** Go to the previous page. */
		*prevPage() {
			if ( state.page <= 1 ) {
				return;
			}
			state.page -= 1;
			yield actions.refresh();
		},

		/** Go to the next page. */
		*nextPage() {
			if ( state.page >= state.totalPages ) {
				return;
			}
			state.page += 1;
			yield actions.refresh();
		},

		/**
		 * Fetch the current selection from the REST endpoint and update state.
		 *
		 * Implemented as a generator so the Interactivity runtime can suspend on
		 * the awaited fetch and resume with the store scope intact.
		 */
		*refresh() {
			const { restUrl } = getConfig( NAMESPACE );
			state.loading = true;

			try {
				const url = new URL( restUrl );
				url.searchParams.set( 'per_page', state.perPage );
				url.searchParams.set( 'page', state.page );

				if ( state.selectedCategories.length ) {
					url.searchParams.set(
						'categories',
						state.selectedCategories.join( ',' )
					);
				}
				if ( state.selectedTags.length ) {
					url.searchParams.set(
						'tags',
						state.selectedTags.join( ',' )
					);
				}

				const data = yield fetch( url ).then( ( response ) =>
					response.json()
				);

				state.posts = data.posts;
				state.totalPages = data.totalPages;
				state.total = data.total;
				state.page = data.page;
			} catch ( error ) {
				// Surface failures in the console without breaking the page.
				// eslint-disable-next-line no-console
				console.error( 'WM Posts Blocks: failed to load posts.', error );
			} finally {
				state.loading = false;
			}
		},
	},
} );
