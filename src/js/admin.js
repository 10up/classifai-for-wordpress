/* global ClassifAI */

// Internal dependencies.
import { handleClick } from './helpers';

( () => {
	const $toggler = document.getElementById( 'classifai-waston-cred-toggle' );
	const $userField = document.getElementById( 'classifai-settings-watson_username' );
	const generateTags = document.getElementById( 'classifai-generate-tags' );

	// Handle tags generation for existing content.
	if ( generateTags ) {
		generateTags.addEventListener( 'click', e => {
			e.preventDefault();
			handleClick(
				{
					button: e.target,
					endpoint: '/classifai/v1/generate-tags/',
					callback: resp => {
						if ( true === resp.success ) {
							const taxonomies = Object.keys( resp );
							taxonomies.forEach( function( e ) {
								if ( 'success' !== e ) {
									const taxonomyWrapper = document.getElementById( e );
									if ( taxonomyWrapper ) {
										// eslint-disable-next-line prefer-destructuring
										const taxonomyInput = taxonomyWrapper.querySelectorAll( 'input' )[0];
										// eslint-disable-next-line prefer-destructuring
										const taxonomyAdd = taxonomyWrapper.querySelectorAll( 'input' )[1];
										taxonomyInput.value = Object.values( resp[e] ).join( ',' );
										taxonomyAdd.click();
									}
								}
							} );
						}
						generateTags.focus();
					}
				}
			);
		} );
	}

	if ( null === $toggler || null === $userField ) return;

	const $userFieldWrapper = $userField.closest( 'tr' );
	const [$passwordFieldTitle] = document.getElementById( 'classifai-settings-watson_password' ).closest( 'tr' ).getElementsByTagName( 'label' );

	if ( null === $toggler ) return;

	$toggler.addEventListener( 'click', e => {
		e.preventDefault();
		$userFieldWrapper.classList.toggle( 'hidden' );

		if ( $userFieldWrapper.classList.contains( 'hidden' ) ) {
			$toggler.innerText = ClassifAI.use_password;
			$passwordFieldTitle.innerText = ClassifAI.api_key;
			$userField.value = 'apikey';
			return;
		}

		$toggler.innerText = ClassifAI.use_key;
		$passwordFieldTitle.innerText = ClassifAI.api_password;
	} );
} )();
