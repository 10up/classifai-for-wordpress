// Internal dependencies.
import { handleClick } from './helpers';

( function( $ )  {
	$( document ).ready( function() {
		if ( wp.media.frame ) {
			wp.media.frame.on( 'edit:attachment', () => {

				const altTagsButton = document.getElementById( 'classifai-rescan-alt-tags' );
				const imageTagsButton = document.getElementById( 'classifai-rescan-image-tags' );
				const ocrScanButton = document.getElementById( 'classifai-rescan-ocr' );
				const smartCropButton = document.getElementById( 'classifai-rescan-smart-crop' );

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
			} );
		}
	} );
} )( jQuery ) ;
