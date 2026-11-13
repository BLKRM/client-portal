/**
 * WP Agency Hub — Admin JavaScript
 *
 * Handles color picker, media upload, and milestone management.
 *
 * @package WPAgencyHub
 */

(function ($) {
	'use strict';

	$(document).ready(function () {
		// Initialize color pickers.
		$('.wpah-color-picker').wpColorPicker();

		// Logo upload via WordPress media library.
		$('#wpah-upload-logo').on('click', function (e) {
			e.preventDefault();

			var frame = wp.media({
				title: 'Choose Logo',
				button: { text: 'Use This Logo' },
				multiple: false,
				library: { type: 'image' },
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				$('#logo_url').val(attachment.url);
			});

			frame.open();
		});

		// Milestone management.
		var milestonesTable = $('#wpah-milestones-table tbody');

		$('#wpah-add-milestone').on('click', function () {
			var row = $('<tr>' +
				'<td>' +
				'<input type="hidden" name="milestone_id[]" value="0">' +
				'<input type="text" name="milestone_name[]" value="" class="regular-text" placeholder="Milestone name">' +
				'</td>' +
				'<td>' +
				'<select name="milestone_status[]">' +
				'<option value="pending">Pending</option>' +
				'<option value="in_progress">In Progress</option>' +
				'<option value="complete">Complete</option>' +
				'</select>' +
				'</td>' +
				'<td><input type="date" name="milestone_due[]" value=""></td>' +
				'<td><button type="button" class="button wpah-remove-milestone">Remove</button></td>' +
				'</tr>');

			milestonesTable.append(row);
		});

		milestonesTable.on('click', '.wpah-remove-milestone', function () {
			$(this).closest('tr').remove();
		});
	});
})(jQuery);
