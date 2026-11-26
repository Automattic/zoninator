var zoninator = {};

(function($, window, undefined) {

	zoninator.init = function() {
		zoninator.autocompleteCache = {};
		zoninator.autocompleteAjax = {};
		zoninator.$zonePostsList = $('.zone-posts-list');
		zoninator.$zonePostsWrap = $('.zone-posts-wrapper');
		zoninator.$zonePostSearch = $('#zone-post-search');
		zoninator.$zoneAdvancedCat = $('#zone_advanced_filter_taxonomy');
		zoninator.$zoneAdvancedDate = $('#zone_advanced_filter_date');
		zoninator.$zoneSubmit = $('#zone-info input[type="submit"]');
		zoninator.$zonePostsSaveInfo = $('#zone-posts-save-info');

		// The filters narrow both the latest content and search results, so drop results fetched without them.
		zoninator.$zoneAdvancedDate.add(zoninator.$zoneAdvancedCat).change(function() {
			zoninator.autocompleteCache = {};
		});

		// Update the currentPostOrder property which keeps track of the list order internally
		zoninator.updatePostOrder();

		// Bind actions to buttons
		zoninator.initZonePost(zoninator.$zonePostsList.children());

		// Initialize sortable - call reorderPosts method when the user drops a post element
		if (!zoninator.$zonePostsWrap.hasClass('readonly')) {
			zoninator.$zonePostsList.sortable({
				stop                  : zoninator.reorderPosts
				, placeholder         : 'sortable-placeholder'
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
				$('.zone-posts-save-input .spinner').addClass('is-active');
			})
			.bind('loading.end', function() {
				$('.zone-posts-save-input .spinner').removeClass('is-active');
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

		// Initialize autocomplete. With nothing typed, it offers the latest content; typing searches.
		if (zoninator.$zonePostSearch.length) {
			zoninator.$zonePostSearch
				.on('focus click', function() {
					if (!this.value) {
						$(this).autocomplete('search', '');
					}
				})
				.autocomplete({
					minLength: 0
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

						request.exclude = zoninator.getZonePostIds();
						request.zone_id = zoninator.getZoneId();

						// Allow developers to hook onto the request
						zoninator.$zonePostSearch.trigger('search.request', request);

						var search = zoninator.autocompleteAjax = wp.apiFetch({ path: '/zoninator/v1/search?' + $.param(request) });
						search.then(function(data) {
							// Give each result a value, so moving through them with the arrow keys shows and announces its title.
							data = $.map(data, function(item) {
								return $.extend({ value: item.title }, item);
							});
							zoninator.autocompleteCache[term] = data;
							// Answer every search, even one a newer search replaced. Autocomplete
							// shows only the latest answer, but keeps its loading spinner until
							// each search it started has had one.
							response(data);
							zoninator.$zonePostSearch.trigger('loading.end');
						}, function() {
							// Autocomplete waits for a response, so give it an empty one.
							response([]);
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
				var type = item.post_type_label || item.post_type,
					status = item.post_status_label || item.post_status,
					$anchor = $('<div></div>')
						.append($('<span class="title"></span>').text(item.title))
						.append($('<span class="meta"></span>').text([type, item.date, status].join(zoninatorOptions.metaSeparator)));
				return $('<li></li>')
					.attr('aria-label', [item.title, type, item.date, status].join(zoninatorOptions.listSeparator))
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
	}

	zoninator.addPost = function(postId) {

		zoninator.$zonePostSearch.trigger('loading.start');

		zoninator.rest('add_post', 'POST', zoninator.zonePostPath(postId), {
			zone_id  : zoninator.getZoneId()
			, post_id: postId
		}, function() {
			// The post is in the zone. The REST API returns data only, so the row to show for it comes
			// from the screen's own route, which runs the columns sites add in PHP.
			wp.apiFetch({ path: zoninator.zonePostPath(postId) + '/row' }).then(zoninator.addPostSuccessCallback, function(error) {
				zoninator.ajaxErrorCallback({ status: 0, content: error && error.message }, { action: zoninator.getAjaxAction('add_post') });
			});
		});

	}

	zoninator.addPostSuccessCallback = function(returnData) {

		zoninator.$zonePostSearch.trigger('loading.end');

		// Add Post to List
		var $post = $(returnData.html);
		$post.hide()
			.appendTo(zoninator.$zonePostsList)
			.fadeIn()
		;

		zoninator.initZonePost($post);

		// The post is now excluded from suggestions, so fetch them afresh.
		zoninator.autocompleteCache = {};

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

		zoninator.rest('remove_post', 'DELETE', zoninator.zonePostPath(postId), {
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
			zoninator.autocompleteCache = {};
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

			zoninator.savePostOrder(data);
		}
	}

	zoninator.reorderPostsSuccessCallback = function(returnData, originalData) {
		zoninator.$zonePostsWrap.trigger('loading.end');
		zoninator.updatePostOrder(false);

		// The user took some action so reset the lock period
		zoninator.resetCurrentLockPeriod();
	}

	zoninator.updateLock = function() {
		zoninator.rest('update_lock', 'PUT', '/zoninator/v1/zones/' + zoninator.getZoneId() + '/lock', {
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
		}, undefined, function(returnData, originalData) {
			// Someone else has the zone. Reloading shows who, and the zone read only.
			location.reload();
		});
	}

	zoninator.resetCurrentLockPeriod = function() {
		zoninator.currentLockPeriod = 0;
	}

	// The zone's posts replace the saved list, which is how the order is saved.
	zoninator.savePostOrder = function(data) {
		zoninator.rest('reorder_posts', 'PUT', '/zoninator/v1/zones/' + data.zone_id + '/posts', data, zoninator.reorderPostsSuccessCallback, {
			post_ids: data.posts
		});
	}

	zoninator.zonePostPath = function(postId) {
		return '/zoninator/v1/zones/' + zoninator.getZoneId() + '/posts/' + postId;
	}

	// Sends a request to the zoninator/v1 REST API, and handles the response.
	zoninator.rest = function(action, method, path, values, successCallback, body, errorCallback) {
		var data = $.extend({ action: zoninator.getAjaxAction(action) }, values);

		// Still fired for code that watched these requests when they went through admin-ajax.
		zoninator.$zonePostSearch.trigger('zoninator.ajax', [action, data]);

		wp.apiFetch({ path: path, method: method, data: body }).then(function(returnData) {
			zoninator.ajaxSuccessCallback($.extend({ status: 1 }, returnData), data, successCallback, errorCallback);
		}, function(error) {
			zoninator.ajaxErrorCallback({ status: 0, content: error && error.message }, data, successCallback, errorCallback);
		});
	}

	zoninator.ajaxSuccessCallback = function(returnData, originalData, successCallback, errorCallback) {
		if (typeof(returnData) === 'undefined' || !returnData.status) {
			// If we didn't get a valid return, it's probably an error
			return zoninator.ajaxErrorCallback(returnData, originalData, successCallback, errorCallback);
		}

		//console.log('ajaxSuccessCallback', returnData, originalData);

		if (originalData.action === 'zoninator_reorder_posts') {
			$('#zone-posts-notices').empty();
			zoninator.$zonePostsSaveInfo.text(zoninatorOptions.savedAt.replace('%s', new Date().toLocaleTimeString()));
		}

		if (typeof(successCallback) === 'function') {
			return successCallback(returnData, originalData);
		}
	}

	zoninator.ajaxErrorCallback = function(returnData, originalData, successCallback, errorCallback) {
		if (typeof(returnData) === 'undefined' || !returnData) {
			returnData = {
				status   : 0
				, content: zoninatorOptions.errorGeneral
			}
		}

		// Whatever failed has stopped loading.
		zoninator.$zonePostsWrap.trigger('loading.end');
		zoninator.getZonePosts().removeClass('loading');

		if (typeof(errorCallback) === 'function') {
			return errorCallback(returnData, originalData);
		}

		if (originalData.action === 'zoninator_reorder_posts') {
			zoninator.notice(zoninatorOptions.errorSave, zoninator.forceSavePosts);
		} else {
			zoninator.notice(returnData.content || zoninatorOptions.errorGeneral);
		}
	}

	// Shows an error above the zone's content, with an optional button to try again, and announces it.
	zoninator.notice = function(message, retry) {
		var $notice = $('<div class="notice notice-error inline"><p></p></div>');
		$notice.find('p').text(message);
		if (typeof(retry) === 'function') {
			$notice.find('p').append(' ', $('<button type="button" class="button-link"></button>')
				.text(zoninatorOptions.retry)
				.on('click', function() {
					$notice.remove();
					retry();
				}));
		}
		$('#zone-posts-notices').empty().append($notice);
		wp.a11y.speak(message, 'assertive');
	}

	zoninator.getAdvancedCat = function() {
		return $('#zone_advanced_filter_taxonomy').length ? zoninator.$zoneAdvancedCat.val() : 0;
	}

	zoninator.getAdvancedDate = function() {
		return $('#zone_advanced_filter_date').length ? zoninator.$zoneAdvancedDate.val() : 0;
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
		zoninator.savePostOrder(data);
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

	$(document).ready(function() {
		zoninator.init();
	});

})(jQuery, window);

if (typeof(console) === 'undefined')
	console = {
		log: function() {
		}
	}
