/**
 * Admin script for preset form image field.
 *
 * Handles the image field injection and media library integration
 * on the preset edit/create form.
 *
 * @package OX_Ticket_Preset_Images
 */

( function( $, wp ) {
	'use strict';

	var OXPresetImageAdmin = {
		/**
		 * Media frame instance.
		 */
		mediaFrame: null,

		/**
		 * Cached elements.
		 */
		$container: null,
		$preview: null,
		$input: null,
		$selectBtn: null,
		$removeBtn: null,

		/**
		 * Initialize the admin functionality.
		 */
		init: function() {
			// Wait for DOM ready.
			$( document ).ready( this.onReady.bind( this ) );
		},

		/**
		 * DOM ready handler.
		 */
		onReady: function() {
			// Only run on preset form page.
			if ( typeof oxTicketPresetImages === 'undefined' || oxTicketPresetImages.context !== 'preset_form' ) {
				return;
			}

			this.injectImageField();
			this.bindEvents();
		},

		/**
		 * Inject the image field into the preset form.
		 */
		injectImageField: function() {
			var template = wp.template( 'ox-preset-image-field' );
			
			if ( ! template ) {
				console.error( 'OX Preset Images: Template not found.' );
				return;
			}

			var html = template( {
				imageId: oxTicketPresetImages.imageId || '',
				imageUrl: oxTicketPresetImages.imageUrl || '',
				i18n: oxTicketPresetImages.i18n
			} );

			// Find the ticket details section and insert after the description field.
			var $descriptionField = $( '.tec-tickets-plus-preset__field' ).filter( function() {
				return $( this ).find( '#preset-description' ).length > 0;
			} );

			if ( $descriptionField.length ) {
				$descriptionField.after( html );
			} else {
				// Fallback: insert before the capacity section.
				var $capacitySection = $( '.tec-tickets-plus-preset__capacity' );
				if ( $capacitySection.length ) {
					$capacitySection.before( html );
				} else {
					// Last resort: append to form section.
					$( '.tec-tickets-plus-preset__form-section' ).first().append( html );
				}
			}

			// Cache elements.
			this.$container = $( '.ox-preset-image-field' );
			this.$preview = this.$container.find( '.ox-preset-image-preview' );
			this.$input = this.$container.find( '#ox-preset-featured-image' );
			this.$selectBtn = this.$container.find( '.ox-select-image' );
			this.$removeBtn = this.$container.find( '.ox-remove-image' );
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			if ( ! this.$container || ! this.$container.length ) {
				return;
			}

			this.$selectBtn.on( 'click', this.openMediaLibrary.bind( this ) );
			this.$removeBtn.on( 'click', this.removeImage.bind( this ) );
		},

		/**
		 * Open the WordPress media library.
		 *
		 * @param {Event} e Click event.
		 */
		openMediaLibrary: function( e ) {
			e.preventDefault();

			var self = this;

			// If frame exists, open it.
			if ( this.mediaFrame ) {
				this.mediaFrame.open();
				return;
			}

			// Create the media frame.
			this.mediaFrame = wp.media( {
				title: oxTicketPresetImages.i18n.selectImage,
				button: {
					text: oxTicketPresetImages.i18n.useImage
				},
				multiple: false,
				library: {
					type: 'image'
				}
			} );

			// Handle selection.
			this.mediaFrame.on( 'select', function() {
				var attachment = self.mediaFrame.state().get( 'selection' ).first().toJSON();
				self.setImage( attachment.id, attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url );
			} );

			this.mediaFrame.open();
		},

		/**
		 * Set the selected image.
		 *
		 * @param {number} id  Attachment ID.
		 * @param {string} url Image URL.
		 */
		setImage: function( id, url ) {
			this.$input.val( id );
			this.$preview.html( '<img src="' + url + '" alt="" />' );
			this.$removeBtn.show();
		},

		/**
		 * Remove the selected image.
		 *
		 * @param {Event} e Click event.
		 */
		removeImage: function( e ) {
			e.preventDefault();

			this.$input.val( '' );
			this.$preview.empty();
			this.$removeBtn.hide();
		}
	};

	// Initialize.
	OXPresetImageAdmin.init();

} )( jQuery, wp );
