/**
 * Posts Filter — editor component.
 *
 * Previews the category and tag checkboxes (pulled live from the taxonomies)
 * and exposes toggles to show/hide each filter group via Inspector Controls.
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

import './editor.scss';

export default function Edit( { attributes, setAttributes } ) {
	const { showCategories, showTags } = attributes;
	const blockProps = useBlockProps( { className: 'wmpb-filter' } );

	// Live term lists from the two custom taxonomies.
	const categories = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords( 'taxonomy', 'wmpb_category', {
				per_page: -1,
			} ),
		[]
	);
	const tags = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords( 'taxonomy', 'wmpb_tag', {
				per_page: -1,
			} ),
		[]
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Filter settings', 'wm-posts-blocks' ) }>
					<ToggleControl
						label={ __( 'Show category filter', 'wm-posts-blocks' ) }
						checked={ showCategories }
						onChange={ ( value ) =>
							setAttributes( { showCategories: value } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Show tag filter', 'wm-posts-blocks' ) }
						checked={ showTags }
						onChange={ ( value ) =>
							setAttributes( { showTags: value } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ showCategories && (
					<FilterPreview
						legend={ __( 'Categories', 'wm-posts-blocks' ) }
						terms={ categories }
					/>
				) }
				{ showTags && (
					<FilterPreview
						legend={ __( 'Tags', 'wm-posts-blocks' ) }
						terms={ tags }
					/>
				) }
			</div>
		</>
	);
}

/**
 * Renders a disabled checkbox group for one taxonomy in the editor preview.
 *
 * @param {Object}      props        Props.
 * @param {string}      props.legend Fieldset legend.
 * @param {Array|null}  props.terms  Term records, or null while loading.
 * @return {JSX.Element} Preview markup.
 */
function FilterPreview( { legend, terms } ) {
	return (
		<fieldset className="wmpb-filter__group">
			<legend className="wmpb-filter__legend">{ legend }</legend>
			{ ! terms && <Spinner /> }
			{ terms &&
				terms.map( ( term ) => (
					<label key={ term.id } className="wmpb-filter__option">
						<input type="checkbox" disabled />
						<span>{ term.name }</span>
					</label>
				) ) }
		</fieldset>
	);
}
