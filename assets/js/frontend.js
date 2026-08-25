/**
 * Additional Gallery for HivePress - front-end scripts.
 *
 * Vanilla JS, no dependencies. Folder drag-sorting is handled by the core
 * HivePress sortable component; this file adds the delete confirmations,
 * copy-link buttons, photo and comment likes, the comment thread, the
 * photo manage form and the paid-access price form.
 */
(function () {
	'use strict';

	var data = window.hivepressGalleryFrontendData || {};

	/**
	 * Builds a REST URL for one of this plugin's endpoints.
	 *
	 * Uses the API root HivePress localises, which already accounts for plain
	 * permalinks (where it is a `?rest_route=` query string rather than a path).
	 */
	var apiUrl = function (path) {
		var base = window.hivepressCoreData && window.hivepressCoreData.apiURL ? window.hivepressCoreData.apiURL.replace(/\/$/, '') : '';

		return base ? base + path : '';
	};

	/**
	 * Sends a request to the HivePress REST API.
	 */
	var apiFetch = function (path, options) {
		var url = apiUrl(path);

		if (!url) {
			return Promise.reject(new Error('no api url'));
		}

		var settings = options || {};

		settings.headers = settings.headers || {};
		settings.headers['X-WP-Nonce'] = window.hivepressCoreData ? window.hivepressCoreData.apiNonce : '';

		return window.fetch(url, settings);
	};

	/**
	 * Shows a message inside a form's messages element, using textContent so
	 * server-provided text can never become markup.
	 */
	var showMessage = function (element, text, isError) {
		if (!element) {
			return;
		}

		element.textContent = text || '';
		element.classList.toggle('hp-form__messages--error', !!isError);
		element.classList.toggle('hp-form__messages--success', !!text && !isError);

		// Core styles .hp-form__messages as display:none and reveals it from
		// its own script (common.js drives it with jQuery show/hide). Setting
		// the text alone therefore left every "Saved" and every error message
		// invisible, with nothing in the console to say so.
		element.style.display = text ? 'block' : 'none';
	};

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

			if (form.classList && form.classList.contains('hp-form--agl-gallery-folder-delete')) {
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

		// Ignore repeat clicks while the confirmation is showing, so the
		// restore never captures the confirmation markup as the original.
		if (button.dataset.aglCopying) {
			return;
		}

		var markCopied = function () {
			var original = button.innerHTML;

			button.dataset.aglCopying = '1';

			// Every copy control confirms the same way, with a tick. The icon-only button in a folder
			// row used to show one while the buttons with a label showed words instead, so the same
			// action looked like two different things depending on where it was pressed.
			button.textContent = '';

			var tick = document.createElement('i');

			tick.className = 'hp-icon fas fa-check';

			button.appendChild(tick);

			// Built as a text node rather than written into innerHTML: this string is translatable,
			// and a translation is not trusted markup.
			if (!button.dataset.aglIconOnly) {
				button.appendChild(document.createTextNode(' ' + (data.copied ? data.copied : 'Copied!')));
			}

			window.setTimeout(function () {
				button.innerHTML = original;
				delete button.dataset.aglCopying;
			}, 2000);
		};

		// Fallback for browsers without the async clipboard API, and for plain
		// HTTP sites where it is unavailable because the page is not a secure
		// context. The temporary input is our own, so this works for buttons
		// that have no text field beside them, mirroring the core `copy`
		// component in common.js.
		var fallbackCopy = function () {
			var input = document.createElement('input');

			input.type = 'text';
			input.value = url;
			input.setAttribute('readonly', 'readonly');
			input.style.position = 'fixed';
			input.style.opacity = '0';
			input.style.pointerEvents = 'none';

			document.body.appendChild(input);
			input.select();
			input.setSelectionRange(0, url.length);

			try {
				if (document.execCommand('copy')) {
					markCopied();
				}
			} catch (error) {
				// Nothing more to try; the link stays visible for manual copying.
			}

			document.body.removeChild(input);
		};

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(url).then(markCopied, fallbackCopy);
		} else {
			fallbackCopy();
		}
	});

	/**
	 * Photo likes (grid tiles and the photo page).
	 */
	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-agl-like]') : null;

		if (!button) {
			return;
		}

		event.preventDefault();

		if (button.disabled) {
			return;
		}

		var id = button.getAttribute('data-agl-like');
		var countEl = button.querySelector('.hp-agl-action__count');

		button.disabled = true;

		apiFetch('/gallery-images/' + id + '/like', { method: 'POST' })
			.then(function (response) {
				if (response.status === 401) {
					if (data.loginUrl) {
						window.location.href = data.loginUrl;
					}

					return null;
				}

				return response.ok ? response.json() : null;
			})
			.then(function (body) {
				if (!body || !body.data) {
					return;
				}

				var liked = !!body.data.liked;

				button.classList.toggle('is-liked', liked);
				button.setAttribute('aria-pressed', liked ? 'true' : 'false');
				button.setAttribute('title', liked ? data.unlike || 'Remove your like' : data.like || 'Like this photo');

				if (countEl) {
					countEl.textContent = body.data.count;
				}
			})
			.catch(function () {
				// Leave the button as it was; the count stays truthful.
			})
			.then(function () {
				button.disabled = false;
			});
	});

	/**
	 * Comment likes.
	 */
	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-agl-clike]') : null;

		if (!button) {
			return;
		}

		event.preventDefault();

		if (button.disabled) {
			return;
		}

		var id = button.getAttribute('data-agl-clike');
		var countEl = button.querySelector('.hp-agl-action__count');

		button.disabled = true;

		apiFetch('/gallery-comments/' + id + '/like', { method: 'POST' })
			.then(function (response) {
				if (response.status === 401 && data.loginUrl) {
					window.location.href = data.loginUrl;

					return null;
				}

				return response.ok ? response.json() : null;
			})
			.then(function (body) {
				if (!body || !body.data) {
					return;
				}

				var liked = !!body.data.liked;

				button.classList.toggle('is-liked', liked);
				button.setAttribute('aria-pressed', liked ? 'true' : 'false');

				if (countEl) {
					countEl.textContent = body.data.count;
				}
			})
			.catch(function () {
				// Leave the state as it was.
			})
			.then(function () {
				button.disabled = false;
			});
	});

	/**
	 * Reply buttons: reveal an inline reply form under the comment.
	 */
	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-agl-reply]') : null;

		if (!button) {
			return;
		}

		event.preventDefault();

		var comment = button.closest('.hp-agl-comment');

		if (!comment) {
			return;
		}

		var existing = comment.querySelector('.hp-agl-comments__reply-form');

		if (existing) {
			existing.remove();

			return;
		}

		var parentId = button.getAttribute('data-agl-reply');
		var section = comment.closest('[data-agl-comments]');
		var photoId = section ? section.getAttribute('data-agl-comments') : null;

		if (!photoId) {
			return;
		}

		var form = document.createElement('form');

		form.className = 'hp-form hp-agl-comments__form hp-agl-comments__reply-form';
		form.setAttribute('data-agl-comment-form', photoId);
		form.setAttribute('data-agl-parent', parentId);

		var field = document.createElement('div');

		field.className = 'hp-form__field hp-form__field--textarea';

		var textarea = document.createElement('textarea');

		textarea.className = 'hp-field hp-field--textarea';
		textarea.name = 'text';
		textarea.rows = 2;
		textarea.maxLength = 1000;
		textarea.required = true;
		textarea.placeholder = data.replyPlaceholder ? data.replyPlaceholder : 'Write a reply...';

		field.appendChild(textarea);

		var footer = document.createElement('div');

		footer.className = 'hp-form__footer';

		var submit = document.createElement('button');

		submit.type = 'submit';
		submit.className = 'hp-form__button button button--primary alt';
		submit.textContent = data.postReply ? data.postReply : 'Post Reply';

		footer.appendChild(submit);

		var messages = document.createElement('div');

		messages.className = 'hp-form__messages';
		messages.setAttribute('data-agl-comment-message', '');

		form.appendChild(field);
		form.appendChild(footer);
		form.appendChild(messages);

		// Into the comment's body, not the comment root: the root is a flex
		// row (avatar beside body), so a form appended there became a third,
		// squeezed column on the far right.
		var body = comment.querySelector('.hp-agl-comment__body') || comment;

		body.appendChild(form);
		textarea.focus();
	});

	/**
	 * Posting a comment or a reply.
	 */
	document.addEventListener('submit', function (event) {
		var form = event.target;

		if (!form.hasAttribute || !form.hasAttribute('data-agl-comment-form')) {
			return;
		}

		event.preventDefault();

		var id = form.getAttribute('data-agl-comment-form');
		var parent = form.getAttribute('data-agl-parent');
		var textarea = form.querySelector('textarea[name="text"]');
		var message = form.querySelector('[data-agl-comment-message]');
		var button = form.querySelector('button[type="submit"]');
		var section = form.closest('[data-agl-comments]') || document.querySelector('[data-agl-comments="' + id + '"]');
		var list = section ? section.querySelector('[data-agl-comment-list]') : null;

		if (!textarea || !textarea.value.trim()) {
			return;
		}

		var body = new window.FormData();

		body.append('text', textarea.value);

		if (parent) {
			body.append('parent', parent);
		}

		if (button) {
			button.disabled = true;
		}

		showMessage(message, '');

		apiFetch('/gallery-images/' + id + '/comments', { method: 'POST', body: body })
			.then(function (response) {
				return response.json().then(function (json) {
					return { ok: response.ok, status: response.status, json: json };
				});
			})
			.then(function (result) {
				if (!result.ok) {
					showMessage(message, result.status === 401 ? data.signInToComment || 'Please sign in to comment.' : data.commentFailed || 'Your comment could not be posted.', true);

					return;
				}

				if (list && result.json && result.json.data && typeof result.json.data.html === 'string') {
					list.innerHTML = result.json.data.html;
				}

				textarea.value = '';

				// A reply form is one-shot; the main composer stays.
				if (form.classList.contains('hp-agl-comments__reply-form')) {
					form.remove();
				}

				updateCommentCount(section, 1);
			})
			.catch(function () {
				showMessage(message, data.commentFailed || 'Your comment could not be posted.', true);
			})
			.then(function () {
				if (button) {
					button.disabled = false;
				}
			});
	});

	/**
	 * Deleting a comment.
	 */
	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-agl-comment-delete]') : null;

		if (!button) {
			return;
		}

		event.preventDefault();

		if (!window.confirm(data.commentDeleteConfirm || 'Delete this comment?')) {
			return;
		}

		var commentId = button.getAttribute('data-agl-comment-delete');
		var section = button.closest('[data-agl-comments]');
		var list = section ? section.querySelector('[data-agl-comment-list]') : null;

		button.disabled = true;

		apiFetch('/gallery-comments/' + commentId, { method: 'DELETE' })
			.then(function (response) {
				return response.ok ? response.json() : null;
			})
			.then(function (body) {
				if (!body || !body.data) {
					button.disabled = false;

					return;
				}

				if (list && typeof body.data.html === 'string') {
					list.innerHTML = body.data.html;
				}

				updateCommentCount(section, -1);
			})
			.catch(function () {
				button.disabled = false;
			});
	});

	/**
	 * Keeps the comments heading count roughly in step. Replies count too,
	 * matching the tile counts, which count every comment row.
	 */
	function updateCommentCount(section, delta) {
		if (!section) {
			return;
		}

		bumpNumber(section.querySelector('.hp-agl-comments__title'), delta);

		// The badge beside the like button is outside the comments section, so it has to be found
		// by photo rather than by looking inside. Without this it sat at zero while the heading
		// right below it already counted the new comment, and only agreed again after a reload.
		var photoId = section.getAttribute('data-agl-comments');

		if (photoId) {
			bumpNumber(document.querySelector('[data-agl-comment-count="' + photoId + '"]'), delta);
		}
	}

	/**
	 * Adds to the first whole number in an element's text, leaving the rest of the wording alone.
	 */
	function bumpNumber(element, delta) {
		if (!element) {
			return;
		}

		var match = element.textContent.match(/\d+/);

		if (!match) {
			return;
		}

		element.textContent = element.textContent.replace(match[0], Math.max(0, parseInt(match[0], 10) + delta));
	}

	/**
	 * Deleting a photo (photo page and photo edit page).
	 *
	 * Uses the core attachments endpoint, which checks ownership and fires the
	 * update events that keep the folder cache and protection in step.
	 */
	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-agl-photo-delete]') : null;

		if (!button) {
			return;
		}

		event.preventDefault();

		if (!window.confirm(data.photoDeleteConfirm || 'Delete this photo? Its likes and comments go with it.')) {
			return;
		}

		var id = button.getAttribute('data-agl-photo-delete');
		var redirect = button.getAttribute('data-agl-redirect');

		button.disabled = true;

		apiFetch('/attachments/' + id, { method: 'DELETE' })
			.then(function (response) {
				if (response.ok || response.status === 204) {
					if (redirect) {
						window.location.href = redirect;
					}
				} else {
					button.disabled = false;
				}
			})
			.catch(function () {
				button.disabled = false;
			});
	});

	/**
	 * The photo manage form in the photo page sidebar: save the details, then
	 * move the photo when a new folder was chosen. A plain save reloads the
	 * page so the new title and description show everywhere; a move goes to
	 * the photo's new address, built from the URL template by swapping the
	 * sentinel for the chosen folder's ID.
	 */
	document.addEventListener('submit', function (event) {
		var form = event.target;

		if (!form.hasAttribute || !form.hasAttribute('data-agl-photo-edit')) {
			return;
		}

		event.preventDefault();

		var id = form.getAttribute('data-agl-photo-edit');
		var currentFolder = form.getAttribute('data-agl-folder');
		var moveTemplate = form.getAttribute('data-agl-move-template') || '';
		var message = form.querySelector('[data-agl-photo-message]');
		var button = form.querySelector('button[type="submit"]');
		var folderSelect = form.querySelector('select[name="folder"]');
		var targetFolder = folderSelect ? folderSelect.value : currentFolder;
		var moving = targetFolder && targetFolder !== currentFolder;

		if (moving && !window.confirm(data.moveConfirm || 'Move this photo to the selected folder?')) {
			return;
		}

		var details = new window.FormData();

		details.append('title', (form.querySelector('input[name="title"]') || {}).value || '');
		details.append('caption', (form.querySelector('textarea[name="caption"]') || {}).value || '');

		if (button) {
			button.disabled = true;
		}

		showMessage(message, '');

		apiFetch('/gallery-images/' + id, { method: 'POST', body: details })
			.then(function (response) {
				if (!response.ok) {
					throw new Error('details');
				}

				if (!moving) {
					return null;
				}

				var move = new window.FormData();

				move.append('folder', targetFolder);

				return apiFetch('/gallery-images/' + id + '/move', { method: 'POST', body: move }).then(function (moveResponse) {
					if (!moveResponse.ok) {
						throw new Error('move');
					}

					return null;
				});
			})
			.then(function () {
				showMessage(message, data.saved || 'Saved.');

				if (moving && moveTemplate) {
					window.location.href = moveTemplate.replace('888888888', targetFolder);
				} else {
					window.location.reload();
				}
			})
			.catch(function (error) {
				showMessage(message, error && error.message === 'move' ? data.moveFailed || 'The photo could not be moved.' : data.saveFailed || 'Your changes could not be saved.', true);

				if (button) {
					button.disabled = false;
				}
			});
	});

	/**
	 * The paid-access price form on the account gallery page.
	 */
	document.addEventListener('submit', function (event) {
		var form = event.target;

		if (!form.hasAttribute || !form.hasAttribute('data-agl-price-form')) {
			return;
		}

		event.preventDefault();

		var message = form.querySelector('[data-agl-price-message]');
		var button = form.querySelector('button[type="submit"]');
		// Every row goes up together, in the order shown. A row with no price is sent as an empty
		// one and the endpoint treats it as unfilled rather than as an error.
		var rows = form.querySelectorAll('[data-agl-tier]');

		var body = new window.FormData();

		for (var i = 0; i < rows.length; i++) {
			var select = rows[i].querySelector('select[name="days[]"]');
			var price = rows[i].querySelector('input[name="price[]"]');

			body.append('days[]', select ? select.value : '0');
			body.append('price[]', price ? price.value : '');
		}

		if (!rows.length) {
			body.append('days[]', '0');
			body.append('price[]', '');
		}

		// Which folder these prices belong to, where the site prices folders separately. Absent on
		// the account page, where the prices are the vendor's whole gallery and the endpoint expects
		// no folder at all.
		var priceFolder = form.getAttribute('data-agl-price-folder');

		if (priceFolder) {
			body.append('folder', priceFolder);
		}

		if (button) {
			button.disabled = true;
		}

		showMessage(message, '');

		apiFetch('/gallery-price', { method: 'POST', body: body })
			.then(function (response) {
				return response.json().then(function (json) {
					return { ok: response.ok, json: json };
				});
			})
			.then(function (result) {
				if (result.ok) {
					showMessage(message, data.priceSaved || 'Price saved.');
				} else {
					showMessage(message, data.priceFailed || 'The price could not be saved.', true);
				}
			})
			.catch(function () {
				showMessage(message, data.priceFailed || 'The price could not be saved.', true);
			})
			.then(function () {
				if (button) {
					button.disabled = false;
				}
			});
	});

	/**
	 * Adding and removing access lengths.
	 *
	 * Rows are cloned from a <template> the server rendered, so the option list and its wording come
	 * from PHP and are translated once, rather than being rebuilt in JavaScript where they would
	 * have to be passed through and escaped again.
	 */
	document.addEventListener('click', function (event) {
		var add = event.target.closest ? event.target.closest('[data-agl-add-tier]') : null;

		if (add) {
			event.preventDefault();

			var wrap = add.closest('.hp-agl-account__paid');
			var list = wrap ? wrap.querySelector('[data-agl-tiers]') : null;
			var template = wrap ? wrap.querySelector('[data-agl-tier-template]') : null;
			var form = wrap ? wrap.querySelector('[data-agl-price-form]') : null;

			if (!list || !template || !form) {
				return;
			}

			var max = parseInt(form.getAttribute('data-agl-max'), 10) || 3;

			if (list.querySelectorAll('[data-agl-tier]').length >= max) {
				return;
			}

			list.appendChild(template.content.cloneNode(true));
			updateTierControls(wrap);

			var added = list.querySelector('[data-agl-tier]:last-child select');

			if (added) {
				added.focus();
			}

			return;
		}

		var remove = event.target.closest ? event.target.closest('[data-agl-remove-tier]') : null;

		if (remove) {
			event.preventDefault();

			var row = remove.closest('[data-agl-tier]');
			var holder = row ? row.parentNode : null;

			if (!row || !holder) {
				return;
			}

			// Never leave the vendor with nothing to type into: the last row is emptied rather than
			// taken away, which is also how they stop selling altogether.
			if (holder.querySelectorAll('[data-agl-tier]').length <= 1) {
				var only = row.querySelector('input[name="price[]"]');

				if (only) {
					only.value = '';
				}
			} else {
				holder.removeChild(row);
			}

			updateTierControls(row.closest ? holder.closest('.hp-agl-account__paid') : null);
		}
	});

	/**
	 * Hides the Add link once every length is in use, and the remove button when there is one row
	 * holding no price, where it would do nothing visible.
	 *
	 * @param {Element} wrap Paid access panel.
	 */
	function updateTierControls(wrap) {
		if (!wrap) {
			return;
		}

		var form = wrap.querySelector('[data-agl-price-form]');
		var list = wrap.querySelector('[data-agl-tiers]');
		var add = wrap.querySelector('[data-agl-add-tier]');

		if (!form || !list) {
			return;
		}

		var max = parseInt(form.getAttribute('data-agl-max'), 10) || 3;
		var count = list.querySelectorAll('[data-agl-tier]').length;

		if (add) {
			add.parentNode.style.display = count >= max ? 'none' : '';
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		var panels = document.querySelectorAll('.hp-agl-account__paid');

		for (var i = 0; i < panels.length; i++) {
			updateTierControls(panels[i]);
		}
	});


	/**
	 * Making a photo its folder's cover.
	 *
	 * The control swaps itself for the "this is the cover" line on success, so the page agrees with
	 * the gallery grid without a reload.
	 */
	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-agl-photo-cover]') : null;

		if (!button) {
			return;
		}

		event.preventDefault();

		var id = button.getAttribute('data-agl-photo-cover');
		var wrap = button.closest('.hp-agl-photo-manage__cover');

		button.disabled = true;

		apiFetch('/gallery-images/' + encodeURIComponent(id) + '/cover', { method: 'POST' })
			.then(function (response) {
				if (!response.ok) {
					throw new Error('failed');
				}

				if (wrap) {
					// Built from nodes rather than innerHTML: the wording is translatable, and a
					// translation is not trusted markup.
					var line = document.createElement('p');
					line.className = 'hp-agl-photo-manage__cover-state';

					var icon = document.createElement('i');
					icon.className = 'hp-icon fas fa-star';
					line.appendChild(icon);

					var label = document.createElement('span');
					label.textContent = data.coverSaved || 'This photo is now the folder cover.';
					line.appendChild(label);

					wrap.replaceChild(line, button);
				}
			})
			.catch(function () {
				button.disabled = false;
				window.alert(data.coverFailed || 'The cover could not be changed.');
			});
	});

})();
