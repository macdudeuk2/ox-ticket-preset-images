/**
 * Modal script for "Save as Preset" image field.
 *
 * Handles the image field in the Save as Preset modal,
 * including fetching the current ticket's featured image.
 *
 * @package OX_Ticket_Preset_Images
 */

( function( $ ) {
	'use strict';

	var OXPresetImageModal = {
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
		$useTicketBtn: null,

		/**
		 * Initialize.
		 */
		init: function() {
			$( document ).ready( this.onReady.bind( this ) );
		},

		/**
		 * DOM ready handler.
		 */
		onReady: function() {
			// Only run on ticket edit screens.
			if ( typeof oxTicketPresetImages === 'undefined' || oxTicketPresetImages.context !== 'ticket_edit' ) {
				return;
			}

			this.cacheElements();
			this.bindEvents();
		},

		/**
		 * Cache DOM elements.
		 */
		cacheElements: function() {
			this.$container = $( '.ox-preset-modal-image-field' );
			
			if ( ! this.$container.length ) {
				return;
			}

			this.$preview = this.$container.find( '.ox-preset-image-preview' );
			this.$input = this.$container.find( '#ox-modal-preset-image' );
			this.$selectBtn = this.$container.find( '.ox-select-image' );
			this.$removeBtn = this.$container.find( '.ox-remove-image' );
			this.$useTicketBtn = this.$container.find( '.ox-use-ticket-image' );
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			if ( ! this.$container.length ) {
				return;
			}

			var self = this;

			this.$selectBtn.on( 'click', this.openMediaLibrary.bind( this ) );
			this.$removeBtn.on( 'click', this.removeImage.bind( this ) );
			this.$useTicketBtn.on( 'click', this.useTicketImage.bind( this ) );

			// Intercept TEC's save success to save our image.
			this.interceptTecAjax();

			// When modal is shown, fetch ticket's current image.
			var observer = new MutationObserver( function( mutations ) {
				mutations.forEach( function( mutation ) {
					if ( mutation.type === 'attributes' && mutation.attributeName === 'open' ) {
						var dialog = document.getElementById( 'tec-tickets-plus-preset-save-modal' );
						if ( dialog && dialog.hasAttribute( 'open' ) ) {
							self.onModalOpen();
						}
					}
				} );
			} );

			var dialog = document.getElementById( 'tec-tickets-plus-preset-save-modal' );
			if ( dialog ) {
				observer.observe( dialog, { attributes: true } );
			}
		},

		/**
		 * Handler for when the Save as Preset modal opens.
		 */
		onModalOpen: function() {
			var self = this;
			var $ticketIdInput = $( '.tec-tickets-plus-preset-modal__ticket-id' );
			var ticketId = $ticketIdInput.val();

			if ( ! ticketId ) {
				this.$useTicketBtn.hide();
				return;
			}

			// Fetch the ticket's current featured image.
			$.ajax( {
				url: oxTicketPresetImages.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ox_get_ticket_image',
					nonce: oxTicketPresetImages.nonce,
					ticket_id: ticketId
				},
				success: function( response ) {
					if ( response.success && response.data.image_id ) {
						// Store the ticket's image data.
						self.$useTicketBtn.data( 'image-id', response.data.image_id );
						self.$useTicketBtn.data( 'image-url', response.data.image_url );
						self.$useTicketBtn.show();

						// Auto-populate with ticket's image.
						self.setImage( response.data.image_id, response.data.image_url );
					} else {
						self.$useTicketBtn.hide();
					}
				},
				error: function() {
					self.$useTicketBtn.hide();
				}
			} );
		},

		/**
		 * Open the WordPress media library.
		 *
		 * @param {Event} e Click event.
		 */
		openMediaLibrary: function( e ) {
			e.preventDefault();

			var self = this;

			if ( this.mediaFrame ) {
				this.mediaFrame.open();
				return;
			}

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

			this.mediaFrame.on( 'select', function() {
				var attachment = self.mediaFrame.state().get( 'selection' ).first().toJSON();
				var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
				self.setImage( attachment.id, url );
			} );

			this.mediaFrame.open();
		},

		/**
		 * Use the current ticket's featured image.
		 *
		 * @param {Event} e Click event.
		 */
		useTicketImage: function( e ) {
			e.preventDefault();

			var imageId = this.$useTicketBtn.data( 'image-id' );
			var imageUrl = this.$useTicketBtn.data( 'image-url' );

			if ( imageId && imageUrl ) {
				this.setImage( imageId, imageUrl );
			}
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
		},

		/**
		 * Intercept TEC's AJAX calls to save our image after preset creation.
		 */
		interceptTecAjax: function() {
			var self = this;

			// Override jQuery.ajax to intercept the save preset response.
			var originalAjax = $.ajax;
			$.ajax = function( options ) {
				// Check if this is TEC's save preset AJAX call.
				if ( options.data && options.data.action === 'tec_tickets_plus_save_as_preset' ) {
					var originalSuccess = options.success;
					
					options.success = function( response ) {
						// Call the original success handler first.
						if ( typeof originalSuccess === 'function' ) {
							originalSuccess.apply( this, arguments );
						}

						// If successful and we have an image, save it.
						if ( response.success && response.data && response.data.preset_id ) {
							var imageId = self.$input.val();
							if ( imageId ) {
								self.savePresetImage( response.data.preset_id, imageId );
							}
						}
					};
				}

				return originalAjax.apply( this, arguments );
			};
		},

		/**
		 * Save the preset image via AJAX.
		 *
		 * @param {number} presetId The preset ID.
		 * @param {number} imageId  The image attachment ID.
		 */
		savePresetImage: function( presetId, imageId ) {
			$.ajax( {
				url: oxTicketPresetImages.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ox_save_preset_image',
					nonce: oxTicketPresetImages.nonce,
					preset_id: presetId,
					image_id: imageId
				}
			} );
		}
	};

	OXPresetImageModal.init();

} )( jQuery );
