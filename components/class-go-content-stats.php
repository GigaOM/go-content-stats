<?php

class GO_Content_Stats
{
	public $config;
	public $date_greater_stamp;
	public $date_greater;
	public $date_lesser_stamp;
	public $date_lesser;
	public $calendar;
	private $days = array();
	private $dependencies = array(
		'go-google' => 'https://github.com/GigaOM/go-google',
		'go-graphing' => 'https://github.com/GigaOM/go-graphing',
		'go-timepicker' => 'https://github.com/GigaOM/go-timepicker',
		'go-ui' => 'https://github.com/GigaOM/go-ui',
	);
	private $missing_dependencies = array();
	private $pieces;
	private $id_base = 'go-content-stats';
	private $storage;
	private $load;

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
		$this->check_dependencies();

		if ( $this->missing_dependencies )
		{
			return;
		}//end if

		$this->config();
		$this->menu_url = admin_url( 'index.php?page=go-content-stats' );
		add_submenu_page( 'index.php', 'Gigaom Content Stats', 'Content Stats', 'edit_posts', 'go-content-stats', array( $this, 'admin_menu' ) );
	} // END admin_menu_init

	/**
	 * hooked to the admin_init action
	 */
	public function admin_init()
	{
		if ( $this->missing_dependencies )
		{
			return;
		}//end if

		$this->config();
		$this->storage();
		add_action( 'go-content-stats-posts', array( $this, 'prime_pv_cache' ) );
		add_action( 'wp_ajax_go_content_stats_fetch', array( $this, 'fetch_ajax' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}// end admin_init

	/**
	 * check plugin dependencies
	 */
	public function check_dependencies()
	{
		foreach ( $this->dependencies as $dependency => $url )
		{
			if ( function_exists( str_replace( '-', '_', $dependency ) ) )
			{
				continue;
			}//end if

			$this->missing_dependencies[ $dependency ] = $url;
		}//end foreach

		if ( $this->missing_dependencies )
		{
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}//end if
	}//end check_dependencies

	/**
	 * hooked to the admin_notices action to inject a message if depenencies are not activated
	 */
	public function admin_notices()
	{
		?>
		<div class="error">
			<p>
				You must <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">activate</a> the following plugins before using <code>go-content-stats</code> plugin:
			</p>
			<ul>
				<?php
				foreach ( $this->missing_dependencies as $dependency => $url )
				{
					?>
					<li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $dependency ); ?></a></li>
					<?php
				}//end foreach
				?>
			</ul>
		</div>
		<?php
	}//end admin_notices

	/**
	 * called on register_activation_hook
	 */
	public function activate()
	{
		$this->storage()->create_table();
	}// end activate

	/**
	 * stats table object accessor
	 */
	public function storage()
	{
		if ( ! $this->storage )
		{
			require_once __DIR__ . '/class-go-content-stats-storage.php';

			$this->storage = new GO_Content_Stats_Storage( $this );
		}//end if

		return $this->storage;
	}//end storage

	/**
	 * stats load object accessor
	 */
	public function load()
	{
		if ( ! $this->load )
		{
			require_once __DIR__ . '/class-go-content-stats-load.php';

			$this->load = new GO_Content_Stats_Load();
		}//end if

		return $this->load;
	}//end load

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
		// If we aren't on the actual content stats page we don't need any of this
		if ( 'dashboard_page_go-content-stats' != get_current_screen()->base )
		{
			return;
		} // END if

		$script_config = apply_filters( 'go-config', array( 'version' => 1 ), 'go-script-version' );

		// make sure our go-graphing styles and js are registered
		go_timepicker()->register_resources();

		// make sure our go-graphing styles and js are registered
		go_graphing();

		// make sure our go-ui styles and js are registered
		go_ui();

		wp_enqueue_style(
			'go-content-stats',
			plugins_url( 'css/go-content-stats.css', __FILE__ ),
			array(
				'bootstrap-daterangepicker',
				'fontawesome',
				'rickshaw',
			),
			$script_config['version']
		);

		$data = array(
			'endpoint' => admin_url( 'admin-ajax.php?action=go_content_stats_fetch' ),
		);

		wp_register_script(
			'go-content-stats',
			plugins_url( 'js/go-content-stats.js', __FILE__ ),
			array(
				'bootstrap-daterangepicker',
				'rickshaw',
				'handlebars',
				'jquery-blockui',
			),
			$script_config['version'],
			TRUE
		);

		wp_register_script(
			'go-content-stats-graph',
			plugins_url( 'js/go-content-stats-graph.js', __FILE__ ),
			array(
				'go-content-stats',
			),
			$script_config['version'],
			TRUE
		);

		wp_register_script(
			'go-content-stats-behavior',
			plugins_url( 'js/go-content-stats-behavior.js', __FILE__ ),
			array(
				'go-content-stats-graph',
			),
			$script_config['version'],
			TRUE
		);

		wp_enqueue_script( 'go-content-stats-behavior' );

		wp_localize_script( 'go-content-stats', 'go_content_stats', $data );
	}//end admin_enqueue_scripts

	/**
	 * a filter for the posts sql to limit by date range
	 *
	 * @param  string $where SQL where condition to filter
	 * @return string filtered SQL where condition
	 */
	public function posts_where( $where = '' )
	{
		if ( $this->days )
		{
			foreach ( $this->days as &$day )
			{
				$day = preg_replace( '/[^0-9\-]/', '', $day );
			}//end foreach

			$where .= " AND DATE_FORMAT( post_date, '%Y-%m-%d' ) IN ( '" . implode( "', '", $this->days ) . "' )";

			return $where;
		}//end if

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
			'term' => $terms,
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
	 * get pageviews for the given post IDs from our stat storage table
	 *
	 * @param mixed $post_ids Post ID or array of Post IDs
	 */
	public function get_pvs( $post_ids )
	{
		return $this->storage()->calc_pvs( $post_ids );
	} // END get_pvs

	/**
	 * get pageviews for a given post ID by day
	 *
	 * @param int $post_id Post ID
	 */
	public function get_pvs_by_day( $post_id )
	{
		$stats = array();

		$args = array(
			'post_id' => $post_id,
		);
		$views = $this->storage()->get( $args );

		if ( ! $views )
		{
			return $stats;
		}//end if

		$date_start = $views[0]->date;
		$date_end = $views[ count( $views ) - 1 ]->date;

		$day = strtotime( $date_start );
		$end = strtotime( $date_end );
		while ( $day <= $end )
		{
			$stats[ $day ] = 0;
			$day = strtotime( '+1 day', $day );
		}//end while

		foreach ( $views as $view )
		{
			$stats[ strtotime( $view->date ) ] += $view->views;
		}//end foreach

		return $stats;
	} // END get_pvs_by_day

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
				'taxonomy' => $taxonomy,
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

		$which = isset( $_GET['which'] ) ? $_GET['which'] : 'general';
		$valid_which = array(
			'general',
			'pvs',
			'taxonomies',
			'posts',
		);

		if ( ! in_array( $which, $valid_which ) )
		{
			wp_send_json_error( 'Nice try. Beat it.' . print_r( $_GET, true ) );
		}// end if

		$this->days = isset( $_GET['days'] ) ? $_GET['days'] : array();

		if ( 'taxonomies' != $which && ( ! is_array( $this->days ) || empty( $this->days ) ) )
		{
			wp_send_json_error( 'Nice try. Days are invalid.' . print_r( $_GET, true ) );
		}//end if

		// set the upper limit of posts
		if ( isset( $_GET['date_start'] ) && strtotime( urldecode( $_GET['date_start'] ) ) )
		{
			$this->date_greater_stamp = strtotime( urldecode( $_GET['date_start'] ) );
			$this->date_greater = date( 'Y-m-d', $this->date_greater_stamp );
		}// end if
		else
		{
			$this->date_greater_stamp = time();
			$this->date_greater = date( 'Y-m-d', $this->date_greater_stamp );
		}// end else

		// set the lower limit of posts
		if ( isset( $_GET['date_end'] ) && strtotime( urldecode( $_GET['date_end'] ) ) )
		{
			$this->date_lesser_stamp = strtotime( urldecode( $_GET['date_end'] ) );
			$this->date_lesser = date( 'Y-m-d', $this->date_lesser_stamp );
		}// end if
		else
		{
			$this->date_lesser_stamp = strtotime( '-31 days' );
			$this->date_lesser = date( 'Y-m-d', $this->date_lesser_stamp );
		}// end else

		$type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : 'general';
		$key = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : NULL;
		$args = array(
			'type' => $type,
			'key' => $key,
		);

		$function = "fetch_$which";
		$stats = $this->$function( $args );

		if ( ! $stats )
		{
			wp_send_json_error( 'failed to load stats.' );
		}// end if

		$stats['period'] = array(
			'start' => empty( $this->days[ 0 ] ) ? $this->date_greater : $this->days[ 0 ],
			'end' => empty( $this->days[ count( $this->days ) - 1 ] ) ? $this->date_lesser : $this->days[ count( $this->days ) - 1 ],
		);

		$stats['which'] = $which;
		$stats['type'] = $type;
		$stats['key'] = $key;

		wp_send_json_success( $stats );
	}// end fetch_ajax

	private function fetch_general( $args )
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

		return array(
			'stats' => $stats,
		);
	}// end fetch_general

	private function fetch_pvs( $args )
	{
		$posts = $this->fetch_stat_posts( $args );
		if ( ! is_array( $posts ) )
		{
			return FALSE;
		}// end if

		$post_ids = array();
		foreach ( $posts as $post )
		{
			$post_date = date( 'Y-m-d', strtotime( $post->post_date ) );

			if ( empty( $post_ids[ $post_date ] ) )
			{
				$post_ids[ $post_date ] = array();
			}// end if

			$post_ids[ $post_date ][] = $post->ID;
		}// end foreach

		$stats = $this->initialize_stats( FALSE );
		foreach ( $post_ids as $post_date => $posts_by_day )
		{
			$stats[ $post_date ]->pvs = (int) $this->get_pvs( $posts_by_day );
		}// end foreach

		return array(
			'stats' => $stats,
		);
	}//end fetch_pvs

	private function fetch_taxonomies( $unused_args )
	{
		// print lists of items people can get stats on
		// authors here
		$authors = $this->get_authors_list();

		$taxonomies = array();
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

	private function fetch_posts( $args )
	{
		$posts = $this->fetch_stat_posts( $args );

		if ( ! is_array( $posts ) )
		{
			return FALSE;
		}// end if

		$post_data = array();
		foreach ( $posts as $post )
		{
			$data = new stdclass;

			$data->id = $post->ID;
			$data->title = get_the_title( $post->ID );
			$data->permalink = get_permalink( $post->ID );
			$data->day = date( 'Y-m-d', strtotime( $post->post_date ) );
			$data->pvs = $this->get_pvs( array( $post->ID ) );

			$data->pvs_by_day = $this->get_pvs_by_day( $post->ID );
			$data->pvs_percentage_plus_one = NULL;
			if ( $data->pvs && $data->pvs_by_day )
			{
				$data->pvs_percentage_plus_one = ( 1 - ( current( $data->pvs_by_day ) / $data->pvs ) ) * 100;

				if ( count( $data->pvs_by_day ) >= 7 )
				{
					$views_7 = 0;
					$views_80_percent = 0;
					$i = 0;
					foreach ( $data->pvs_by_day as $day_views )
					{
						if ( $i < 7 )
						{
							$views_7 += $day_views;
						}//end if

						$views_80_percent += $day_views;

						$i++;

						$pvs_percentage_80 = ( $views_80_percent / $data->pvs ) * 100;
						if ( $pvs_percentage_80 >= 80 )
						{
							$data->pvs_days_to_80_percent = $i;
							break;
						}//end if
					}//end foreach
					$data->pvs_percentage_plus_seven = ( 1 - ( $views_7 / $data->pvs ) ) * 100;
				}//end if
			}//end if

			$data->pvs_by_day = go_graphing()->array_to_series( $data->pvs_by_day );

			$data->comments = $post->comment_count;

			foreach ( $this->config['content_matches'] as $key => $match )
			{
				if ( preg_match( $match['regex'], $post->post_content ) )
				{
					$data->$key = 'yes';
				}// end if
			}// end foreach

			$post_data[] = clone $data;
		}// end foreach

		usort( $post_data, array( $this, 'fetch_posts_sort' ) );

		return array(
			'posts' => $post_data,
		);
	}//end fetch_posts

	public function fetch_posts_sort( $a, $b )
	{
		if ( $a->pvs == $b->pvs )
		{
			return 0;
		}// end if

		return ( $a->pvs > $b->pvs ) ? -1 : 1;
	}

	private function initialize_stats( $pieces = TRUE )
	{
		$stats = array();
		foreach ( $this->days as $date )
		{
			if ( $pieces )
			{
				$stats[ $date ] = $this->pieces();
			}// end if
			else
			{
				$stats[ $date ] = new stdClass;
				$stats[ $date ]->pvs = 0;
			}// end else

			$stats[ $date ]->day = $date;
		}// end foreach

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
