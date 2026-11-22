var zoninator = {};

(function($, window, undefined) {

	zoninator.init = function() {
		zoninator.autocompleteCache = {};
		zoninator.autocompleteAjax = {};
		zoninator.$zonePostsList = $('.zone-posts-list');
		zoninator.$zonePostsWrap = $('.zone-posts-wrapper');
		zoninator.$zonePostSearch = $('#zone-post-search');
		zoninator.$zonePostLatest = $('#zone-post-latest');
		zoninator.$zoneAdvancedCat = $('#zone_advanced_filter_taxonomy');
		zoninator.$zoneAdvancedDate = $('#zone_advanced_filter_date');
		zoninator.$zoneSubmit = $('#zone-info input[type="submit"]');
		zoninator.$zonePostsSave = $('#zone-posts-save');
		zoninator.$zonePostsSaveInfo = $('#zone-posts-save-info');

		zoninator.$zoneAdvancedDate.change(function() {
			zoninator.autocompleteCache = {};
			zoninator.updateLatest();
		});

		zoninator.$zoneAdvancedCat.change(function() {
			zoninator.autocompleteCache = {};
			zoninator.updateLatest();
		});

		// Update the currentPostOrder property which keeps track of the list order internally
		zoninator.updatePostOrder();

		// Bind actions to buttons
		zoninator.initZonePost(zoninator.$zonePostsList.children());

		// Initialize sortable - call reorderPosts method when the user drops a post element
		if (!zoninator.$zonePostsWrap.hasClass('readonly')) {
			zoninator.$zonePostsList.sortable({
				stop                  : zoninator.reorderPosts
				, placeholder         : 'ui-state-highlight'
				, forcePlaceholderSize: true
				//, handle: '.zone-post-handle'
				// This helper function addresses #41 (Drag and Drop on Mobile Issue) and corrects the placement of the cloned sortable item on Chrome/Safari iOS
				, helper              : function(event, ui) {
					var clone = $(ui).clone();
					clone.css('position','absolute');
					return clone.get(0);
				}
			});
		}

		// Bind loading events
		zoninator.$zonePostsWrap
			.bind('loading.start', function() {
				zoninator.$zoneSubmit.attr("disabled", true);
				$(this).addClass('loading');
			})
			.bind('loading.end', function() {
				$(this).removeClass('loading');
				zoninator.$zoneSubmit.attr("disabled", false);
			});

		// The name field is required; a name of only spaces is empty too, so let the browser report it.
		$('#zone-info').submit(function(e) {
			var $name = $(this).find('input[name="name"]');
			if (!$name.val().trim()) {
				$name.val('');
				this.reportValidity();
				return false;
			}
		});

		zoninator.$zonePostsSave.on('click', zoninator.forceSavePosts);

		// Add the post chosen from the latest posts list. This waits for the button, rather than adding on change,
		// because moving through a closed list with the arrow keys changes its value at every step in some browsers.
		$('#zone-post-latest-add').on('click', function() {
			var post_id = zoninator.$zonePostLatest.val();
			if (post_id) {
				zoninator.addPost(post_id);
				zoninator.$zonePostLatest.find('[value="' + post_id + '"]').remove();
				zoninator.$zonePostLatest.val('');
			}
		});


		// Initialize autocomplete
		if (zoninator.$zonePostSearch.length) {
			zoninator.$zonePostSearch
				.bind('loading.start', function(e) {
					$(this).addClass('loading');
				})
				.bind('loading.end', function(e) {
					$(this).removeClass('loading');
				})
				.autocomplete({
					minLength: 3
					// the default timeout (300ms) doesn't allow much time between keystrokes, increasing it to avoid request flooding
					, delay: 600
					// Remote source with caching
					, source : function(request, response) {
						var term = request.term;

						request.cat = zoninator.getAdvancedCat();
						request.date = zoninator.getAdvancedDate();
						request.zone_id = zoninator.getZoneId();

						if (term in zoninator.autocompleteCache) { //&& request.cat && request.date ) {
							response(zoninator.autocompleteCache[term]);
							zoninator.$zonePostSearch.trigger('loading.end');
							return;
						}

						// Append more request vars
						request.action = zoninator.getAjaxAction('search_posts');
						request._wpnonce = zoninator.getAjaxNonce();
						request.exclude = zoninator.getZonePostIds();

						// Allow developers to hook onto the request
						zoninator.$zonePostSearch.trigger('search.request', request);

						zoninator.autocompleteAjax = $.getJSON(ajaxurl, request, function(data, status, xhr) {
							// Give each result a value, so moving through them with the arrow keys shows and announces its title.
							data = $.map(data, function(item) {
								return $.extend({ value: item.title }, item);
							});
							zoninator.autocompleteCache[term] = data;
							if (xhr === zoninator.autocompleteAjax) {
								response(data);
							}
							zoninator.$zonePostSearch.trigger('loading.end');
						});
					}
					, select : function(e, ui) {
						zoninator.addPost(ui.item.post_id);
						$(this).val('');
						return false;
					}
					, search : function(e, ui) {
						zoninator.$zonePostSearch.trigger('loading.start');
					}
				});

			// Compat with jQuery 1.8 and 1.9; the latter uses ui- prefix for data attribute
			var autocomplete = zoninator.$zonePostSearch.data('autocomplete') || zoninator.$zonePostSearch.data('ui-autocomplete');

			autocomplete._renderItem = function(ul, item) {
				var $anchor = $('<a></a>')
					.append($('<span class="title"></span>').text(item.title))
					.append($('<span class="type"></span>').text(item.post_type))
					.append($('<span class="date"></span>').text(item.date))
					.append($('<span class="status"></span>').text(item.post_status));
				return $('<li></li>')
					.attr('aria-label', [item.title, item.post_type, item.date, item.post_status].join(', '))
					.data('item.autocomplete', item)
					.append($anchor)
					.appendTo(ul)
					;
			}
		}

		// Initialize lock heartbeat
		if (zoninator.getZoneId() && !$('#zone-locked').length) {
			zoninator.currentLockPeriod = 0;
			zoninator.heartbeatInterval = parseInt(zoninatorOptions.zoneLockPeriod);
			zoninator.maxLockPeriod = parseInt(zoninatorOptions.zoneLockPeriodMax);

			if (zoninator.heartbeatInterval > 0 && zoninator.maxLockPeriod != -1) {
				zoninator.heartbeatInterval = zoninator.heartbeatInterval * 1000;
				zoninator.maxLockPeriod = zoninator.maxLockPeriod * 1000;

				zoninator.updateLock();
			}
		}

		// TODO: move / copy posts to zones
	}

	zoninator.updateLatest = function() {

		zoninator.$zonePostSearch.trigger('loading.start');
		zoninator.ajax('update_recent', {
			zone_id: zoninator.getZoneId(),
			cat    : zoninator.getAdvancedCat(),
			date   : zoninator.getAdvancedDate()
		}, zoninator.addUpdateLatestSuccessCallback);
	}

	zoninator.addUpdateLatestSuccessCallback = function(returnData) {

		zoninator.$zonePostSearch.trigger('loading.end');
		var $list = $(returnData.content);
		$('#zone-post-latest').html($list);

	}

	zoninator.addPost = function(postId) {

		zoninator.$zonePostSearch.trigger('loading.start');

		zoninator.ajax('add_post', {
			zone_id  : zoninator.getZoneId()
			, post_id: postId
		}, zoninator.addPostSuccessCallback);

	}

	zoninator.addPostSuccessCallback = function(returnData) {

		zoninator.$zonePostSearch.trigger('loading.end');

		// Add Post to List
		var $post = $(returnData.content);
		$post.hide()
			.appendTo(zoninator.$zonePostsList)
			.fadeIn()
		;

		zoninator.initZonePost($post);

		// Reorder Posts
		zoninator.updatePostOrder(true);

		wp.a11y.speak(zoninatorOptions.postAdded);
	}

	zoninator.initZonePost = function($elem) {
		$elem.bind('loading.start', function(e) {
			$(this).addClass('loading');
		}).bind('loading.end', function(e) {
			$(this).removeClass('loading');
		});

		$elem.find('.delete').bind('click', function(e) {
			e.preventDefault();
			var postId = zoninator.getPostIdFromElem(this);
			zoninator.removePost(postId);
		});

		// Keyboard alternative to drag and drop.
		$elem.find('.move-up, .move-down').bind('click', function() {
			var $post = $(this).closest('.zone-post'),
				up = $(this).hasClass('move-up'),
				$target = up ? $post.prev('.zone-post') : $post.next('.zone-post');

			if (!$target.length) {
				return;
			}

			if (up) {
				$post.insertBefore($target);
			} else {
				$post.insertAfter($target);
			}

			// Moving the row in the DOM drops focus, so put it back on the button.
			this.focus();
			zoninator.reorderPosts();
			wp.a11y.speak(
				zoninatorOptions.postMoved
					.replace('%1$d', $post.index() + 1)
					.replace('%2$d', zoninator.getZonePosts().length)
			);
		});
	}

	zoninator.removePost = function(postId) {
		zoninator.getPost(postId).trigger('loading.start');

		zoninator.ajax('remove_post', {
			zone_id  : zoninator.getZoneId()
			, post_id: postId
		}, zoninator.removePostSuccessCallback);
	}

	zoninator.removePostSuccessCallback = function(returnData, originalData) {
		var postId = originalData.post_id;

		var $post = zoninator.getPost(postId),
			$next = $post.next('.zone-post').length ? $post.next('.zone-post') : $post.prev('.zone-post');

		$post.fadeOut('slow', function() {
			$(this).remove();
			if (zoninator.getZonePostIds().length)
				zoninator.updatePostOrder(true);
			zoninator.$zonePostsWrap.trigger('loading.end');

			// The focused button has gone, so move focus to a neighboring item, or to the search field.
			($next.length ? $next.find('.delete') : zoninator.$zonePostSearch).trigger('focus');
			wp.a11y.speak(zoninatorOptions.postRemoved);
		});
	}

	zoninator.reorderPosts = function() {
		// Get list of post ids from the DOM
		var zoneId = zoninator.getZoneId()
			, postIds = zoninator.getZonePostIds()
			;

		// Reorder only if DOM list has changed compared to internal list
		if (!compareArrays(postIds, zoninator.getPostOrder())) {
			var data = {
				zone_id: zoneId
				, posts: postIds
			}

			zoninator.$zonePostsWrap.trigger('loading.start');

			// make ajax call to save order
			zoninator.ajax('reorder_posts', data, zoninator.reorderPostsSuccessCallback);
		}
	}

	zoninator.reorderPostsSuccessCallback = function(returnData, originalData) {
		zoninator.$zonePostsWrap.trigger('loading.end');
		zoninator.updatePostOrder(false);

		// The user took some action so reset the lock period
		zoninator.resetCurrentLockPeriod();
	}

	zoninator.updateLock = function() {
		zoninator.ajax('update_lock', {
			zone_id: zoninator.getZoneId()
		}, function(returnData, originalData) {
			// We want to set a max to avoid people leaving their tabs open and then running away for long periods.
			// Ask before letting the lock go, so someone still working can keep it. While the question is open
			// the heartbeat stops, so an abandoned tab still loses the lock.
			if (zoninator.currentLockPeriod >= zoninator.maxLockPeriod) {
				if (!confirm(zoninatorOptions.confirmZoneLock)) {
					location.href = zoninatorOptions.adminUrl;
					return;
				}

				// The question may have been open longer than the lock lasts, so take it again straight away.
				zoninator.resetCurrentLockPeriod();
				zoninator.updateLock();
				return;
			}

			zoninator.currentLockPeriod += zoninator.heartbeatInterval;
			setTimeout(zoninator.updateLock, zoninator.heartbeatInterval);
		}, function(returnData, originalData) {
			// Show alert and reload page to update lock
			alert(zoninatorOptions.errorZoneLock);
			location.reload();
		});
	}

	zoninator.resetCurrentLockPeriod = function() {
		zoninator.currentLockPeriod = 0;
	}

	zoninator.ajax = function(action, values, successCallback, errorCallback, params) {
		var data = {
			action    : zoninator.getAjaxAction(action)
			, _wpnonce: zoninator.getAjaxNonce()
		}
		data = $.extend({}, data, values);

		// Allow developers to filter the ajax parameters
		zoninator.$zonePostSearch.trigger('zoninator.ajax', [action, data]);

		var defaultParams = {
			url       : ajaxurl
			, data    : data
			, dataType: 'json'
			, type    : 'POST'
			, success : function(returnData) {
				zoninator.ajaxSuccessCallback(returnData, data, successCallback, errorCallback);
			}
			, error   : function(returnData) {
				zoninator.ajaxErrorCallback(returnData, data, successCallback, errorCallback);
			}
		}
		params = $.extend({}, defaultParams, params);

		if (action === 'reorder_posts') {
			zoninator.$zonePostsSave
				.prop({
					value   : 'Saving...',
					disabled: 'disabled'
				});
		}

		$.ajax(params);
	}

	zoninator.ajaxSuccessCallback = function(returnData, originalData, successCallback, errorCallback) {
		if (typeof(returnData) === 'undefined' || !returnData.status) {
			// If we didn't get a valid return, it's probably an error
			return zoninator.ajaxErrorCallback(returnData, originalData, successCallback, errorCallback);
		}

		//console.log('ajaxSuccessCallback', returnData, originalData);

		if (originalData.action === 'zoninator_reorder_posts') {
			setTimeout(function() {
				zoninator.$zonePostsSave
					.prop({
						value   : 'Save zone posts',
						disabled: false
					});
				zoninator.$zonePostsSaveInfo
					.removeClass('notice-error')
					.addClass('notice-info')
					.text('Saved at ' + new Date().toLocaleTimeString());
				setTimeout(function() {
					zoninator.$zonePostsSaveInfo
						.removeClass('notice-error notice-info')
				}, 2000);
			}, 250);
		}

		if (returnData.nonce)
			zoninator.updateAjaxNonce(returnData.nonce);

		if (typeof(successCallback) === 'function') {
			return successCallback(returnData, originalData);
		} else {
			alert(returnData.content);
		}
	}

	zoninator.ajaxErrorCallback = function(returnData, originalData, successCallback, errorCallback) {
		if (typeof(returnData) === 'undefined' || !returnData) {
			returnData = {
				status   : 0
				, content: zoninatorOptions.errorGeneral
			}
		}

		//console.log('ajaxErrorCallback', returnData, originalData);

		if (originalData.action === 'zoninator_reorder_posts') {
			zoninator.$zonePostsSaveInfo
				.removeClass('notice-info')
				.addClass('notice-error')
				.text('Error saving posts. Please try again.');
		}

		if (typeof(errorCallback) === 'function') {
			return errorCallback(returnData, originalData);
		} else {
			if (typeof(returnData.content) === 'undefined' || !returnData.content)
				returnData.content = zoninatorOptions.errorGeneral;
			alert(returnData.content);
		}
	}

	zoninator.updateAjaxNonce = function(action, nonce) {
		zoninator.getAjaxNonceField(action).val(nonce);
	}

	zoninator.getAdvancedCat = function() {
		return $('#zone_advanced_filter_taxonomy').length ? zoninator.$zoneAdvancedCat.val() : 0;
	}

	zoninator.getAdvancedDate = function() {
		return $('#zone_advanced_filter_date').length ? zoninator.$zoneAdvancedDate.val() : 0;
	}

	zoninator.getAjaxNonce = function(action) {
		return zoninator.getAjaxNonceField(action).val();
	}

	zoninator.getAjaxNonceField = function(action) {
		action = action || zoninatorOptions.ajaxNonceAction;
		return $('#' + action);
	}

	zoninator.getZoneId = function() {
		return $('#zone_id').length ? $('#zone_id').val() : 0;
	}

	// Get the list of posts from the DOM
	zoninator.getZonePosts = function() {
		return zoninator.$zonePostsList.children();
	}

	zoninator.getPost = function(postId) {
		return $('#zone-post-' + postId);
	}

	zoninator.getPostIdFromElem = function(elem) {
		return $(elem).closest('.zone-post').attr('data-post-id');
	}

	// Get the IDs of post elements in the DOM list
	zoninator.getZonePostIds = function() {
		var ids = []
			, $posts = zoninator.getZonePosts();
		$posts.find('[name="zone-post-id"]').each(function(i, elem) {
			ids.push(elem.value);
		});
		return ids;
	}

	// Get the post list we stored internally
	zoninator.getPostOrder = function() {
		if (!$.isArray(zoninator.currentPostOrder))
			zoninator.updatePostOrder();
		return zoninator.currentPostOrder;
	}

	// Update the internal list based on the list in the DOM
	zoninator.updatePostOrder = function(save) {
		if (save)
			zoninator.reorderPosts();

		zoninator.currentPostOrder = zoninator.getZonePostIds();
		zoninator.renumberPosts();
	}

	// Change the numbers in the UI to match the order of posts in the list
	zoninator.renumberPosts = function() {
		var $numbers = zoninator.$zonePostsList.find('.zone-post-position');
		$numbers.each(function(i, elem) {
			$(elem).text(i + 1);
		});
	}

	zoninator.getAjaxAction = function(action) {
		return 'zoninator_' + action;
	}

	zoninator.emptyFunc = function() {
	}

	zoninator.forceSavePosts = function() {
		// Grab post list from DOM
		var zoneId = zoninator.getZoneId(),
			postIds = zoninator.getZonePostIds(),
			data = {
				zone_id: zoneId,
				posts  : postIds
			}
		// Update internal list to match UI list
		zoninator.updatePostOrder();
		// Save list to server
		zoninator.$zonePostsWrap.trigger('loading.start');
		zoninator.ajax('reorder_posts', data, zoninator.reorderPostsSuccessCallback);
	};

	/**
	 * compareArrays - Compares two arrays!
	 *
	 * Copyright (c) David
	 *      http://stackoverflow.com/questions/1773069/using-jquery-to-compare-two-arrays/1773172#1773172
	 *
	 * Some mods by Mohammad Jangda
	 *
	 * @param Array First Array
	 * @param Array Second Array
	 * @param bool Sort the arrays before comparing?
	 *
	 */
	var compareArrays = function(arr1, arr2, sort) {
		if (arr1.length != arr2.length) return false;

		if (sort) {
			arr1 = arr1.sort(),
				arr2 = arr2.sort();
		}
		for (var i = 0; arr2[i]; i++) {
			if (arr1[i] !== arr2[i]) {
				return false;
			}
		}
		return true;
	};

	$('.zone-toggle-advanced-search').click(function() {
		var $this = $(this),
			currentLabel = $(this).text(),
			altLabel = $(this).data('alt-label');

		$('.zone-advanced-search-filters-wrapper').toggle();

		$this.text(altLabel).data('alt-label', currentLabel)
			.attr('aria-expanded', $this.attr('aria-expanded') !== 'true');
	});

	// TODO: fix this
	function parseIntOrZero(str) {
		var parsed = parseInt(str);
		if (isNaN(parsed) || !parsed) parsed = 0;
		return parsed;
	}

	$(document).ready(function() {
		zoninator.init();
	});

})(jQuery, window);

if (typeof(console) === 'undefined')
	console = {
		log: function() {
		}
	}
