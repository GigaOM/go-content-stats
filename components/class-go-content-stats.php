<?php

class GO_Content_Stats
{
	public $wpcom_api_key = FALSE; // get yours at http://apikey.wordpress.com/
	public $config;
	public $date_greater_stamp;
	public $date_greater;
	public $date_lesser_stamp;
	public $date_lesser;
	public $calendar;
	private $pieces;
	private $id_base = 'go-content-stats';

	/**
	 * constructor
	 */
	public function __construct()
	{
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu_init' ) );
	} // END __construct

	/**
	 * add the menu item to the dashboard
	 */
	public function admin_menu_init()
	{
		$this->config();
		$this->menu_url = admin_url( 'index.php?page=go-content-stats' );
		add_submenu_page( 'index.php', 'Gigaom Content Stats', 'Content Stats', 'edit_posts', 'go-content-stats', array( $this, 'admin_menu' ) );
	} // END admin_menu_init

	public function admin_init()
	{
		$this->config();
		add_action( 'go-content-stats-posts', array( $this, 'prime_pv_cache' ) );
		add_action( 'wp_ajax_go_content_stats_fetch', array( $this, 'fetch_ajax' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}// end admin_init

	private function config()
	{
		if ( $this->config )
		{
			return $this->config;
		}//end if

		$this->config = apply_filters( 'go_config', array(), 'go-content-stats' );
		// prep the config vars so we don't have to check them later
		if ( ! isset( $this->config['taxonomies'] ) )
		{
			$this->config['taxonomies'] = array();
		}

		if ( ! isset( $this->config['content_matches'] ) )
		{
			$this->config['content_matches'] = array();
		}

		// prefix the matches so we can avoid collisions
		foreach ( $this->config['content_matches'] as $k => $v )
		{
			$this->config['content_matches'][ 'match_' . $k ] = $v;
			unset( $this->config['content_matches'][ $k ] );
		}

		return $this->config;
	}// end config

	private function pieces()
	{
		if ( $this->pieces )
		{
			return clone $this->pieces;
		}// end if

		$this->pieces = (object) array_merge(

			array(
				'day' => NULL,
				'posts' => NULL,
				'pvs' => NULL,
				'comments' => NULL,
			),

			array_fill_keys( array_keys( $this->config['content_matches'] ), NULL )
		);

		return clone $this->pieces;
	}// end pieces

	/**
	 * the stats page/admin menu
	 */
	public function admin_menu()
	{
		require __DIR__ . '/templates/stats.php';
	} // END admin_menu

	public function admin_enqueue_scripts()
	{
		$script_config = apply_filters( 'go-config', array( 'version' => 1 ), 'go-script-version' );

		wp_enqueue_style( 'go-content-stats', plugins_url( 'css/go-content-stats.css', __FILE__ ), array(), $script_config['version'] );

		wp_register_script( 'go-content-stats', plugins_url( 'js/go-content-stats.js', __FILE__ ), array( 'jquery-mustache' ), $script_config['version'], TRUE );
		wp_enqueue_script( 'go-content-stats' );
	}//end admin_enqueue_scripts

	/**
	 * a filter for the posts sql to limit by date range
	 *
	 * @param  string $where SQL where condition to filter
	 * @return string filtered SQL where condition
	 */
	public function posts_where( $where = '' )
	{
		$where .= " AND post_date BETWEEN '{$this->date_lesser}' AND '{$this->date_greater}'";
		return $where;
	} // END posts_where

	/**
	 * get a list of all posts matching the time selector
	 */
	public function get_general_stats()
	{
		add_filter( 'posts_where', array( $this, 'posts_where' ) );
		$query = new WP_Query( array(
			'posts_per_page' => -1,
		) );
		remove_filter( 'posts_where', array( $this, 'posts_where' ) );

		if ( ! isset( $query->posts ) )
		{
			return FALSE;
		}

		return $query->posts;
	} // END get_general_stats

	/**
	 * get a list of posts by author to display
	 *
	 * @param  int $author ID for the author
	 * @return array posts
	 */
	public function get_author_stats( $author )
	{
		add_filter( 'posts_where', array( $this, 'posts_where' ) );
		$query = new WP_Query( array(
			'author' => (int) $author,
			'posts_per_page' => -1,
		) );
		remove_filter( 'posts_where', array( $this, 'posts_where' ) );

		if ( ! isset( $query->posts ) )
		{
			return FALSE;
		}

		return $query->posts;
	} // END get_author_stats

	/**
	 * get a list of posts by taxonomy to display
	 *
	 * @param string $taxonomy Taxonomy
	 * @param mixed $terms (int/string/array) Taxonomy terms
	 */
	public function get_taxonomy_stats( $taxonomy, $terms )
	{
		add_filter( 'posts_where', array( $this, 'posts_where' ) );
		$query = new WP_Query( array(
			'taxonomy' => $taxonomy,
			'terms' => $terms,
			'posts_per_page' => -1,
		) );
		remove_filter( 'posts_where', array( $this, 'posts_where' ) );

		if ( ! isset( $query->posts ) )
		{
			return FALSE;
		}

		return $query->posts;
	} // END get_taxonomy_stats

	/**
	 * actually display the stats for the selected posts
	 *
	 * @param  array $posts array of post objects
	 * @return null outputs HTML
	 */
	public function display_stats( $posts )
	{
		if ( ! is_array( $posts ) )
		{
			return FALSE;
		}

		do_action( 'go-content-stats-posts', wp_list_pluck( $posts, 'ID' ) );

		// iterate through the posts, aggregate their stats, and assign those into the calendar
		foreach ( $posts as $post )
		{
			$post_date = date( 'Y-m-d', strtotime( $post->post_date ) );
			$this->calendar[ $post_date ]->day = $post_date;
			$this->calendar[ $post_date ]->posts++;
			$this->calendar[ $post_date ]->pvs += $this->get_pvs( $post->ID );
			$this->calendar[ $post_date ]->comments += $post->comment_count;
			foreach ( $this->config['content_matches'] as $key => $match )
			{
				if ( preg_match( $match['regex'], $post->post_content ) )
				{
					$this->calendar[ $post_date ]->$key++;
				}
			}// end foreach
		}// end foreach

		// create a sub-list of content match table headers
		$content_match_th = '';
		if ( is_array( $this->config['content_matches'] ) )
		{
			foreach ( $this->config['content_matches'] as $match )
			{
				$content_match_th .= '<th>' . $match['label'] . '</th>';
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
				' . $content_match_th .'
			</tr>
		';

		// iterate through and generate the summary stats (yes, this means I'm iterating extra)
		$summary = clone $this->pieces;
		foreach ( $this->calendar as $day )
		{
			$summary->day++;
			$summary->posts += $day->posts;
			$summary->pvs += $day->pvs;
			$summary->comments += $day->comments;
			foreach ( $this->config['content_matches'] as $key => $match )
			{
				$summary->$key += $day->$key;
			}
		}// end foreach

		// iterate the content matches for the summary
		$content_match_summary_values = '';
		foreach ( $this->config['content_matches'] as $key => $match )
		{
			$content_match_summary_values .= '<td>' . ( $summary->$key ? $summary->$key : 0 ) . '</td>';
		}// end foreach

		// print the summary row for all these stats
		printf( '
			<tr class="summary">
				<td>%1$s</td>
				<td>%2$s</td>
				<td>%3$s</td>
				<td>%4$s</td>
				<td>%5$s</td>
				<td>%6$s</td>
				%7$s
			</tr>',

			$summary->day .' days',
			$summary->posts ? $summary->posts : 0,
			$summary->pvs ? number_format( $summary->pvs ) : 0,
			$summary->posts ? number_format( ( $summary->pvs / $summary->posts ), 1 ) : 0,
			$summary->comments ? number_format( $summary->comments ) : 0,
			$summary->posts ? number_format( ( $summary->comments / $summary->posts ), 1 ) : 0,
			$content_match_summary_values
		);

		// iterate through the calendar (includes empty days), print stats for each day
		foreach ( $this->calendar as $day )
		{
			// iterate the content matches for each row
			$content_match_row_values = '';
			foreach ( $this->config['content_matches'] as $key => $match )
			{
				$content_match_row_values .= '<td>' . ( $day->$key ? $day->$key : '&nbsp;' ) . '</td>';
			}

			printf( '
				<tr>
					<td>%1$s</td>
					<td>%2$s</td>
					<td>%3$s</td>
					<td>%4$s</td>
					<td>%5$s</td>
					<td>%6$s</td>
					%7$s
				</tr>',

				$day->day,
				$day->posts ? '<a href="' . admin_url( '/edit.php?m=' . $day->day ) . '">' . $day->posts . '</a>' : '&nbsp;',
				$day->pvs ? number_format( $day->pvs ): '&nbsp;',
				$day->posts ? number_format( ( $day->pvs / $day->posts ), 1 ) : '&nbsp;',
				$day->comments ? number_format( $day->comments ) : '&nbsp;',
				$day->posts ? number_format( ( $day->comments / $day->posts ), 1 ) : '&nbsp;',
				$content_match_row_values
			);
		}// end foreach

		// print the summary row for all these stats
		printf( '
			<tr class="summary-footer">
				<td>%1$s</td>
				<td>%2$s</td>
				<td>%3$s</td>
				<td>%4$s</td>
				<td>%5$s</td>
				<td>%6$s</td>
				%7$s
			</tr>',

			$summary->day .' days',
			$summary->posts ? $summary->posts : 0,
			$summary->pvs ? number_format( $summary->pvs ) : 0,
			$summary->posts ? number_format( ( $summary->pvs / $summary->posts ), 1 ) : 0,
			$summary->comments ? number_format( $summary->comments ) : 0,
			$summary->posts ? number_format( ( $summary->comments / $summary->posts ), 1 ) : 0,
			$content_match_summary_values
		);

		echo '</table>';
	} // END display_stats

	public function get_wpcom_api_key()
	{
		$api_key = FALSE;

		// a locally set API key overrides everything
		if ( ! empty( $this->wpcom_api_key ) )
		{
			$api_key = $this->wpcom_api_key;
		}// end if
		// attempt to get the API key from the user
		elseif (
			( $user = wp_get_current_user() ) &&
			isset( $user->api_key )
		)
		{
			$api_key = $user->api_key;
		}// end elseif
		elseif ( isset( $this->config['pv_api_key'] ) )
		{
			$api_key = $this->config['pv_api_key'];
		}// end elseif

		return $api_key;
	}//end get_wpcom_api_key

	/**
	 * get pageviews for the given post ID from Automattic's stats API
	 *
	 * @param  int $post_id Post ID
	 */
	public function get_pvs( $post_id )
	{
		// test the cache like a good API user
		// if the prime_pv_cache() cache method earlier is working, this should always return a cached result
		if ( ! $hits = wp_cache_get( $post_id, 'go-content-stats-hits' ) )
		{
			// attempt to get the API key
			if ( ! $api_key = $this->get_wpcom_api_key() )
			{
				return NULL;
			}

			// the api has some very hacker-ish docs at http://stats.wordpress.com/csv.php
			$get_url = sprintf(
				 'http://stats.wordpress.com/csv.php?api_key=%1$s&blog_uri=%2$s&table=postviews&post_id=%3$d&days=-1&limit=-1&format=json&summarize',
				 $api_key,
				 urlencode( $this->config['pv_api_url'] ),
				 $post_id
			);

			$hits_api = wp_remote_request( $get_url );
			if ( ! is_wp_error( $hits_api ) )
			{
				$hits_api = wp_remote_retrieve_body( $hits_api );
				$hits_api = json_decode( $hits_api );

				if ( isset( $hits_api->views ) )
				{
					$hits = $hits_api->views;
				}
				else
				{
					$hits = NULL;
				}

				wp_cache_set( $post_id, $hits, 'go-content-stats-hits', 1800 );
			}// end if
		}// end if

		return $hits;
	} // END get_pvs

	/**
	 * prime the pageview stats cache by doing a bulk query of all posts, rather than individual queries
	 *
	 * @param  array $post_ids Post IDs
	 * @return null
	 */
	public function prime_pv_cache( $post_ids )
	{
		$to_fetch = array();
		foreach ( $post_ids as $post_id )
		{
			$to_fetch[] = $post_id;
			if ( 100 == count( $to_fetch ) )
			{
				$this->prime_pv_cache_chunk( $to_fetch );
				$to_fetch = array();
			}//end if
		}// end foreach

		$this->prime_pv_cache_chunk( $to_fetch );
	}//end prime_pv_cache

	private function prime_pv_cache_chunk( $post_ids )
	{
		// caching this, but the result doesn't really matter so much as the fact that
		// we've already run it on a specific set of posts recently
		$cachekey = md5( serialize( $post_ids ) );

		// test the cache like a good API user
		if ( ! $hits = wp_cache_get( $cachekey, 'go-content-stats-hits-bulk' ) )
		{
			// attempt to get the API key
			if ( ! $api_key = $this->get_wpcom_api_key() )
			{
				return NULL;
			}

			// the api has some very hacker-ish docs at http://stats.wordpress.com/csv.php
			$get_url = sprintf(
				 'http://stats.wordpress.com/csv.php?api_key=%1$s&blog_uri=%2$s&table=postviews&post_id=%3$s&days=-1&limit=-1&format=json&summarize',
				 $api_key,
				 urlencode( $this->config['pv_api_url'] ),
				 implode( ',', array_map( 'absint', $post_ids ) )
			);

			$hits_api = wp_remote_request( $get_url );

			if ( ! is_wp_error( $hits_api ) )
			{
				$hits_api = wp_remote_retrieve_body( $hits_api );
				$hits_api = json_decode( $hits_api );

				if ( ! isset( $hits_api[0]->postviews ) )
				{
					return;
				}

				foreach ( $hits_api[0]->postviews as $hits_api_post )
				{
					if ( ! isset( $hits_api_post->post_id, $hits_api_post->views ) )
					{
						continue;
					}

					// the real gold here is setting the cache entry for the get_pv method to use later
					wp_cache_set( $hits_api_post->post_id, $hits_api_post->views, 'go-content-stats-hits', 1800 );
				}

				wp_cache_set( $cachekey, $hits_api[0]->postviews, 'go-content-stats-hits-bulk', 1800 );
			}// end if
		}// end if
	} // END prime_pv_cache_chunk

	/**
	 * print a list of items to get stats on
	 *
	 * @param  array $list items to list
	 * @param  string $type the item type
	 * @return null outputs unordered list
	 *
	 * @todo : this is ready to be deleted, kept for reference on mustache growing
	 */
	public function do_list( $list, $type = 'author' )
	{
		if ( ! is_array( $list ) )
		{
			return FALSE;
		}

		echo '<ul>';
		foreach ( $list as $item )
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

	/**
	 * get a list of authors from actual posts (rather than just authors on the blog)
	 * cached for a full day
	 */
	public function get_authors_list()
	{
		if ( ! $return = wp_cache_get( 'authors', 'go-content-stats' ) )
		{
			global $wpdb;

			$author_ids = $wpdb->get_results( "SELECT post_author, COUNT(1) AS hits FROM {$wpdb->posts} GROUP BY post_author" );

			if ( ! is_array( $author_ids ) )
			{
				return FALSE;
			}

			$return = array();
			foreach ( $author_ids as $author_id )
			{
				$name = get_the_author_meta( 'display_name', $author_id->post_author );
				$return[ $author_id->post_author ] = array(
					'key' => $author_id->post_author,
					'name' => $name ? $name : 'No author name',
					'hits' => $author_id->hits,
				);
			}

			wp_cache_set( 'authors', $return, 'go-content-stats', 86413 ); // 86413 is a prime number slightly longer than 24 hours
		}// end if

		return $return;
	} // END get_authors_list

	/**
	 * get a list of the most popular terms in the given taxonomy
	 *
	 * @param  string $taxonomy Taxonomy
	 * @return array objects with 'key', 'name', and 'count'
	 */
	public function get_terms_list( $taxonomy )
	{
		if ( ! taxonomy_exists( $taxonomy ) )
		{
			return FALSE;
		}

		$terms = get_terms( $taxonomy, array(
			'orderby' => 'count',
			'order' => 'DESC',
			'number' => 23,
		) );

		if ( ! is_array( $terms ) )
		{
			return FALSE;
		}

		$return = array();
		foreach ( $terms as $term )
		{
			$return[ $term->slug ] = array(
				'id' => $term->term_id,
				'key' => $term->slug,
				'name' => $term->name,
				'hits' => $term->count,
			);
		}// end foreach

		return $return;
	} // END get_terms_list

	public function fetch_ajax()
	{
		if ( ! current_user_can( 'edit_posts' ) )
		{
			wp_send_json_error( 'you do not have permission' );
		}// end if

		$which = isset( $_GET['which'] ) ? $_GET['which'] : 'stats';
		$valid_which = array(
			'stats',
			'pv_stats',
			'taxonomies',
		);

		if ( ! in_array( $which, $valid_which ) )
		{
			wp_send_json_error( 'nice try. beat it.' );
		}// end if

		// set the upper limit of posts
		if ( isset( $_GET['date_greater'] ) && strtotime( urldecode( $_GET['date_greater'] ) ) )
		{
			$this->date_greater_stamp = strtotime( urldecode( $_GET['date_greater'] ) );
			$this->date_greater = date( 'Y-m-d', $this->date_greater_stamp );
		}// end if
		else
		{
			$this->date_greater_stamp = time();
			$this->date_greater = date( 'Y-m-d', $this->date_greater_stamp );
		}// end else

		// set the lower limit of posts
		if ( isset( $_GET['date_lesser'] ) && strtotime( urldecode( $_GET['date_lesser'] ) ) )
		{
			$this->date_lesser_stamp = strtotime( urldecode( $_GET['date_lesser'] ) );
			$this->date_lesser = date( 'Y-m-d', $this->date_lesser_stamp );
		}// end if
		else
		{
			$this->date_lesser_stamp = strtotime( '-31 days' );
			$this->date_lesser = date( 'Y-m-d', $this->date_lesser_stamp );
		}// end else

		$args = array(
			'type' => isset( $_GET['type'] ) ? $_GET['type'] : 'general',
			'key' => isset( $_GET['key'] ) ? $_GET['key'] : NULL,
		);

		$function = "fetch_$which";
		$stats = $this->$function( $args );

		if ( ! $stats )
		{
			wp_send_json_error( 'failed to load stats.' );
		}// end if

		wp_send_json_success( $stats );
	}// end fetch_ajax

	private function fetch_stats( $args )
	{
		$posts = $this->fetch_stat_posts( $args );
		if ( ! is_array( $posts ) )
		{
			return FALSE;
		}// end if

		$stats = $this->initialize_stats();

		// iterate through the posts, aggregate their stats, and assign those into the stat array
		foreach ( $posts as $post )
		{
			$post_date = date( 'Y-m-d', strtotime( $post->post_date ) );
			$stats[ $post_date ]->day = $post_date;
			$stats[ $post_date ]->posts++;

			$stats[ $post_date ]->comments += $post->comment_count;
			foreach ( $this->config['content_matches'] as $key => $match )
			{
				if ( preg_match( $match['regex'], $post->post_content ) )
				{
					$stats[ $post_date ]->$key++;
				}// end if
			}// end foreach
		}// end foreach

		// iterate through and generate the summary stats (yes, this means I'm iterating extra)
		$summary = $this->pieces();
		foreach ( $stats as $day )
		{
			$summary->day++;
			$summary->posts += $day->posts;
			$summary->comments += $day->comments;
			foreach ( $this->config['content_matches'] as $key => $match )
			{
				$summary->$key += $day->$key;
			}// end foreach
		}// end foreach

		return array(
			'stats' => $stats,
			'summary' => $summary,
		);
	}// end fetch_stats

	private function fetch_pv_stats( $args )
	{
		$posts = $this->fetch_stat_posts( $args );
		if ( ! is_array( $posts ) )
		{
			return FALSE;
		}// end if

		$this->prime_pv_cache( wp_list_pluck( $posts, 'ID' ) );

		$stats = $this->initialize_stats();
		foreach ( $posts as $post )
		{
			$post_date = date( 'Y-m-d', strtotime( $post->post_date ) );

			$stats[ $post_date ]->pvs += $this->get_pvs( $post->ID );
		}// end foreach

		// summary!
		$summary = $this->pieces();
		foreach ( $stats as $day )
		{
			$summary->pvs += $day->pvs;
		}//end foreach

		return array(
			'stats' => $stats,
			'summary' => $summary,
		);
	}//end fetch_pv_stats

	private function initialize_stats()
	{
		$stats = array();
		$temp_time = $this->date_lesser_stamp;
		do
		{
			$temp_date = date( 'Y-m-d', $temp_time );
			$stats[ $temp_date ] = clone $this->pieces;
			$stats[ $temp_date ]->day = $temp_date;
			$temp_time += 86400;
		}// end do
		while ( $temp_time < $this->date_greater_stamp );

		return array_reverse( $stats );
	}// end initialize_stats

	private function fetch_stat_posts( $args )
	{
		// run the stats
		if ( 'author' == $args['type'] && ( $author = get_user_by( 'id', $args['key'] ) ) )
		{
			$posts = $this->get_author_stats( $args['key'] );
		}// end if
		elseif ( taxonomy_exists( $args['type'] ) && term_exists( $args['key'], $args['type'] ) )
		{
			$posts = $this->get_taxonomy_stats( $args['type'], $args['key'] );
		}// end elseif
		else
		{
			$posts = $this->get_general_stats();
		}// end else

		return $posts;
	}// end fetch_stat_posts

	private function fetch_taxonomies( $unused_args )
	{
		// print lists of items people can get stats on
		// authors here
		$authors = $this->get_authors_list();

		// all configured taxonomies here
		foreach ( $this->config['taxonomies'] as $tax )
		{
			$terms = $this->get_terms_list( $tax );
			$taxonomies[ $tax ] = $terms ?: array();
		}// end foreach

		return array(
			'authors' => $authors,
			'taxonomies' => $taxonomies,
		);
	}//end fetch_taxonomies

	/**
	 * utility function to consistently get field names
	 *
	 * @param string name of the field
	 * @return string formatted like idbase[field_name]
	 */
	private function get_field_name( $field_name )
	{
		return "{$this->id_base}[{$field_name}]";
	}//end get_field_name

	/**
	 * utility function to consistently get field
	 *
	 * @param string name of the field
	 * @return string formatted like idbase-field_name
	 */
	private function get_field_id( $field_name )
	{
		return "{$this->id_base}-{$field_name}";
	}//end get_field_id
}// END GO_Content_Stats

function go_content_stats()
{
	global $go_content_stats;

	if ( ! is_object( $go_content_stats ) )
	{
		$go_content_stats = new GO_Content_Stats();
	}

	return $go_content_stats;
} // END go_content_stats
