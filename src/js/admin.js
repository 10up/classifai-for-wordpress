/* global ClassifAI */
(() => {
	const $toggler = document.getElementById('classifai-waston-cred-toggle');
	const $userField = document.getElementById('classifai-settings-watson_username');

	if ($toggler === null || $userField === null) return;

	const $userFieldWrapper = $userField.closest('tr');
	const [$passwordFieldTitle] = document
		.getElementById('classifai-settings-watson_password')
		.closest('tr')
		.getElementsByTagName('label');

	$toggler.addEventListener('click', (e) => {
		e.preventDefault();
		$userFieldWrapper.classList.toggle('hidden');

		if ($userFieldWrapper.classList.contains('hidden')) {
			$toggler.innerText = ClassifAI.use_password;
			$passwordFieldTitle.innerText = ClassifAI.api_key;
			$userField.value = 'apikey';
			return;
		}

		$toggler.innerText = ClassifAI.use_key;
		$passwordFieldTitle.innerText = ClassifAI.api_password;
	});
})();
