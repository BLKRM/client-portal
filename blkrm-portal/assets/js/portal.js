/**
 * WP Agency Hub — Portal JavaScript
 *
 * Handles file uploads, messaging, and approval interactions.
 *
 * @package WPAgencyHub
 */

(function () {
	'use strict';

	/**
	 * REST API helper.
	 */
	function apiRequest(method, endpoint, data, isFormData) {
		var opts = {
			method: method,
			headers: {
				'X-WP-Nonce': wpah_portal.nonce,
			},
			credentials: 'same-origin',
		};

		if (data) {
			if (isFormData) {
				opts.body = data;
			} else {
				opts.headers['Content-Type'] = 'application/json';
				opts.body = JSON.stringify(data);
			}
		}

		return fetch(wpah_portal.rest_url + endpoint, opts).then(function (res) {
			return res.json();
		});
	}

	/**
	 * File upload via drag-and-drop.
	 */
	function initFileUpload() {
		var dropzone = document.getElementById('wpah-dropzone');
		var fileInput = document.getElementById('wpah-file-input');
		var progressWrap = document.getElementById('wpah-upload-progress');
		var progressFill = document.getElementById('wpah-progress-fill');
		var statusText = document.getElementById('wpah-upload-status');

		if (!dropzone || !fileInput) {
			return;
		}

		var projectId = fileInput.getAttribute('data-project-id');

		// Click to browse.
		dropzone.addEventListener('click', function () {
			fileInput.click();
		});

		// Drag events.
		dropzone.addEventListener('dragover', function (e) {
			e.preventDefault();
			dropzone.classList.add('dragging');
		});

		dropzone.addEventListener('dragleave', function () {
			dropzone.classList.remove('dragging');
		});

		dropzone.addEventListener('drop', function (e) {
			e.preventDefault();
			dropzone.classList.remove('dragging');
			if (e.dataTransfer.files.length) {
				uploadFile(e.dataTransfer.files[0]);
			}
		});

		fileInput.addEventListener('change', function () {
			if (fileInput.files.length) {
				uploadFile(fileInput.files[0]);
			}
		});

		function uploadFile(file) {
			if (file.size > wpah_portal.max_file) {
				alert('File exceeds maximum size.');
				return;
			}

			progressWrap.style.display = 'block';
			progressFill.style.width = '0%';
			statusText.textContent = 'Uploading ' + file.name + '...';

			var formData = new FormData();
			formData.append('file', file);

			var xhr = new XMLHttpRequest();
			xhr.open('POST', wpah_portal.rest_url + 'projects/' + projectId + '/files');
			xhr.setRequestHeader('X-WP-Nonce', wpah_portal.nonce);
			xhr.withCredentials = true;

			xhr.upload.addEventListener('progress', function (e) {
				if (e.lengthComputable) {
					var pct = Math.round((e.loaded / e.total) * 100);
					progressFill.style.width = pct + '%';
					statusText.textContent = 'Uploading ' + file.name + '... ' + pct + '%';
				}
			});

			xhr.addEventListener('load', function () {
				var res = JSON.parse(xhr.responseText);
				if (res.success) {
					statusText.textContent = 'Upload complete.';
					progressFill.style.width = '100%';
					setTimeout(function () {
						window.location.reload();
					}, 800);
				} else {
					statusText.textContent = 'Error: ' + (res.message || 'Upload failed.');
					progressFill.style.width = '0%';
				}
			});

			xhr.addEventListener('error', function () {
				statusText.textContent = 'Upload failed. Please try again.';
			});

			xhr.send(formData);
		}
	}

	/**
	 * Message form AJAX submission.
	 */
	function initMessaging() {
		var form = document.getElementById('wpah-message-form');
		var thread = document.getElementById('wpah-message-thread');

		if (!form || !thread) {
			return;
		}

		var projectId = thread.getAttribute('data-project-id');

		form.addEventListener('submit', function (e) {
			e.preventDefault();

			var textarea = form.querySelector('textarea[name="message"]');
			var message = textarea.value.trim();
			var btn = form.querySelector('button[type="submit"]');

			if (!message) {
				return;
			}

			btn.disabled = true;
			btn.textContent = 'Sending...';

			apiRequest('POST', 'projects/' + projectId + '/messages', {
				message: message,
			}).then(function (res) {
				if (res.success) {
					textarea.value = '';
					window.location.reload();
				} else {
					alert(res.message || 'Failed to send message.');
				}
			}).catch(function () {
				alert('Failed to send message.');
			}).finally(function () {
				btn.disabled = false;
				btn.textContent = 'Send Message';
			});
		});
	}

	/**
	 * Approval actions.
	 */
	function initApproval() {
		var actions = document.getElementById('wpah-approval-actions');

		if (!actions) {
			return;
		}

		var approvalId = actions.getAttribute('data-approval-id');
		var noteField = document.getElementById('wpah-approval-note');

		var approveBtn = document.getElementById('wpah-approve-btn');
		var changesBtn = document.getElementById('wpah-request-changes-btn');
		var submitBtn = document.getElementById('wpah-submit-review-btn');

		function transitionApproval(status) {
			var note = noteField ? noteField.value.trim() : '';
			var btns = actions.querySelectorAll('.wpah-btn');

			btns.forEach(function (b) {
				b.disabled = true;
			});

			apiRequest('POST', 'approvals/' + approvalId + '/transition', {
				status: status,
				note: note,
			}).then(function (res) {
				if (res.success) {
					window.location.reload();
				} else {
					alert(res.message || 'Failed to update approval.');
					btns.forEach(function (b) {
						b.disabled = false;
					});
				}
			}).catch(function () {
				alert('Failed to update approval.');
				btns.forEach(function (b) {
					b.disabled = false;
				});
			});
		}

		if (approveBtn) {
			approveBtn.addEventListener('click', function () {
				if (confirm('Approve this deliverable?')) {
					transitionApproval('approved');
				}
			});
		}

		if (changesBtn) {
			changesBtn.addEventListener('click', function () {
				transitionApproval('changes_requested');
			});
		}

		if (submitBtn) {
			submitBtn.addEventListener('click', function () {
				transitionApproval('pending_review');
			});
		}
	}

	// Initialize on DOM ready.
	document.addEventListener('DOMContentLoaded', function () {
		initFileUpload();
		initMessaging();
		initApproval();
	});
})();
