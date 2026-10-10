import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	return (
		<div { ...useBlockProps() }>
			<p>{ __( 'Menu Filter — customer controls will appear here.', 'restaurant-menu-block' ) }</p>
		</div>
	);
}
