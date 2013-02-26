<?php

class GO_Post_Stats
{
	public $wpcom_api_key = ''; // get yours at http://apikey.wordpress.com/
	public $taxonomies;
	public $date_greater_stamp;
	public $date_greater;
	public $date_lesser_stamp;
	public $date_lesser;
	public $calendar;

	public function __construct( $taxonomies = array() )
	{
		$this->taxonomies = (array) $taxonomies;
		
		add_action( 'init', array( $this, 'init' ) );
	} // END __construct

	// add the menu item to the dashboard
	public function admin_menu_init()
	{
		$this->menu_url = admin_url( 'index.php?page=go-post-stats' );

		add_submenu_page( 'index.php', 'GigaOM Post Stats', 'GigaOM Post Stats', 'edit_posts', 'go-post-stats', array( $this, 'admin_menu' ) );
	} // END admin_menu_init

	public function init()
	{
		add_action( 'admin_menu', array( $this, 'admin_menu_init' ) );
		
		if ( is_admin() )
		{
			wp_enqueue_style( 'go-post_stats', plugins_url( 'css/go-post-stats.css', __FILE__ ), array(), '1' );
		}
	} // END init

	// the stats page/admin menu
	public function admin_menu()
	{
		echo '<div class="wrap">';
		screen_icon('index');

		// if stats are requested, show them
		if( isset( $_GET['type'], $_GET['key'] ))
		{

			// set the upper limit of posts
			if( isset( $_GET['date_greater'] ) && strtotime( urldecode( $_GET['date_greater'] ) ) )
			{
				$this->date_greater_stamp = strtotime( urldecode( $_GET['date_greater'] ) );
				$this->date_greater = date( 'Y-m-d', $this->date_greater_stamp );
			}
			else
			{
				$this->date_greater_stamp = time();
				$this->date_greater = date( 'Y-m-d', $this->date_greater_stamp );
			}



			// set the lower limit of posts
			if( isset( $_GET['date_lesser'] ) && strtotime( urldecode( $_GET['date_lesser'] ) ) )
			{
				$this->date_lesser_stamp = strtotime( urldecode( $_GET['date_lesser'] ) );
				$this->date_lesser = date( 'Y-m-d', $this->date_lesser_stamp );
			}
			else
			{
				$this->date_lesser_stamp = strtotime( '-31 days' );
				$this->date_lesser = date( 'Y-m-d', $this->date_lesser_stamp );
			}



			// prefill the results list
			$this->pieces = (object) array(
				'day' => NULL,
				'posts' => NULL,
				'pvs' => NULL,
				'comments' => NULL,
				'pro_links' => NULL,
				'events_links' => NULL,
			);

			$temp_time = $this->date_lesser_stamp;
			do
			{
				$temp_date = date( 'Y-m-d', $temp_time );
				$this->calendar[ $temp_date ] = clone $this->pieces;
				$this->calendar[ $temp_date ]->day = $temp_date;
				$temp_time += 86400;
			}
			while( $temp_time < $this->date_greater_stamp );
			$this->calendar = array_reverse( $this->calendar );



			// run the stats
			if( 'author' == $_GET['type'] && ( $author = get_user_by( 'id', $_GET['key'] ) ) )
			{
					echo '<h2>Stats for ' . esc_html( $author->display_name ) . '</h2>';
					$this->get_author_stats( $_GET['key'] );				
			}
			elseif( taxonomy_exists( $_GET['type'] ) && term_exists( $_GET['key'] , $_GET['type'] ) )
			{
					echo '<h2>Stats for ' . sanitize_title_with_dashes( $_GET['type'] ) . ':' .  sanitize_title_with_dashes( $_GET['key'] ) . '</h2>';
					$this->get_taxonomy_stats( $_GET['type'] , $_GET['key'] );
			}
		}

		echo '<h2>Select a knife to slice through the stats</h2>';
		// display a picker for the time period
		$this->pick_month();

		// print lists of items people can get stats on
		// authors here
		$authors = $this->get_authors_list();
		if( is_array( $authors ))
		{
			echo '<h2>Authors</h2>';
			$this->do_list( $authors );
		}

		// all configured taxonomies here
		foreach( $this->taxonomies as $tax )
		{
			$terms = $this->get_terms_list( $tax );
			if( is_array( $terms ))
			{
				echo '<h2>'. $tax .'</h2>';
				$this->do_list( $terms , $tax );
			}
		}
		
		echo '</div>';
	} // END admin_menu

	// a filter for the posts sql to limit by date range
	public function posts_where( $where = '' )
	{
		$where .= " AND post_date <= '{$this->date_greater}' AND post_date >= '{$this->date_lesser}'";
		return $where;
	} // END posts_where

	// get a list of posts by author to display
	public function get_author_stats( $author )
	{
		add_filter( 'posts_where', array( $this, 'posts_where' ));
		$query = new WP_Query( array( 
			'author' => (int) $author,
			'posts_per_page' => -1,
		) );
		remove_filter( 'posts_where', array( $this, 'posts_where' ));

		if( ! isset( $query->posts ))
		{
			return FALSE;
		}

		return $this->display_stats( $query->posts );
	} // END get_author_stats

	// get a list of posts by taxonomy to display
	public function get_taxonomy_stats( $taxonomy , $term )
	{
		add_filter( 'posts_where', array( $this, 'posts_where' ));
		$query = new WP_Query( array( 
			'taxonomy' => $taxonomy,
			'term' => $term,
			'posts_per_page' => -1,
		));
		remove_filter( 'posts_where', array( $this, 'posts_where' ));

		if( ! isset( $query->posts ))
		{
			return FALSE;
		}

		return $this->display_stats( $query->posts );
	} // END get_taxonomy_stats

	// actually display the stats for the selected posts
	public function display_stats( $posts )
	{
		if( ! is_array( $posts ))
		{
			return FALSE;
		}

		// iterate through the posts, aggregate their stats, and assign those into the calendar
		foreach( $posts as $post )
		{
			$post_date = date( 'Y-m-d', strtotime( $post->post_date ));
			$this->calendar[ $post_date ]->day = $post_date;
			$this->calendar[ $post_date ]->posts++;
			$this->calendar[ $post_date ]->pvs += $this->get_pvs( $post->ID );
			$this->calendar[ $post_date ]->comments += $post->comment_count;
			if( preg_match( '/pro\.gigaom\.com/', $post->post_content ))
			{
				$this->calendar[ $post_date ]->pro_links++;			
			}
			if( preg_match( '/event(s?)\.gigaom\.com/', $post->post_content ))
			{
				$this->calendar[ $post_date ]->events_links++;
			}
		}

		// display the aggregated stats in a table
		echo '
		<h3>Post performance by date published</h3>
		<table border="0" cellspacing="0" cellpadding="0">
			<tr>
				<th>Day</th>
				<th>Posts</th>
				<th>PVs</th>
				<th>PVs/post</th>
				<th>Comments</th>
				<th>Comments/post</th>
				<th>w/Pro links</th>
				<th>w/events links</th>
			</tr>
		';

		// iterate through and generate the summary stats (yes, this means I'm iterating extra)
		$summary = clone $this->pieces;
		foreach( $this->calendar as $day )
		{
			$summary->day++;
			$summary->posts += $day->posts;
			$summary->pvs += $day->pvs;
			$summary->comments += $day->comments;
			$summary->pro_links += $day->pro_links;			
			$summary->events_links += ( isset( $day->event_links ) ) ? $day->event_links : '';
		}

		// print the summary row for all these stats
		printf( '
			<tr class="summary">
				<td>%1$s</td>
				<td>%2$s</td>
				<td>%3$s</td>
				<td>%4$s</td>
				<td>%5$s</td>
				<td>%6$s</td>
				<td>%7$s</td>
				<td>%8$s</td>
			</tr>', 
			$summary->day .' days',
			$summary->posts ? $summary->posts : 0,
			$summary->pvs ? number_format( $summary->pvs ) : 0,
			$summary->posts ? number_format( ( $summary->pvs / $summary->posts ), 1 ) : 0,
			$summary->comments ? number_format( $summary->comments ) : 0,
			$summary->posts ? number_format( ( $summary->comments / $summary->posts ), 1 ) : 0,
			$summary->pro_links ? $summary->pro_links : 0,
			isset( $summary->event_links ) ? $summary->event_links : 0
		);

		// iterate through the calendar (includes empty days), print stats for each day
		foreach( $this->calendar as $day )
		{
			printf( '
				<tr>
					<td>%1$s</td>
					<td>%2$s</td>
					<td>%3$s</td>
					<td>%4$s</td>
					<td>%5$s</td>
					<td>%6$s</td>
					<td>%7$s</td>
					<td>%8$s</td>
				</tr>', 
				$day->day,
				$day->posts ? $day->posts : '&nbsp;',
				$day->pvs ? number_format( $day->pvs ): '&nbsp;',
				$day->posts ? number_format( ( $day->pvs / $day->posts ), 1 ) : '&nbsp;',
				$day->comments ? number_format( $day->comments ) : '&nbsp;',
				$day->posts ? number_format( ( $day->comments / $day->posts ), 1 ) : '&nbsp;',
				$day->pro_links ? $day->pro_links : '&nbsp;',
				isset( $day->event_links ) ? $day->event_links : '&nbsp;'
			);

		}

		// print the summary row for all these stats
		printf( '
			<tr class="summary-footer">
				<td>%1$s</td>
				<td>%2$s</td>
				<td>%3$s</td>
				<td>%4$s</td>
				<td>%5$s</td>
				<td>%6$s</td>
				<td>%7$s</td>
				<td>%8$s</td>
			</tr>', 
			$summary->day .' days',
			$summary->posts ? $summary->posts : 0,
			$summary->pvs ? number_format( $summary->pvs ) : 0,
			$summary->posts ? number_format( ( $summary->pvs / $summary->posts ), 1 ) : 0,
			$summary->comments ? number_format( $summary->comments ) : 0,
			$summary->posts ? number_format( ( $summary->comments / $summary->posts ), 1 ) : 0,
			$summary->pro_links ? $summary->pro_links : 0,
			isset( $summary->event_links ) ? $summary->event_links : 0
		);

		echo '</table>';
	} // END display_stats

	// get pageviews for the given post ID from Automattic's stats API
	public function get_pvs( $post_id )
	{

		// test the cache like a good API user
		if( ! $hits = wp_cache_get( $post_id , 'go-post-stats-hits' ))
		{
			// attempt to get the API key from the user
			$user = wp_get_current_user();
			$api_key = isset( $user->api_key ) ? $user->api_key : NULL;

			// a locally set API key overrides everything
			$api_key = $this->wpcom_api_key ? $this->wpcom_api_key : NULL;

			// no shirt, no shoes, no service
			if( ! $api_key )
			{
				return NULL;
			}

			// the api has some very hacker-ish docs at http://stats.wordpress.com/csv.php
			$hits_api = wp_remote_request(
				'http://stats.wordpress.com/csv.php?api_key=' . $this->wpcom_api_key . '&blog_uri=' . urlencode( home_url() ) . '&table=postviews&post_id=' . $post_id . '&days=-1&limit=-1&format=json&summarize'
			);
			if( ! is_wp_error( $hits_api ))
			{
				$hits_api = wp_remote_retrieve_body( $hits_api );
				$hits_api = json_decode( $hits_api );

				if( isset( $hits_api->views ))
				{
					$hits = $hits_api->views;
				}
				else
				{
					$hits = NULL;
				}

				wp_cache_set( $post_id, $hits, 'go-post-stats-hits', 3600 );
			}
		}

		return $hits;
	} // END get_pvs

	// print a list of items to get stats on
	public function do_list( $list, $type = 'author' )
	{
		if( ! is_array( $list ))
		{
			return FALSE;
		}

		echo '<ul>';
		foreach( $list as $item )
		{
			printf( '<li><a href="%1$s&type=%2$s&key=%3$s">%4$s (%5$d)</a></li>',
				$this->menu_url,
				$type,
				$item->key,
				$item->name,
				$item->hits
			);
		}
		echo '</ul>';
	} // END do_list

	// get a list of authors from actual posts (rather than just authors on the blog)
	public function get_authors_list()
	{
		global $wpdb;

		$author_ids = $wpdb->get_results( "SELECT post_author, COUNT(1) AS hits FROM {$wpdb->posts} GROUP BY post_author" );

		if( ! is_array( $author_ids ))
		{
			return FALSE;
		}

		$return = array();
		foreach( $author_ids as $author_id )
		{
			$name = get_the_author_meta( 'display_name', $author_id->post_author );
			$return[ $author_id->post_author ] = (object) array( 
				'key' => $author_id->post_author,
				'name' => $name ? $name : 'No author name',
				'hits' => $author_id->hits,
			);
		}

		return $return;
	} // END get_authors_list

	// get a list of the most popular terms in the given taxonomy
	public function get_terms_list( $taxonomy )
	{
		if( ! taxonomy_exists( $taxonomy ))
		{
			return FALSE;
		}

		$terms = get_terms( $taxonomy, array(
			'orderby' => 'count',
			'order' => 'DESC',
			'number' => 23,
		));

		if( ! is_array( $terms ))
		{
			return FALSE;
		}

		$return = array();
		foreach( $terms as $term )
		{
			$return[ $term->term_id ] = (object) array( 
				'key' => $term->slug,
				'name' => $term->name,
				'hits' => $term->count,
			);
		}

		return $return;
	} // END get_terms_list

	public function pick_month()
	{
		?>
		<p>
			<select>
				<option>Last 30 days</option>
				<option>This currently</option>
				<option>Does nothing</option>
			</select>
		</p>
		<?php
	} // END pick_month
} // END GO_Post_Stats

function go_post_stats( $taxonomies )
{
	global $go_post_stats;

	if( ! is_object( $go_post_stats ))
	{
		$go_post_stats = new GO_Post_Stats( $taxonomies );
	}

	return $go_post_stats;
} // END go_post_stats
