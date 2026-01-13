/**
 * Script to inject preset image ID when a preset is applied.
 *
 * This script extends the TEC preset application to include
 * our featured image ID in the ticket form.
 *
 * @package OX_Ticket_Preset_Images
 */

( function( $ ) {
	'use strict';

	var OXApplyPresetImage = {
		/**
		 * Preset images data (preset_id => image_id).
		 */
		presetImages: {},

		/**
		 * Currently pending image ID to apply.
		 */
		pendingImageId: 0,

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
			if ( typeof oxPresetImages === 'undefined' ) {
				return;
			}

			this.presetImages = oxPresetImages.presets || {};
			this.bindEvents();
			this.interceptAjax();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			var self = this;

			// Hook into TEC's preset application buttons - set pending image BEFORE TEC processes.
			$( document ).on( 'click', '.tec-tickets-plus-presets__button-add, .tec-tickets-plus-presets__button-review', function() {
				var presetId = $( '#ticket-preset' ).val();
				if ( presetId ) {
					self.pendingImageId = self.presetImages[ presetId ] || 0;
				}
			} );

			// Clear pending when ticket form is cancelled or reset.
			$( document ).on( 'click', '#tribe_settings_form_cancel', function() {
				self.pendingImageId = 0;
			} );

			// Clear when switching to RSVP or other non-preset flows.
			$( document ).on( 'tribe_ticket_panel_cancel', function() {
				self.pendingImageId = 0;
			} );
		},

		/**
		 * Intercept AJAX requests to inject our image ID into ticket save requests.
		 */
		interceptAjax: function() {
			var self = this;

			// Use jQuery's ajaxPrefilter to intercept all AJAX requests.
			$.ajaxPrefilter( function( options, originalOptions, jqXHR ) {
				// Check if this is a ticket save request.
				if ( ! self.isTicketSaveRequest( options, originalOptions ) ) {
					return;
				}

				// If we have a pending image, add it to the request data.
				if ( self.pendingImageId ) {
					self.injectImageId( options, originalOptions );
				}
			} );
		},

		/**
		 * Check if this AJAX request is a ticket save.
		 *
		 * @param {Object} options         The processed AJAX options.
		 * @param {Object} originalOptions The original AJAX options.
		 * @return {boolean} True if this is a ticket save request.
		 */
		isTicketSaveRequest: function( options, originalOptions ) {
			var data = originalOptions.data || options.data || '';

			// Check for ticket save action in data.
			if ( typeof data === 'string' ) {
				return data.indexOf( 'tribe_ticket_add' ) !== -1 ||
				       data.indexOf( 'action=ticket' ) !== -1;
			}

			if ( typeof data === 'object' ) {
				return data.action === 'tribe_ticket_add' ||
				       ( data.data && data.data.action === 'tribe_ticket_add' );
			}

			return false;
		},

		/**
		 * Inject our image ID into the AJAX request data.
		 *
		 * @param {Object} options         The processed AJAX options.
		 * @param {Object} originalOptions The original AJAX options.
		 */
		injectImageId: function( options, originalOptions ) {
			var imageId = this.pendingImageId;

			if ( typeof options.data === 'string' ) {
				// Data is URL-encoded string.
				options.data += '&ox_preset_image_id=' + encodeURIComponent( imageId );
			} else if ( typeof options.data === 'object' ) {
				// Data is object.
				options.data.ox_preset_image_id = imageId;
			}

			// Clear pending after injection.
			this.pendingImageId = 0;
		}
	};

	OXApplyPresetImage.init();

} )( jQuery );
