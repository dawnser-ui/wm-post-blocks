/**
 * Posts Grid — editor component.
 *
 * Gives the editor a realistic, live preview of the grid plus the Inspector
 * Controls required by the brief (columns: 2/3/4, posts per page). The
 * pagination is added as a locked inner block via the template.
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	RangeControl,
	Spinner,
	Placeholder,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

import './editor.scss';

/**
 * The inner-block template: a single, locked pagination block.
 * `templateLock: 'all'` prevents editors from adding/removing/moving it.
 */
const INNER_TEMPLATE = [ [ 'wmpb/posts-grid-pagination' ] ];

export default function Edit( { attributes, setAttributes } ) {
	const { columns, perPage } = attributes;

	// 0 means "inherit the global default"; use sensible fallbacks for the
	// editor preview (the front end resolves the real global setting at render).
	const previewColumns = columns || 3;
	const previewPerPage = perPage || 6;

	// Pull a live sample of demo posts straight from the core data store so the
	// editor preview reflects real, current content (with featured images).
	const posts = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords( 'postType', 'wmpb_post', {
				per_page: previewPerPage,
				_embed: true,
			} ),
		[ previewPerPage ]
	);

	const blockProps = useBlockProps( {
		className: 'wmpb-posts-grid',
	} );

	// The pagination inner block renders below the preview grid.
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'wmpb-posts-grid__pagination' },
		{
			template: INNER_TEMPLATE,
			templateLock: 'all',
			allowedBlocks: [ 'wmpb/posts-grid-pagination' ],
		}
	);

	const gridStyle = { '--wmpb-columns': previewColumns };

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Grid settings', 'wm-posts-blocks' ) }>
					<SelectControl
						label={ __( 'Columns', 'wm-posts-blocks' ) }
						help={ __(
							'Choose "Global default" to follow the plugin settings.',
							'wm-posts-blocks'
						) }
						value={ String( columns ) }
						options={ [
							{
								label: __( 'Global default', 'wm-posts-blocks' ),
								value: '0',
							},
							{ label: '2', value: '2' },
							{ label: '3', value: '3' },
							{ label: '4', value: '4' },
						] }
						onChange={ ( value ) =>
							setAttributes( { columns: Number( value ) } )
						}
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Posts per page', 'wm-posts-blocks' ) }
						help={ __(
							'0 = use the global default.',
							'wm-posts-blocks'
						) }
						value={ perPage }
						onChange={ ( value ) =>
							setAttributes( { perPage: value } )
						}
						min={ 0 }
						max={ 12 }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ ! posts && (
					<Placeholder
						icon="grid-view"
						label={ __( 'Posts Grid', 'wm-posts-blocks' ) }
					>
						<Spinner />
					</Placeholder>
				) }

				{ posts && posts.length === 0 && (
					<Placeholder
						icon="grid-view"
						label={ __( 'Posts Grid', 'wm-posts-blocks' ) }
						instructions={ __(
							'No demo posts found yet. They are created automatically when the plugin is activated.',
							'wm-posts-blocks'
						) }
					/>
				) }

				{ posts && posts.length > 0 && (
					<div
						className="wmpb-posts-grid__items"
						style={ gridStyle }
					>
						{ posts.map( ( post ) => (
							<PreviewCard key={ post.id } post={ post } />
						) ) }
					</div>
				) }

				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}

/**
 * A single card in the editor preview.
 *
 * @param {Object} props      Component props.
 * @param {Object} props.post A post entity record fetched with `_embed`.
 * @return {JSX.Element} Card markup.
 */
function PreviewCard( { post } ) {
	const image =
		post._embedded?.[ 'wp:featuredmedia' ]?.[ 0 ]?.source_url || '';
	const title = post.title?.rendered || '';
	const excerpt = post.excerpt?.rendered || '';

	return (
		<article className="wmpb-card">
			{ image && (
				<img className="wmpb-card__image" src={ image } alt={ title } />
			) }
			<h3
				className="wmpb-card__title"
				// Titles can contain entities like &amp; — render them safely.
				dangerouslySetInnerHTML={ { __html: title } }
			/>
			<div
				className="wmpb-card__excerpt"
				dangerouslySetInnerHTML={ { __html: excerpt } }
			/>
		</article>
	);
}
