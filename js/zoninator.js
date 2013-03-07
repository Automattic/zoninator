var zoninator = {}

;(function($, window, undefined) {
	
	zoninator.init = function() {
		zoninator.autocompleteCache = {}
		zoninator.autocompleteAjax = {}
		zoninator.$zonePostsList = $('.zone-posts-list');
		zoninator.$zonePostsWrap = $('.zone-posts-wrapper');
		zoninator.$zonePostSearch = $("#zone-post-search");
		zoninator.$zonePostLatest = $("#zone-post-latest");
		zoninator.$zoneAdvancedCat = $("#zone_advanced_filter_taxonomy");
		zoninator.$zoneAdvancedDate = $("#zone_advanced_filter_date");
	
		
		zoninator.$zoneAdvancedDate.change(function() {
			zoninator.updateLatest();
		});

		zoninator.$zoneAdvancedCat.change(function() {
			zoninator.updateLatest();
		});

		zoninator.updatePostOrder();		

		zoninator.initTabManager();
	
		// Bind actions to buttons
		zoninator.initZonePost(zoninator.$zonePostsList.children());
		
		// Initialize sortable
		if(!zoninator.$zonePostsWrap.hasClass('readonly')) {
			zoninator.$zonePostsList.sortable({
				stop: zoninator.reorderPosts
				, placeholder: 'ui-state-highlight'
				, forcePlaceholderSize: true
				//, handle: '.zone-post-handle'
			});
		}
		
		// Bind loading events
		zoninator.$zonePostsWrap
			.bind('loading.start', function() {
				$(this).addClass('loading');
			})
			.bind('loading.end', function() {
				$(this).removeClass('loading');
			});
		
		// Validate form
		// TODO: This is really simplistic validation; beef it up a bit.
		$('#zone-info').submit(function(e) {
			var $form = $(this);
			var $name = $form.find('input[name="name"]');
			if( !$name.val().trim() ) {
				$name.closest( '.zone-field' ).addClass('error'); 
				return false;
			} else {
				$name.closest( '.zone-field' ).removeClass('error'); 
			}
		});

		zoninator.$zonePostLatest.change(function() {
			var $this = $(this),
				post_id = $this.val();
			if ( post_id ) {
				zoninator.addPost( post_id );
				$this.find( '[value="' + post_id + '"]' ).remove();
			}
		});

		

		// Initialize autocomplete
		if(zoninator.$zonePostSearch.length) {
			zoninator.$zonePostSearch
				.bind('loading.start', function(e) {
					$(this).addClass('loading');
				})
				.bind('loading.end', function(e) {
					$(this).removeClass('loading');
				})
				.autocomplete({
					minLength: 3
					// Remote source with caching
					, source: function( request, response ) {
						var term = request.term;

						request.cat = zoninator.getAdvancedCat();
						request.date = zoninator.getAdvancedDate();

						if ( term in zoninator.autocompleteCache ) { //&& request.cat && request.date ) {
							response( zoninator.autocompleteCache[ term ] );
							zoninator.$zonePostSearch.trigger('loading.end');
							return;
						}
					
						// Append more request vars
						request.action = zoninator.getAjaxAction('search_posts');
						request.exclude = zoninator.getZonePostIds();
						
						zoninator.autocompleteAjax = $.getJSON( ajaxurl, request, function( data, status, xhr ) {
							zoninator.autocompleteCache[ term ] = data;
							if ( xhr === zoninator.autocompleteAjax ) {
								response( data );
							}
							zoninator.$zonePostSearch.trigger('loading.end');
						});
					}
					, select: function( e, ui ) {
						zoninator.addPost(ui.item.post_id);
					}
					, search: function( e, ui ) {
						zoninator.$zonePostSearch.trigger('loading.start');
					}
				});

			// Compat with jQuery 1.8 and 1.9; the latter uses ui- prefix for data attribute
			var autocomplete = zoninator.$zonePostSearch.data( 'autocomplete' ) || zoninator.$zonePostSearch.data( 'ui-autocomplete' );

			autocomplete._renderItem = function( ul, item ) {
				var content = '<a>'
					+ '<span class="title">' + item.title + '</span>'
					+ '<span class="type">' + item.post_type + '</span>'
					+ '<span class="date">' + item.date + '</span>'
					+ '<span class="status">' + item.post_status + '</span>'
					+ '</a>';
				return $( '<li></li>' )
					.data( 'item.autocomplete', item )
					.append( content )
					.appendTo( ul )
					;
			}
		}
		
		// Initialize lock heartbeat
		if( zoninator.getZoneId() && ! $('#zone-locked').length ) {
			zoninator.currentLockPeriod = 0;
			zoninator.heartbeatInterval = parseInt( zoninatorOptions.zoneLockPeriod );
			zoninator.maxLockPeriod = parseInt( zoninatorOptions.zoneLockPeriodMax );

			if( zoninator.heartbeatInterval > 0 && zoninator.maxLockPeriod != -1) {
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
			cat: zoninator.getAdvancedCat(),
			date: zoninator.getAdvancedDate()
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
			zone_id: zoninator.getZoneId()
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
	}

	zoninator.removePost = function(postId) {
		zoninator.getPost(postId).trigger('loading.start');
		
		zoninator.ajax('remove_post', {
			zone_id: zoninator.getZoneId()
			, post_id: postId
		}, zoninator.removePostSuccessCallback);	
	}
	
	zoninator.removePostSuccessCallback = function(returnData, originalData) {
		var postId = originalData.post_id;
		
		zoninator.getPost(postId).fadeOut('slow', function() {
			$(this).remove();
			if ( zoninator.getZonePostIds().length )
				zoninator.updatePostOrder(true);
			zoninator.$zonePostsWrap.trigger('loading.end');
		});
	}

	zoninator.reorderPosts = function() {
		// get list of post ids
		var zoneId = zoninator.getZoneId()
			, postIds = zoninator.getZonePostIds()
			;
		
		// Reorder only if changed
		if(!compareArrays(postIds, zoninator.getPostOrder())) {
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
			zoninator.currentLockPeriod += zoninator.heartbeatInterval;
			
			// We want to set a max to avoid people leaving their tabs open and then running away for long periods
			if( zoninator.currentLockPeriod < zoninator.maxLockPeriod ) {
				setTimeout(zoninator.updateLock, zoninator.heartbeatInterval);
			} else {
				alert(zoninatorOptions.errorZoneLockMax);
				location.href = zoninatorOptions.adminUrl;
			}
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
			action: zoninator.getAjaxAction(action)
			, _wpnonce: zoninator.getAjaxNonce()
		}
		data = $.extend({}, data, values);
		
		var defaultParams = {
			url: ajaxurl
			, data: data
			, dataType: 'json'
			, type: 'POST'
			, success: function(returnData) {
				zoninator.ajaxSuccessCallback(returnData, data, successCallback, errorCallback);
			}
			, error: function(returnData) {
				zoninator.ajaxErrorCallback(returnData, data, successCallback, errorCallback);
			}
		}
		params = $.extend({}, defaultParams, params);
		
		$.ajax(params);
	}
	
	zoninator.ajaxSuccessCallback = function(returnData, originalData, successCallback, errorCallback) {
		if(typeof(returnData) === 'undefined' || !returnData.status) {
			// If we didn't get a valid return, it's probably an error
			return zoninator.ajaxErrorCallback(returnData, originalData, successCallback, errorCallback);
		}
		
		//console.log('ajaxSuccessCallback', returnData, originalData);
		
		if(returnData.nonce)
			zoninator.updateAjaxNonce(returnData.nonce);
		
		if(typeof(successCallback) === 'function') {
			return successCallback(returnData, originalData); 
		} else {
			alert(returnData.content);
		}
	}
	
	zoninator.ajaxErrorCallback = function(returnData, originalData, successCallback, errorCallback) {
		if( typeof(returnData) === 'undefined' || !returnData ) {
			returnData = {
				status: 0
				, content: zoninatorOptions.errorGeneral
			}
		}
		
		//console.log('ajaxErrorCallback', returnData, originalData);
		
		if(typeof(errorCallback) === 'function') {
			return errorCallback(returnData, originalData); 
		} else {
			if( typeof(returnData.content) === 'undefined' || !returnData.content )
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
		return $('#' + action );
	}
	
	zoninator.getZoneId = function() {
		return $('#zone_id').length ? $('#zone_id').val() : 0;
	}
	
	zoninator.getZonePosts = function() {
		return zoninator.$zonePostsList.children();
	}
	
	zoninator.getPost = function(postId) {
		return $('#zone-post-' + postId);
	}
	
	zoninator.getPostIdFromElem = function(elem) {
		return $(elem).closest('.zone-post').attr('data-post-id');
	}
	
	zoninator.getZonePostIds = function() {
		var ids = []
			, $posts = zoninator.getZonePosts();
		$posts.find('[name="zone-post-id"]').each(function(i, elem) {
			ids.push(elem.value);
		});
		return ids;
	}
	
	zoninator.getPostOrder = function() {
		if(!$.isArray(zoninator.currentPostOrder))
			zoninator.updatePostOrder();
		return zoninator.currentPostOrder;
	}
	
	zoninator.updatePostOrder = function(save) {
		if(save)
			zoninator.reorderPosts();
		
		zoninator.currentPostOrder = zoninator.getZonePostIds();
		zoninator.renumberPosts();
	}
	
	zoninator.renumberPosts = function() {
		var $numbers = zoninator.$zonePostsList.find('.zone-post-position');
		$numbers.each(function(i, elem) {
		    $(elem).text(i + 1);
        });
	}
	
	zoninator.getAjaxAction = function(action) {
		return 'zoninator_' + action;
	}

	zoninator.emptyFunc = function() {}

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
        
        if(sort) {
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

	// Stolen from nav-menu.js
	zoninator.initTabManager = function() {
		var fixed = $('.nav-tabs-wrapper'),
			fluid = fixed.children('.nav-tabs'),
			active = fluid.children('.nav-tab-active'),
			tabs = fluid.children('.nav-tab'),
			tabsWidth = 0,
			fixedRight, fixedLeft,
			arrowLeft, arrowRight, resizeTimer, css = {},
			marginFluid = 'margin-left',
			marginFixed = 'margin-right',
			msPerPx = 2;

		/**
		 * Refreshes the menu tabs.
		 * Will show and hide arrows where necessary.
		 * Scrolls to the active tab by default.
		 *
		 * @param savePosition {boolean} Optional. Prevents scrolling so
		 * 		  that the current position is maintained. Default false.
		 **/
		refreshMenuTabs = function( savePosition ) {
			var fixedWidth = fixed.width(),
				margin = 0, css = {};
			fixedLeft = fixed.offset().left;
			fixedRight = fixedLeft + fixedWidth;

			if( !savePosition )
				active.makeTabVisible();

			// Prevent space from building up next to the last tab if there's more to show
			var $lastTab = tabs.last();
			if( $lastTab.length && $lastTab.isTabVisible() ) {
				margin = fixed.width() - tabsWidth;
				margin = margin > 0 ? 0 : margin;
				css[marginFluid] = margin + 'px';
				fluid.animate( css, 100, "linear" );
			}

			// Show the arrows only when necessary
			if( fixedWidth > tabsWidth )
				arrowLeft.add( arrowRight ).hide();
			else
				arrowLeft.add( arrowRight ).show();
		}

		$.fn.extend({
			makeTabVisible : function() {
				var t = this.eq(0), left, right, css = {}, shift = 0;

				if( ! t.length ) return this;

				left = t.offset().left;
				right = left + t.outerWidth();

				if( right > fixedRight )
					shift = fixedRight - right;
				else if ( left < fixedLeft )
					shift = fixedLeft - left;

				if( ! shift ) return this;

				css[marginFluid] = "+=" + shift + 'px';
				fluid.animate( css, Math.abs( shift ) * msPerPx, "linear" );
				return this;
			},
			isTabVisible : function() {
				var t = this.eq(0),
					left = t.offset().left,
					right = left + t.outerWidth();
				return ( right <= fixedRight && left >= fixedLeft ) ? true : false;
			}
		});

		// Find the width of all tabs
		tabs.each(function(){
			tabsWidth += $(this).outerWidth(true);
		});

		// Set up fixed margin for overflow, unset padding
		css['padding'] = 0;
		css[marginFixed] = (-1 * tabsWidth) + 'px';
		fluid.css( css );

		// Build tab navigation
		arrowLeft = $('<div class="nav-tabs-arrow nav-tabs-arrow-left"><a>&laquo;</a></div>');
		arrowRight = $('<div class="nav-tabs-arrow nav-tabs-arrow-right"><a>&raquo;</a></div>');
		// Attach to the document
		fixed.wrap('<div class="nav-tabs-nav"/>').parent().prepend( arrowLeft ).append( arrowRight );
		// Set the menu tabs
		refreshMenuTabs();
		// Make sure the tabs reset on resize
		$(window).resize(function() {
			if( resizeTimer ) clearTimeout(resizeTimer);
			resizeTimer = setTimeout( refreshMenuTabs, 200);
		});

		// Build arrow functions
		$.each([{
				arrow : arrowLeft,
				next : "next",
				last : "first",
				operator : "+="
			},{
				arrow : arrowRight,
				next : "prev",
				last : "last",
				operator : "-="
			}], function(){
			var that = this;
			this.arrow.mousedown(function(){
				var marginFluidVal = Math.abs( parseInt( fluid.css(marginFluid) ) ),
					shift = marginFluidVal,
					css = {};

				if( "-=" == that.operator )
					shift = Math.abs( tabsWidth - fixed.width() ) - marginFluidVal;

				if( ! shift ) return;

				css[marginFluid] = that.operator + shift + 'px';
				fluid.animate( css, shift * msPerPx, "linear" );
			}).mouseup(function(){
				var tab, next;
				fluid.stop(true);
				tab = tabs[that.last]();
				while( (next = tab[that.next]()) && next.length && ! next.isTabVisible() ) {
					tab = next;
				}
				tab.makeTabVisible();
			});
		});
	}

	$('.zone-toggle-advanced-search').click( function() {
		$('.zone-advanced-search-filters-wrapper').toggle();
		$('.zone-toggle-advanced-search').toggle();
	});

	// TODO: fix this
	function parseIntOrZero(str) {		
		var parsed = parseInt( str );
		if( isNaN(parsed) || !parsed ) parsed = 0;
		return parsed;
	}

	$(document).ready(function() {
		zoninator.init();

	})
})(jQuery, window);

if(typeof(console) === 'undefined')
	console = { log: function(){} }
