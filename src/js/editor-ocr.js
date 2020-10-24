/* global lodash */
const { select, useSelect, dispatch } = wp.data;
const { createBlock } = wp.blocks;
const { apiFetch } = wp;
const { find, debounce } = lodash;
const { addFilter } = wp.hooks;
const { createHigherOrderComponent } = wp.compose;
const { InspectorControls } = wp.blockEditor; // eslint-disable-line no-unused-vars
const { PanelBody, PanelRow, Button, Modal, Flex, FlexItem } = wp.components; // eslint-disable-line no-unused-vars
const { __ } = wp.i18n;
const { registerPlugin } = wp.plugins;
const { useState, Fragment } = wp.element; // eslint-disable-line no-unused-vars

/**
 * Get image scanned text using media api.
 *
 * @param {int} imageId - Image ID.
*/
const getImageOcrScannedText = async ( imageId ) => {
	const media = await apiFetch( { path: `/wp/v2/media/${imageId}` } );

	if (
		! Object.prototype.hasOwnProperty.call( media, 'meta' )
		|| ! Object.prototype.hasOwnProperty.call( media.meta, 'classifai_computer_vision_ocr' )
		|| ! media.meta.classifai_computer_vision_ocr
	) {
		return false;
	}

	if (
		! Object.prototype.hasOwnProperty.call( media, 'description' )
		|| ! Object.prototype.hasOwnProperty.call( media.description, 'rendered' )
		|| ! media.description.rendered
	) {
		return false;
	}

	return media.description.rendered
		.replace( /(<([^>]+)>)/gi, '' )
		.replace( /(\r\n|\n|\r)/gm,'' )
		.trim();
};

/**
 * Insert scanned text as a paragraph block to the editor.
 *
 * @param {int} clientId - Client ID of image block.
 * @param {int} imageId - Image ID.
 * @param {string} scannedText - Text to insert to editor.
*/
const insertOcrScannedText = async ( clientId, imageId, scannedText = '' ) => {
	const { getBlockIndex } = select( 'core/block-editor' );

	if( ! scannedText ) {
		scannedText = await getImageOcrScannedText( imageId );
	}

	if( ! scannedText ) {
		return;
	}

	const newBlock = createBlock( 'core/paragraph', {
		content: scannedText,
		anchor: `classifai-ocr-${imageId}`,
	} );

	dispatch( 'core/block-editor' ).insertBlock( newBlock, getBlockIndex( clientId ) + 1 );
};

/**
 * An Modal allows user to insert scanned text to block if detected.
 */
const imageOcrModal = () => {
	const [ isOpen, setOpen ] = useState( false );
	const [ imageId, setImageId ] = useState( 0 );
	const [ clientId, setClientId ] = useState( 0 );
	const [ ocrScannedText, setOcrScannedText ] = useState( '' );
	const openModal = () => setOpen( true ); // eslint-disable-line require-jsdoc
	const closeModal = () => setOpen( false ); // eslint-disable-line require-jsdoc
	let currentBlocks;

	useSelect( debounce( async ( select ) => {
		const { getSelectedBlock, getBlocks } = select( 'core/block-editor' );
		const newBlocks = getBlocks();
		const prevBlocks = currentBlocks;
		currentBlocks = newBlocks;

		const currentBlock = getSelectedBlock();

		if ( ! currentBlock || 'core/image' !== currentBlock.name ) {
			return;
		}

		if ( ! currentBlock.attributes.id ) {
			return;
		}

		const prevBlock = find( prevBlocks, block => block.clientId === currentBlock.clientId );

		if ( ! prevBlock || prevBlock.attributes.id === currentBlock.attributes.id ) {
			return;
		}

		setClientId( currentBlock.clientId );
		setImageId( currentBlock.attributes.id );

		const _ocrText = await getImageOcrScannedText( currentBlock.attributes.id );

		if ( ! _ocrText ) {
			return;
		}

		setOcrScannedText( _ocrText );

		openModal();
	}, 10 ) );

	return isOpen && <Modal title={__( 'ClassifAI detected text in your image', 'classifai' )}>
		<p>{__( 'Would you like you insert it as a paragraph under this image block?', 'classifai' )}</p>
		<Flex align='flex-end' justify='flex-end'>
			<FlexItem>
				<Button isPrimary onClick={() => {
					insertOcrScannedText( clientId, imageId, ocrScannedText );
					return closeModal();
				}}>
					{__( 'Insert text', 'classifai' )}
				</Button>
			</FlexItem>
			<FlexItem>
				<Button isSecondary onClick={ closeModal }>
					{__( 'Dismiss', 'classifai' )}
				</Button>
			</FlexItem>
		</Flex>
	</Modal>;
};

registerPlugin( 'tenup-classifai-ocr-modal', {
	render: imageOcrModal,
} );

/**
 * Insert ClassifAI panel to image settings sidebar.
*/
const imageOcrControl = createHigherOrderComponent( ( BlockEdit ) => { // eslint-disable-line no-unused-vars
	return ( props ) => {
		const { attributes, clientId, isSelected, name } = props;

		if ( ! isSelected || 'core/image' != name ) {
			return <BlockEdit {...props} />;
		}

		return (
			<Fragment>
				<BlockEdit {...props} />
				<InspectorControls>
					<PanelBody title={__( 'ClassifAI', 'classifai' )} initialOpen={true}>
						<PanelRow>
							<Button onClick={() => insertOcrScannedText( clientId, attributes.id )} isSecondary>
								{__( 'Insert scanned text into content', 'classifai' )}
							</Button>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	};
}, 'imageOcrControl' );

addFilter(
	'editor.BlockEdit',
	'classifai/image-ocr-control',
	imageOcrControl
);
