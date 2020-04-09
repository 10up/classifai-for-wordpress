import { subscribe, select, dispatch } from '@wordpress/data';

let saveHappened = false;
let showingNotice = false;

subscribe(() => {
	if (saveHappened === false) {
		saveHappened = wp.data.select('core/editor').isSavingPost() === true;
	}

	if (
		saveHappened &&
		wp.data.select('core/editor').isSavingPost() === false &&
		showingNotice === false
	) {
		const { _classifai_error: classifaiError } = select('core/editor').getCurrentPostAttribute(
			'meta',
		);
		if (classifaiError) {
			showingNotice = true;
			const error = JSON.parse(classifaiError);
			dispatch('core/notices').createErrorNotice(
				`Failed to classify content with the IBM Watson NLU API. Error: ${error.code} - ${error.message}`,
			);
			saveHappened = false;
			showingNotice = false;
		}
	}
});
