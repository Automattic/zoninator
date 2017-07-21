/*

### Zoninator Admin Acceptance tests

Requires Linux/OSX and working CasperJS and WordPress installations.

Links

- CasperJS: http://casperjs.org/
- Local WordPress setup: https://github.com/Chassis/Chassis
  or https://roots.io/trellis/ or https://github.com/Varying-Vagrant-Vagrants/VVV

### Usage

expects that the following environment variables are set:

 	export WP_INT_BASEURL='http://example.com'
 	export WP_INT_ADMIN_ROOT='/wp/wp-admin'
 	export WP_INT_ADMIN_USERNAME='user'
 	export WP_INT_ADMIN_PASS='pass'
	export WP_INT_DEBUG=''

### Running the tests

 	casperjs test tests/acceptance/test_zoninator_admin.js

for additional debug data run `WP_INT_DEBUG=1 casperjs test tests/acceptance/test_zoninator_admin.js`
*/

var system = require('system');

var WAIT_TIMEOUT = 5000;

var baseUrl       = system.env.WP_INT_BASEURL        || 'http://vagrant.local';
var adminRoot     = system.env.WP_INT_ADMIN_ROOT     || '/wp/wp-admin';
var adminUserName = system.env.WP_INT_ADMIN_USERNAME || 'admin';
var adminPassword = system.env.WP_INT_ADMIN_PASS     || 'password';
var debug         = (system.env.WP_INT_DEBUG) ? true : false;

var adminUrl                 = baseUrl + adminRoot,
	adminAddNewPostUrl       = adminUrl + '/post-new.php',
	adminPostIndex           = adminUrl + '/edit.php',
	adminZoninatorPage       = adminUrl + '/admin.php?page=zoninator',
	adminActivePluginIndex   = adminUrl + '/plugins.php?plugin_status=active',
	integrationTestZoneTitle = 'Integration Test Zone';


if (debug) {
	casper.options.verbose  = true;
	casper.options.logLevel = 'debug';
}


casper.test.begin('Zoninator Acceptance Tests', {
	test: function (test) {
		"use strict";

		casper.zoneDeleted = function (casper) {
			var zones = this.fetchText('.zone-tab');
			return (zones.indexOf(integrationTestZoneTitle) === -1);
		};

		casper.assertPostCanBeRemovedFromZone = function (postId) {
			casper.then(function () {
				this.echo('When I press Remove on zone post with id ' + postId);
				var selector = '#zone-post-' + postId;
				casper.waitForSelector(selector, function () {
					casper.thenClick(selector + ' .delete', function () {
					});

					casper.then(function () {
						this.echo('And I wait for the transition effect');
						this.wait(1000);
					});

					casper.then(function () {
						test.assertNotExists(selector, 'Then the post is removed from the Zone');
					});
				});
			});
		};

		casper.assertCanAddPostToZone = function (postId) {
			casper.waitForSelector('#zone-post-latest', function () {
				this.echo('When I select the post with id ' + postId);
				this.fillSelectors('.zone-search-wrapper', {
				'#zone-post-latest' :  postId
				}, false);
			});

			casper.then(function () {
				casper.waitForSelector('#zone-post-' + postId, function () {
					test.assertExists('#zone-post-' + postId, 'Then Post with id ' + postId + ' is added to the Zone');
				});
			});
		};

		casper.setFilter('page.confirm', function(message) {
	        this.echo('And the Page displayed a message that will be confirmed: ' + message);
	        return true;
	    });

		casper.on('resource.requested', function (request) {
			// Wait on All REST API AJAX requests
			// This should be called within the test context and
			// we should not be using PhatomJS onResourceRequested
			// as it causes some weirdness
			if (request.url.indexOf('zoninator/v1') >= 0) {
				var formattedRequest = [request.method, request.url].join(' ');
				this.echo('And I Wait until XHR Request completes: ' + formattedRequest, 'info');
				casper.waitForResource(request.url, function(){
					this.echo('And the XHR request is completed: ' + formattedRequest, 'info');
					this.wait(250);
				}, function(){
					this.echo('But the XHR request times out: ' + formattedRequest, 'info');
				}, WAIT_TIMEOUT);
			}
		});

		casper.on('resource.received', function (response){
			// Capture responses for all REST API AJAX requests
			if (response.url.indexOf('zoninator/v1') >= 0) {
				var parts = ['id:', response.id, 'status:', response.status, 'url:', response.url]
				this.log('XHR Response received: ' + parts.join(' '), 'info');
			}
		});

	    casper.start(adminUrl, function() {
			this.echo('When I visit ' + adminUrl);
	        test.assertTitle("WordPress Site › Log In", "I am at the login page");
	        test.assertExists('form[name="loginform"]', "And I can find a login form");
			this.echo('When I fill the login form and submit');
	        this.fill('form[name="loginform"]', {
	            log: adminUserName,
				pwd: adminPassword
	        }, true);
	    });

	    casper.then(function() {
	        test.assertTitle('Dashboard ‹ WordPress Site — WordPress',
							 'I am at WordPress Admin Dashboard');
	    });

		casper.thenOpen(adminPostIndex);

		casper.waitForSelector('tr.status-publish', function () {
			test.assertExists('tr.status-publish',
			                  'The site has published posts');
		});

		casper.thenOpen(adminActivePluginIndex);

		casper.waitForSelector('.plugin-title', function () {
			test.assertSelectorHasText('.plugin-title',
			                           'Zone Manager (Zoninator)',
									   'Zoninator Plugin is active');
		});

		casper.thenOpen(adminZoninatorPage);

		casper.then(function () {
			test.assertTitle('Zoninator ‹ WordPress Site — WordPress',
			                 'Zoninator Page Exists');
		});

		casper.then(function () {
			if (!this.zoneDeleted()) {
				this.echo('Test Zone Found... cleaning up');
				casper.then(function () {
					this.clickLabel(integrationTestZoneTitle, 'a');
				});

				casper.then(function () {
					this.clickLabel('Delete','a');
				});
			}
		});

		casper.waitForSelector('form[id="zone-info"]', function () {
			test.assertExists('form[id="zone-info"]', "zone-info form is found");
	        this.fill('form[id="zone-info"]', {
	            name: integrationTestZoneTitle,
				description: 'Zone used by integration tests and can be safely deleted'
	        }, true);
		});

		casper.waitForSelector('.zone-tab', function () {
			test.assertSelectorHasText('.zone-tab',
									   integrationTestZoneTitle,
									   'Integration Test Zone created');
		});

		casper.then(function () {
			this.clickLabel(integrationTestZoneTitle, 'span',
			                'I Click on the zone Titled ' + integrationTestZoneTitle);
		});

		casper.then(function () {
			test.assertUrlMatch(/zoninator&action=edit&zone_id=/, 'Then Edit Zone Page is Rendered');
			test.assertSelectorHasText('#zone-description',
									   'Zone used by integration tests and can be safely deleted',
									   'And The Zone contains the expected description');
		});

		casper.waitForSelector('form[id="zone-info"]', function () {
			test.assertExists('form[id="zone-info"]',
							  'zone-info form is found and ready for Editing');
	        this.fill('form[id="zone-info"]', {
				description: 'Edited Zone used by integration tests and can be safely deleted'
	        }, true);
		});

		casper.waitForSelector('#zone-description', function () {
			test.assertUrlMatch(/zoninator&action=edit&zone_id=/, 'Then Edit Zone Page is Rendered');
			test.assertSelectorHasText('#zone-description',
									   'Edited Zone used by integration tests and can be safely deleted',
									   'And the Edited Zone contains expected description');

		});

		var recentPosts = [];

		casper.waitForSelector('#zone-post-latest', function () {
			recentPosts = this.getElementsAttribute('#zone-post-latest > option', 'value');
			this.echo('And the following recent posts are available: [' + recentPosts + ']');
			test.assertTrue(recentPosts.length > 2,
						    'And I have at least two posts that can be added to the Zone');
		});

		casper.then(function () {
			this.assertCanAddPostToZone(recentPosts[1]);
		});

		casper.then(function () {
			this.assertCanAddPostToZone(recentPosts[2]);
		});

		casper.waitForSelector('#zone-post-latest', function () {
			var recentPostsAfterAdditions = this.getElementsAttribute('#zone-post-latest > option', 'value');
			var selectBoxDifference = recentPosts.length - recentPostsAfterAdditions.length;
			test.assertTrue(selectBoxDifference === 2, 'Then the selectable recent posts decrease by 2');
		});

		casper.then(function () {
			this.assertPostCanBeRemovedFromZone(recentPosts[2]);
		});

		casper.then(function () {
			this.assertPostCanBeRemovedFromZone(recentPosts[1]);
		});

		casper.waitForSelector('#zone-post-latest', function () {
			var recentPostsAfterAdditions = this.getElementsAttribute('#zone-post-latest > option', 'value');
			var selectBoxDifference = recentPosts.length - recentPostsAfterAdditions.length;
			test.assertTrue(selectBoxDifference === 2, 'Then the selectable recent posts do not increase by 2 (and this is unexpected)');
		});

		// Deleting a zone

		casper.then(function () {
			this.clickLabel(integrationTestZoneTitle, 'span');
		});

		casper.then(function () {
			this.clickLabel('Delete','a');
		});

		casper.then(function () {
			test.assertTrue(this.zoneDeleted(), 'A Zone can be deleted');
		});

	    casper.run(function() {
	        test.done();
	    });
	}
});
