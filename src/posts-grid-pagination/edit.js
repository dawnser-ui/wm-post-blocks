/**
 * Posts Grid Pagination — editor component.
 *
 * Shows a static, disabled preview of the controls. The real, interactive
 * pagination is rendered on the front end by render.php and driven by the
 * shared Interactivity store.
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

import './editor.scss';

export default function Edit() {
	const blockProps = useBlockProps( { className: 'wmpb-pagination' } );

	return (
		<nav { ...blockProps } aria-label={ __( 'Pagination preview', 'wm-posts-blocks' ) }>
			<button type="button" className="wmpb-pagination__btn" disabled>
				{ __( 'Previous', 'wm-posts-blocks' ) }
			</button>
			<span className="wmpb-pagination__status">
				{ __( 'Page 1 of N', 'wm-posts-blocks' ) }
			</span>
			<button type="button" className="wmpb-pagination__btn" disabled>
				{ __( 'Next', 'wm-posts-blocks' ) }
			</button>
		</nav>
	);
}
