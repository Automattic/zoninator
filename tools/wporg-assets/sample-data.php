<?php
/**
 * Sample content for the WordPress.org screenshots: a small newsroom with
 * posts, zones, a page with the Zone Posts block, and Zone Posts widgets.
 *
 * Run once on a fresh wp-env site (screenshots.mjs does this):
 * npx --no wp-env run cli --env-cwd=wp-content/plugins/<checkout> wp eval-file tools/wporg-assets/sample-data.php
 *
 * @package Automattic\Zoninator
 */

use Automattic\Zoninator\Application\ZoneCurator;
use Automattic\Zoninator\Application\ZoneManager;

// A function, so the script's variables don't become globals.
( static function (): void {
	$manager = zoninator_container()->get( ZoneManager::class );
	$curator = zoninator_container()->get( ZoneCurator::class );

	wp_update_user(
		array(
			'ID'           => 1,
			'display_name' => 'Sam Rivera',
			'first_name'   => 'Sam',
			'last_name'    => 'Rivera',
		)
	);

	$authors = array( 1 );
	foreach ( array(
		'maya' => 'Maya Chen',
		'tom'  => 'Tom Okafor',
		'lena' => 'Lena Fischer',
	) as $login => $name ) {
		$authors[] = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password(),
				'user_email'   => "$login@example.test",
				'role'         => 'author',
				'display_name' => $name,
			)
		);
	}

	// WordPress's own sample post and page would show up in the search results and the menu.
	foreach ( array(
		'hello-world' => 'post',
		'sample-page' => 'page',
	) as $slug => $type ) {
		$sample = get_page_by_path( $slug, OBJECT, $type );
		if ( $sample ) {
			wp_delete_post( $sample->ID, true );
		}
	}

	$cats = array();
	foreach ( array( 'News', 'Sports', 'Culture', 'Business' ) as $name ) {
		$term          = term_exists( $name, 'category' ) ?: wp_insert_term( $name, 'category' );
		$cats[ $name ] = (int) $term['term_id'];
	}

	$stories = array(
		array( 'News', 'City council approves plan for 40 miles of protected bike lanes', 'The vote ends two years of debate, and construction starts on the first corridors in the spring.' ),
		array( 'News', 'Storm leaves thousands without power across the region', 'Crews expect to restore most homes by Thursday evening. Schools in three districts are closed.' ),
		array( 'News', 'New light rail line opens with free rides all weekend', 'The 9-mile extension links the airport to downtown in 24 minutes.' ),
		array( 'News', 'Public library extends weekend hours at every branch', 'Branches will open on Sundays from next month, after a rise in visitors.' ),
		array( 'News', 'Water rates to rise 4% to fund pipe replacement', 'The utility says the increase will cost a typical household about $3 a month.' ),
		array( 'Business', 'Local coffee roaster opens its third café downtown', 'The new shop doubles as a training school for baristas.' ),
		array( 'Business', 'Port traffic hits a record as new cranes come online', 'Container volumes rose 12% on last year, the busiest quarter yet.' ),
		array( 'Business', 'Startup turns food waste into compost for city parks', 'The company now collects from 200 restaurants every week.' ),
		array( 'Business', 'Farmers market moves to a year-round indoor hall', 'Vendors say the move will let them sell through the winter.' ),
		array( 'Sports', 'Late penalty seals derby win in front of a sell-out crowd', 'A stoppage-time spot kick settled a tense match and sent the home side top.' ),
		array( 'Sports', 'Marathon route revealed, with a finish by the waterfront', 'Organizers expect 20,000 runners on the new course in April.' ),
		array( 'Sports', 'Veteran captain announces retirement after 14 seasons', 'She leaves as the club\'s record appearance maker.' ),
		array( 'Sports', 'High school swimmer breaks a 20-year state record', 'The 17-year-old took nearly a second off the 200m freestyle mark.' ),
		array( 'Sports', 'Season preview: five questions facing the team', 'A new coach, a young roster and a tough opening month.' ),
		array( 'Culture', 'Summer festival announces its biggest lineup yet', 'More than 80 acts will play across four stages in the park.' ),
		array( 'Culture', 'Review: a bold new staging of a classic at the Playhouse', 'A spare set and a young cast breathe fresh life into a familiar story.' ),
		array( 'Culture', 'Five books to read this winter', 'Our critics pick the novels and memoirs worth curling up with.' ),
		array( 'Culture', 'Museum reopens its modern art wing after renovation', 'The refurbished galleries add natural light and room for 200 more works.' ),
		array( 'Culture', 'The best new restaurants that opened this year', 'From a tiny ramen counter to a rooftop grill, our food writer\'s favorites.' ),
		array( 'News', 'Volunteers plant 1,000 trees along the river path', 'The project aims to shade the trail and cut flooding downstream.' ),
	);

	$posts = array();
	foreach ( $stories as $i => list( $cat, $title, $excerpt ) ) {
		$posts[] = wp_insert_post(
			array(
				'post_title'     => $title,
				'post_excerpt'   => $excerpt,
				'post_content'   => "<!-- wp:paragraph --><p>$excerpt</p><!-- /wp:paragraph -->",
				'post_status'    => 'publish',
				'comment_status' => 'closed',
				'post_category'  => array( $cats[ $cat ] ),
				'post_author'    => $authors[ $i % count( $authors ) ],
				'post_date'      => gmdate( 'Y-m-d H:i:s', time() - ( $i * 9 + 2 ) * HOUR_IN_SECONDS ),
			)
		);
	}

	$zones = array();
	foreach (
		array(
			'homepage'      => array( 'Homepage', 'The lead stories at the top of the front page.' ),
			'top-stories'   => array( 'Top Stories', 'The stories everyone is reading today.' ),
			'sports'        => array( 'Sports', 'The best of the sports desk.' ),
			'culture-picks' => array( 'Culture Picks', 'Reviews and features chosen by the culture editor.' ),
			'newsletter'    => array( 'Morning Newsletter', 'Stories for tomorrow\'s email.' ),
		) as $slug => list( $name, $description )
	) {
		$zones[ $slug ] = $manager->create( $slug, $name, array( 'description' => $description ) )->get_id();
	}

	$curator->set_posts( $zones['homepage'], array( $posts[0], $posts[9], $posts[2], $posts[14], $posts[6] ) );
	$curator->set_posts( $zones['top-stories'], array( $posts[1], $posts[0], $posts[10], $posts[17], $posts[5], $posts[19] ) );
	$curator->set_posts( $zones['sports'], array( $posts[9], $posts[12], $posts[10], $posts[11] ) );
	$curator->set_posts( $zones['culture-picks'], array( $posts[15], $posts[16], $posts[18] ) );
	$curator->set_posts( $zones['newsletter'], array( $posts[3], $posts[4] ) );

	update_option( 'blogname', 'The Daily Harbor' );
	update_option( 'blogdescription', 'News from the waterfront city' );
	update_option( 'permalink_structure', '/%postname%/' );
	flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- A one-off WP-CLI script on a local site.

	// A page with the Zone Posts block: the Top Stories zone, in a grid with dates.
	$block = sprintf(
		'<!-- wp:heading --><h2 class="wp-block-heading">Top stories</h2><!-- /wp:heading -->
	<!-- wp:query {"queryId":1,"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","orderBy":"date","inherit":false,"zoninatorZone":%d},"namespace":"zoninator/zone-posts"} -->
	<div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
	<!-- wp:post-title {"isLink":true,"fontSize":"large"} /-->
	<!-- wp:post-date {"fontSize":"small"} /-->
	<!-- /wp:post-template --></div>
	<!-- /wp:query -->',
		$zones['top-stories']
	);
	wp_insert_post(
		array(
			'post_title'   => 'Today',
			'post_name'    => 'today',
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => $block,
		)
	);

	// Zone Posts widgets for a classic theme's sidebar.
	update_option(
		'widget_zoninator_zoneposts_widget',
		array(
			2              => array(
				'zone_id'          => $zones['top-stories'],
				'show_description' => 1,
			),
			3              => array(
				'zone_id'          => $zones['culture-picks'],
				'show_description' => 0,
			),
			'_multiwidget' => 1,
		)
	);
	$sidebars              = get_option( 'sidebars_widgets', array() );
	$sidebars['sidebar-1'] = array( 'zoninator_zoneposts_widget-2', 'zoninator_zoneposts_widget-3' );
	update_option( 'sidebars_widgets', $sidebars );
} )();
