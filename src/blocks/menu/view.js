import { store } from '@wordpress/interactivity';

// Page-scoped store. The filter block writes `excluded`; menu blocks read it.
store( 'prettylp/menu', {
	state: {
		excluded: [],
	},
} );
