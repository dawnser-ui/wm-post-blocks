/**
 * Posts Grid Pagination — editor entry point.
 *
 * A dynamic block (rendered by render.php), restricted via block.json `parent`
 * to only exist inside a Posts Grid. `save` returns null because there is no
 * stored markup and no inner blocks.
 */

import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
