# OX Ticket Preset Images

Adds featured image support to Event Tickets Plus presets for WooCommerce tickets.

## Description

This plugin extends Event Tickets Plus to allow you to set a featured image for ticket presets. When a ticket is created from a preset, the featured image is automatically applied to the WooCommerce product.

### Features

- Add featured images to ticket presets using the WordPress Media Library
- Automatically apply the preset's image when creating tickets from presets
- Capture the existing ticket's featured image when saving a ticket as a preset
- Choose between using the ticket's image or selecting a different one
- Works with the Classic Editor ticket interface

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- Event Tickets Plus 6.6.0 or higher
- WooCommerce 7.0 or higher

## Installation

1. Upload the `ox-ticket-preset-images` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure Event Tickets Plus and WooCommerce are both installed and activated

## Usage

### Setting a Featured Image on a Preset

1. Navigate to **Tickets > All Tickets > Presets** tab
2. Create a new preset or edit an existing one
3. Below the Description field, you'll see the **Featured Image** field
4. Click **Select Featured Image** to open the Media Library
5. Choose an image and click **Use this image**
6. Save the preset

### Creating a Ticket from a Preset

When you create a new ticket from a preset that has a featured image:

1. Go to any event and open the ticket editor
2. Select a preset from the dropdown
3. Click **Add Ticket** or **Review & Submit**
4. The ticket (WooCommerce product) will automatically have the preset's featured image

### Saving a Ticket as a Preset

When saving an existing ticket as a preset:

1. Edit a ticket and check **Save as Preset**
2. In the modal, you'll see the ticket's current featured image (if any)
3. You can keep it, select a different image, or remove it
4. Click **Create Preset**

## FAQ

### Where do I set the featured image for a preset?

When creating or editing a ticket preset (Tickets > All Tickets > Presets tab), you'll see a new "Featured Image" field below the Description field. Click "Select Featured Image" to open the Media Library.

### Does this work with the Block Editor?

Currently, this plugin is designed for the Classic Editor ticket interface. Block Editor support may be added in a future version.

### What happens if I don't set an image on a preset?

If no image is set on a preset, tickets created from that preset will simply have no featured image (the current default behaviour).

### Can I change the image after creating a ticket from a preset?

Yes, the preset image is only applied when the ticket is first created. You can edit the WooCommerce product directly to change its featured image afterwards.

## Changelog

### 1.0.0
- Initial release
- Add featured image field to preset form
- Apply preset image when creating tickets from presets
- Capture ticket image when saving as preset
- WordPress Media Library integration

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html
