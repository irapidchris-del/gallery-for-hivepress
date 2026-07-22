/**
 * Additional Gallery for HivePress - front-end scripts.
 *
 * Vanilla JS, no dependencies. Folder drag-sorting is handled by the core
 * HivePress sortable component; this file adds the delete confirmation,
 * copy-link buttons and the per-image description inputs.
 */
(function () {
	'use strict';

	var data = window.hivepressGalleryFrontendData || {};

	/**
	 * Confirms folder deletion.
	 *
	 * HivePress submits forms via a jQuery handler; a capture-phase listener
	 * on the document runs first, so cancelling here stops the submission
	 * before it reaches the AJAX handler.
	 */
	document.addEventListener(
		'submit',
		function (event) {
			var form = event.target;

			if (form.classList && form.classList.contains('hp-form--gallery-folder-delete')) {
				var message = data.deleteConfirm ? data.deleteConfirm : 'Are you sure?';

				if (!window.confirm(message)) {
					event.preventDefault();
					event.stopPropagation();
				}
			}
		},
		true
	);

	/**
	 * Copies a link to the clipboard, briefly marking the button.
	 */
	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-agl-copy]') : null;

		if (!button) {
			return;
		}

		event.preventDefault();

		var url = button.getAttribute('data-agl-copy');

		var markCopied = function () {
			var original = button.innerHTML;

			if (button.querySelector('.hp-icon')) {
				button.innerHTML = '<i class="hp-icon fas fa-check"></i>';
			} else {
				button.textContent = data.copied ? data.copied : 'Copied!';
			}

			window.setTimeout(function () {
				button.innerHTML = original;
			}, 2000);
		};

		var fallbackCopy = function () {
			var input = button.parentNode.querySelector('input');

			if (input) {
				input.focus();
				input.select();

				try {
					document.execCommand('copy');
					markCopied();
				} catch (error) {
					// Selection remains for manual copying.
				}
			}
		};

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(url).then(markCopied, fallbackCopy);
		} else {
			fallbackCopy();
		}
	});

	/**
	 * Per-image description inputs on the folder edit page.
	 *
	 * The core upload field renders one row per attachment with a data-url
	 * pointing at the attachments endpoint; the attachment ID is read from
	 * that URL, and descriptions are saved to the gallery images endpoint
	 * in the same REST namespace.
	 */
	document.addEventListener('DOMContentLoaded', function () {
		var form = document.querySelector('.hp-form--gallery-folder-update');

		if (!form) {
			return;
		}

		// Read the existing descriptions.
		var captions = {};
		var captionsScript = document.querySelector('script[data-agl-captions]');

		if (captionsScript) {
			try {
				captions = JSON.parse(captionsScript.textContent) || {};
			} catch (error) {
				captions = {};
			}
		}

		var getAttachmentId = function (row) {
			var url = row.getAttribute('data-url') || '';
			var match = url.match(/\/attachments\/(\d+)/);

			return match ? match[1] : null;
		};

		var apiBase = null;

		if (window.hivepressCoreData && window.hivepressCoreData.apiURL) {
			apiBase = window.hivepressCoreData.apiURL.replace(/\/$/, '');
		}

		var saveCaption = function (input, id) {
			var row = input.closest('[data-url]');
			var url = apiBase ? apiBase + '/gallery-images/' + id : null;

			if (!url && row) {
				url = row.getAttribute('data-url').replace(/\/attachments\/.*$/, '/gallery-images/' + id);
			}

			if (!url) {
				return;
			}

			var body = new window.FormData();

			body.append('caption', input.value);

			input.classList.add('is-saving');
			input.classList.remove('is-saved', 'is-error');

			window.fetch(url, {
				method: 'POST',
				body: body,
				headers: {
					'X-WP-Nonce': window.hivepressCoreData ? window.hivepressCoreData.apiNonce : ''
				}
			}).then(function (response) {
				input.classList.remove('is-saving');
				input.classList.add(response.ok ? 'is-saved' : 'is-error');

				window.setTimeout(function () {
					input.classList.remove('is-saved');
				}, 2000);
			}).catch(function () {
				input.classList.remove('is-saving');
				input.classList.add('is-error');
			});
		};

		var enhanceRow = function (row) {
			var id = getAttachmentId(row);

			if (!id || row.querySelector('.hp-agl-caption')) {
				return;
			}

			var input = document.createElement('input');

			input.type = 'text';
			input.className = 'hp-agl-caption';
			input.maxLength = 500;
			input.placeholder = data.captionPlaceholder ? data.captionPlaceholder : 'Add a description...';
			input.value = captions[id] ? captions[id] : '';

			var lastSaved = input.value;
			var timeout = null;

			var saveIfChanged = function () {
				if (input.value !== lastSaved) {
					lastSaved = input.value;
					saveCaption(input, id);
				}
			};

			input.addEventListener('input', function () {
				window.clearTimeout(timeout);

				timeout = window.setTimeout(saveIfChanged, 800);
			});

			input.addEventListener('blur', function () {
				window.clearTimeout(timeout);
				saveIfChanged();
			});

			// Uploaded rows are draggable; keep text selection working.
			input.addEventListener('mousedown', function (event) {
				event.stopPropagation();
			});

			row.appendChild(input);
		};

		var container = form.querySelector('.hp-form__field--attachment-upload .hp-row');

		if (!container) {
			return;
		}

		// Enhance the existing rows.
		Array.prototype.forEach.call(container.children, enhanceRow);

		// Enhance rows added by new uploads.
		if (window.MutationObserver) {
			new window.MutationObserver(function (mutations) {
				mutations.forEach(function (mutation) {
					Array.prototype.forEach.call(mutation.addedNodes, function (node) {
						if (node.nodeType === 1 && node.hasAttribute && node.hasAttribute('data-url')) {
							enhanceRow(node);
						}
					});
				});
			}).observe(container, { childList: true });
		}
	});
})();
