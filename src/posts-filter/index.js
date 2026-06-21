/**
 * Posts Filter — editor entry point.
 *
 * Dynamic block: the term checkboxes are produced by render.php so they always
 * reflect the current taxonomy terms. `save` returns null.
 */

import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
