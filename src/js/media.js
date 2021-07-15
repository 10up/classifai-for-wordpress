( function( $ )  {
	const { __ } = wp.i18n;
	const { get } = window.lodash;

	/**
	 *
	 * @param {Element} The button being clicked
	 * @param {string} Which endpoint to query
	 * @param {Function|bool} Optional callback to run after the request completes.
	 *
	 */
	const handleClick = ( { button, endpoint, callback = false } ) => {
		const postID = button.getAttribute( 'data-id' );
		const [ spinner ] = button.parentNode.getElementsByClassName( 'spinner' );
		const [ errorContainer ] = button.parentNode.getElementsByClassName( 'error' );
		const path = `${ endpoint }${ postID }`;

		button.setAttribute( 'disabled', 'disabled' );
		spinner.style.display = 'inline-block';
		spinner.classList.add( 'is-active' );
		errorContainer.style.display = 'none';

		wp.apiRequest( { path } )
			.then(
				( response ) => {
					button.removeAttribute( 'disabled' );
					spinner.style.display = 'none';
					spinner.classList.remove( 'is-active' );
					button.textContent = __( 'Rescan', 'classifai' );
					callback && callback( response );
				},
				( error ) => {
					const errorObj = get( error, 'responseJSON', {
						code: 'unknown_error',
						message: __( 'An unknown error occurred.', 'classifai' ),
					} );
					spinner.style.display = 'none';
					spinner.classList.remove( 'is-active' );
					button.removeAttribute( 'disabled' );
					button.textContent = __( 'Rescan', 'classifai' );
					errorContainer.style.display = 'inline-block';
					errorContainer.textContent = `Error: ${errorObj.message}`;
				}
			);
	};

	$( document ).ready( function() {
		if ( wp.media.frame ) {
			wp.media.frame.on( 'edit:attachment', () => {

				const altTagsButton = document.getElementById( 'classifai-rescan-alt-tags' );
				const imageTagsButton = document.getElementById( 'classifai-rescan-image-tags' );
				const ocrScanButton = document.getElementById( 'classifai-rescan-ocr' );
				const smartCropButton = document.getElementById( 'classifai-rescan-smart-crop' );
				const readButton = document.getElementById( 'classifai-rescan-pdf' );

				if ( altTagsButton ) {
					altTagsButton.addEventListener( 'click', e => handleClick(
						{
							button: e.target,
							endpoint: '/classifai/v1/alt-tags/',
							callback: resp => {
								if ( resp ) {
									const textField = document.getElementById( 'attachment-details-two-column-alt-text' );
									textField.value = resp;
								}
							}
						}
					) );
				}

				if ( imageTagsButton ) {
					imageTagsButton.addEventListener( 'click', e => handleClick(
						{
							button: e.target,
							endpoint: '/classifai/v1/image-tags/'
						}
					) );
				}

				if ( ocrScanButton ) {
					ocrScanButton.addEventListener( 'click', e => handleClick(
						{
							button: e.target,
							endpoint: '/classifai/v1/ocr/',
							callback: resp => {
								if ( resp ) {
									const textField = document.getElementById( 'attachment-details-two-column-description' );
									textField.value = resp;
								}
							}
						}
					) );
				}

				if ( smartCropButton ) {
					smartCropButton.addEventListener( 'click', e => handleClick(
						{
							button: e.target,
							endpoint: '/classifai/v1/smart-crop/'
						}
					) );
				}

				if ( readButton ) {
					readButton.addEventListener( 'click', e => {
						const postID = e.target.getAttribute( 'data-id' );
						wp.apiRequest( { path: `/classifai/v1/read-pdf/${postID}` } );
						e.target.setAttribute( 'disabled', 'disabled' );
						e.target.textContent = __( 'Read API requested!', 'classifai' );
					} );
				}
			} );
		}
	} );
} )( jQuery ) ;
