/* BLKRM Portal admin: logo picker for client organizations. */
(function ($) {
	'use strict';
	$(function () {
		$(document).on('click', '[data-blkrm-pick-logo]', function (e) {
			e.preventDefault();
			var $form = $(this).closest('form');
			var frame = wp.media({ title: 'Client logo', library: { type: 'image' }, multiple: false });
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				var url = (a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url);
				$form.find('[data-blkrm-logo-id]').val(a.id);
				$form.find('[data-blkrm-logo-preview]').empty().append($('<img>', { src: url, alt: '', css: { maxWidth: 120, maxHeight: 64 } }));
			});
			frame.open();
		});
		$(document).on('click', '[data-blkrm-clear-logo]', function (e) {
			e.preventDefault();
			var $form = $(this).closest('form');
			$form.find('[data-blkrm-logo-id]').val('0');
			$form.find('[data-blkrm-logo-preview]').empty();
		});
	});
})(jQuery);
