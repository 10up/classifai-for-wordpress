/* global classifyObj */

// External dependencies.
import { handleClick } from './helpers';

const { __ } = wp.i18n;
const { registerPlugin } = wp.plugins;
const { PluginPostStatusInfo } = wp.editPost; // eslint-disable-line no-unused-vars
const { subscribe, select, dispatch } = wp.data;
const { Button } = wp.components; // eslint-disable-line no-unused-vars
const { Fragment } = wp.element; // eslint-disable-line no-unused-vars

let saveHappened = false;
let showingNotice = false;

/**
 * Display error notice if classify failed.
 */
const showNotice = () => {
	const meta = select( 'core/editor' ).getCurrentPostAttribute( 'meta' );
	if ( meta._classifai_error ) {
		showingNotice = true;
		const error = JSON.parse( meta._classifai_error );
		dispatch( 'core/notices' ).createErrorNotice( `Failed to classify content with the IBM Watson NLU API. Error: ${ error.code } - ${ error.message }` );
		saveHappened = false;
		showingNotice = false;
	}
};

subscribe( () => {
	if ( false === saveHappened ) {
		saveHappened = true === wp.data.select( 'core/editor' ).isSavingPost();
	}

	if ( saveHappened && false === wp.data.select( 'core/editor' ).isSavingPost() && false === showingNotice ) {
		showNotice();
	}
} );

if ( 'true' === classifyObj.show_generate_button ) {
	/**
	 * Add option to generate tags for existing content.
	 *
	 * @returns {JSX.Element}
	 * @constructor
	 */
	const ClassifAIAddGenerateTagsButton = () => (
		<PluginPostStatusInfo>
			<Fragment>
				<Button
					isSecondary={ true }
					data-id= { select( 'core/editor' ).getCurrentPostId() }
					onClick={ ( e ) => {
						handleClick(
							{
								button: e.target,
								endpoint: '/classifai/v1/generate-tags/',
								callback: resp => {
									if ( true === resp.success ) {
										showNotice();
										const isPostSaved = select( 'core/editor' ).didPostSaveRequestSucceed();
										if ( true === isPostSaved ) {
											window.location.reload();
										}
									}
								}
							}
						);
					}}
				>
					{__( 'Generate Tags', 'classifai' )}
				</Button>
				<span className="spinner" style={ { display:'none', float:'none' } }></span>
				<span className="error" style={ { display:'none', color:'#bc0b0b', padding:'5px' } }></span>
			</Fragment>
		</PluginPostStatusInfo>
	);
	registerPlugin( 'classifai-generate-tags', { render: ClassifAIAddGenerateTagsButton } );
}
