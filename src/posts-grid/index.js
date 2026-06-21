/**
 * Posts Grid — editor entry point.
 *
 * Registers the block type with the editor. The heavy lifting lives in edit.js;
 * this file only wires the metadata to the React edit component.
 *
 * The block is *dynamic* — its grid markup is produced by render.php on every
 * request, not stored in post content. But it also has an inner block (the
 * pagination), so `save` must still emit `<InnerBlocks.Content />`; that
 * serialised inner-block markup is what render.php receives as `$content`.
 */

import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
