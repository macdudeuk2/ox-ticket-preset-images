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
			this.ensureHiddenField();
		},

		/**
		 * Ensure the hidden field exists in the ticket form.
		 */
		ensureHiddenField: function() {
			var $ticketForm = $( '#ticket_form' );
			
			if ( ! $ticketForm.length ) {
				return;
			}

			// Add hidden field if it doesn't exist.
			if ( ! $ticketForm.find( '#ox-preset-image-id' ).length ) {
				$ticketForm.append(
					'<input type="hidden" id="ox-preset-image-id" name="ox_preset_image_id" value="" />'
				);
			}
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			var self = this;

			// Hook into TEC's preset selector change.
			$( document ).on( 'change', '#ticket-preset', function() {
				self.onPresetChange( $( this ).val() );
			} );

			// Hook into TEC's preset application buttons.
			$( document ).on( 'click', '.tec-tickets-plus-presets__button-add, .tec-tickets-plus-presets__button-review', function() {
				var presetId = $( '#ticket-preset' ).val();
				if ( presetId ) {
					self.setImageField( presetId );
				}
			} );

			// Clear the field when ticket form is cancelled or reset.
			$( document ).on( 'click', '#tribe_settings_form_cancel', function() {
				self.clearImageField();
			} );

			// Clear when switching to RSVP or other non-preset flows.
			$( document ).on( 'tribe_ticket_panel_cancel', function() {
				self.clearImageField();
			} );
		},

		/**
		 * Handle preset selector change.
		 *
		 * @param {string} presetId The selected preset ID.
		 */
		onPresetChange: function( presetId ) {
			// We don't set the field on change, only when actually applying.
			// This prevents issues if user selects but doesn't apply.
		},

		/**
		 * Set the hidden image field value.
		 *
		 * @param {string|number} presetId The preset ID.
		 */
		setImageField: function( presetId ) {
			var imageId = this.presetImages[ presetId ] || 0;
			$( '#ox-preset-image-id' ).val( imageId );
		},

		/**
		 * Clear the hidden image field.
		 */
		clearImageField: function() {
			$( '#ox-preset-image-id' ).val( '' );
		}
	};

	OXApplyPresetImage.init();

} )( jQuery );
