import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	// Dynamic block: PHP renders the front end, so nothing is saved to post content.
	save: () => null,
} );
