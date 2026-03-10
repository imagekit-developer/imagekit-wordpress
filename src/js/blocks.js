import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import {
	createElement,
	Fragment,
	useEffect,
	useRef,
	useState,
	useCallback,
	createPortal,
} from '@wordpress/element';
import { Button } from '@wordpress/components';

/**
 * Per-block configuration: MIME types, "has media" attribute, and
 * how to map a selected attachment to block attributes.
 */
const BLOCK_CONFIG = {
	'core/image': {
		allowedTypes: [ 'image' ],
		urlAttr: 'url',
		mapAttachment: ( a ) => ( {
			id: a.id,
			url: a.url,
			alt: a.alt,
			caption: a.caption,
		} ),
	},
	'core/video': {
		allowedTypes: [ 'video' ],
		urlAttr: 'src',
		mapAttachment: ( a ) => ( {
			id: a.id,
			src: a.url,
			caption: a.caption,
		} ),
	},
	'core/file': {
		allowedTypes: [],
		urlAttr: 'href',
		mapAttachment: ( a ) => ( {
			id: a.id,
			href: a.url,
			fileName: a.filename || a.title,
		} ),
	},
};

/**
 * Opens the WordPress media modal directly on the ImageKit tab,
 * then inserts the selected media into the given block via setAttributes.
 *
 * @param {Object} props  Block props (needs setAttributes).
 * @param {Object} config Entry from BLOCK_CONFIG.
 */
function openImageKitModal( props, config ) {
	const frameOpts = {
		title: 'ImageKit',
		multiple: false,
	};
	if ( config.allowedTypes.length ) {
		frameOpts.library = { type: config.allowedTypes };
	}

	const frame = wp.media( frameOpts );

	// Switch to the ImageKit content tab as soon as the frame opens.
	frame.on( 'open', function () {
		if ( frame.content && typeof frame.content.mode === 'function' ) {
			frame.content.mode( 'imagekit' );
		}
	} );

	frame.on( 'select', function () {
		const attachment = frame.state().get( 'selection' ).first().toJSON();
		props.setAttributes( config.mapAttachment( attachment ) );
	} );

	frame.open();
}

/**
 * Filter the BlockEdit component for supported media blocks to inject an
 * "Import from ImageKit" button inside the placeholder, alongside the
 * existing Upload / Media Library / Insert from URL buttons.
 *
 * Uses a ref-based approach so the DOM lookup works inside the block
 * editor iframe (WordPress 6.x+).
 */
const withImageKitButton = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { name, attributes, isSelected, clientId } = props;
		const wrapperRef = useRef( null );
		const [ portalContainer, setPortalContainer ] = useState( null );

		const config = BLOCK_CONFIG[ name ];
		const hasMedia = config
			? !! attributes[ config.urlAttr ]
			: false;
		const shouldShow = !! config && ! hasMedia && isSelected;

		useEffect( () => {
			if ( ! shouldShow || ! wrapperRef.current ) {
				setPortalContainer( null );
				return;
			}

			const el = wrapperRef.current;
			const doc = el.ownerDocument;

			// Brief delay so the placeholder DOM is rendered.
			const timer = setTimeout( () => {
				// Use clientId to directly target this specific block
				// inside the iframe document.
				const blockEl = doc.querySelector(
					`[data-block="${ clientId }"]`
				);
				if ( ! blockEl ) {
					return;
				}

				const fieldset = blockEl.querySelector(
					'.components-placeholder__fieldset'
				);
				if ( ! fieldset ) {
					return;
				}

				let container = fieldset.querySelector(
					'.ik-block-editor-button-portal'
				);
				if ( ! container ) {
					container = doc.createElement( 'div' );
					container.className =
						'ik-block-editor-button-portal';
					container.style.display = 'contents';
					fieldset.appendChild( container );
				}
				setPortalContainer( container );
			}, 50 );

			return () => {
				clearTimeout( timer );
				const blockEl = doc.querySelector(
					`[data-block="${ clientId }"]`
				);
				if ( blockEl ) {
					const c = blockEl.querySelector(
						'.ik-block-editor-button-portal'
					);
					if ( c ) {
						c.remove();
					}
				}
				setPortalContainer( null );
			};
		}, [ shouldShow, clientId ] );

		const handleClick = useCallback( () => {
			openImageKitModal( props, config );
		}, [ props, config ] );

		// Only wrap supported blocks so other blocks are unaffected.
		if ( ! config ) {
			return createElement( BlockEdit, props );
		}

		return createElement(
			Fragment,
			null,
			createElement( 'span', {
				ref: wrapperRef,
				'aria-hidden': true,
				style: { display: 'none' },
			} ),
			createElement( BlockEdit, props ),
			portalContainer
				? createPortal(
						createElement(
							Button,
							{
								variant: 'secondary',
								className: 'ik-block-editor-button',
								style: { height: '40px' },
								onClick: handleClick,
							},
							'Import from ImageKit'
						),
						portalContainer
				  )
				: null
		);
	};
}, 'withImageKitButton' );

addFilter(
	'editor.BlockEdit',
	'imagekit/add-imagekit-button',
	withImageKitButton
);