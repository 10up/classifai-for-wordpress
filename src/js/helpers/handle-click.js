/**
 * Click EventListener handler.
 *
 * @param {Element}       buttonThe Button being clicked
 * @param {string}        endpoint  Which endpoint to query
 * @param {Function|bool} callback  Optional callback to run after the request completes.
 *
 */
const handleClick = ( { button, endpoint, callback = false } ) => {
	const postID = button.getAttribute( 'data-id' );
	const [ spinner ] = button.parentNode.getElementsByClassName( 'spinner' );
	const [ errorContainer ] = button.parentNode.getElementsByClassName( 'error' );
	const path = `${ endpoint }${ postID }`;
	const { __ } = wp.i18n;
	const { get } = window.lodash;

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

export default handleClick;
