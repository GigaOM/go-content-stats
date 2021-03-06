<?php

class GO_Content_Stats_Storage
{
	private $table = NULL;
	private $cache_group = 'go-content-stats';
	private $core;
	private $fields = array(
		'id' => '%d',
		'date' => '%s',
		'property' => '%s',
		'url' => '%s',
		'post_id' => '%d',
		'views' => '%d',
		'added_timestamp' => '%s',
	);

	/**
	 * The constructor
	 */
	public function __construct( $core )
	{
		global $wpdb;

		$this->core = $core;
		$this->table = ( isset( $wpdb->base_prefix ) ? $wpdb->base_prefix : $wpdb->prefix ) . 'go_content_stats';
	}// end __construct

	/**
	 * inserts a record
	 *
	 * @param array $data Row to insert into table
	 */
	public function insert( $data )
	{
		global $wpdb;

		$defaults = array(
			'id' => NULL,
			'date' => NULL,
			'property' => NULL,
			'url' => NULL,
			'post_id' => 0,
			'views' => 0,
			'added_timestamp' => current_time( 'mysql', 1 ),
		);

		$args = array_merge( $defaults, $data );

		if ( ! $args['views'] )
		{
			return FALSE;
		}//end if

		$formats = array_values( $this->fields );

		$insert_id = $wpdb->insert( $this->table, $args, $formats );

		return $insert_id;
	}// end insert

	/**
	 * get stats based on provided args
	 *
	 * @param $args array of options to get based on, see the following list for what options are available:
	 *     'id' - row ID
	 *     'date'
	 *     'property'
	 *     'url'
	 *     'post_id'
	 *     'views'
	 *     'added_timestamp'
	 *     'orderby'  - Default is 'date'. How to order the queued alerts.
	 *     'order' - Default is 'ASC'. The order to retrieve the alerts.
	 *     'count' - if TRUE, returns a count rather than a result set
	 *     'sum' - if TRUE, returns a sum of the views rather than a result set
	 *     'groupby' - Sets grouping order
	 * @return array of results
	 */
	public function get( $args )
	{
		global $wpdb;

		$fields = array_keys( $this->fields );

		if ( isset( $args['sum'] ) && $args['sum'] )
		{
			$select = 'sum(views) as sum';
		}//end if
		elseif ( isset( $args['count'] ) && $args['count'] )
		{
			$select = 'count(1) as num';
		}//end elseif
		else
		{
			$select = '*';
		}//end else

		$query = "SELECT {$select} FROM {$this->table} WHERE 1=1 ";
		$curated_args = array();

		foreach ( $this->fields as $field_name => $format )
		{
			if ( isset( $args[ $field_name ] ) )
			{
				if ( NULL === $args[ $field_name ] )
				{
					$query .= " AND `$field_name` = '' ";
				}//end if
				elseif ( is_array( $args[ $field_name ] ) )
				{
					$query .= " AND `$field_name` IN ( '" . implode( "','", $args[ $field_name ] ) . "' )";
				}
				else
				{
					$query .= " AND $field_name = $format ";
				}//end else
				$curated_args[] = $args[ $field_name ];
			}//end if
		}//end foreach

		if ( ! empty( $args['groupby'] ) )
		{
			if ( ! is_array( $args['groupby'] ) )
			{
				$groupby = explode( ',', $args['groupby'] );
			}//end if

			foreach ( $groupby as $key => $value )
			{
				$value = trim( $value );

				if ( in_array( $value, $fields ) )
				{
					continue;
				}//end if

				unset( $groupby[ $key ] );
			}//end foreach

			$query .= "GROUP BY {$groupby}";
		}//end if

		if ( isset( $args['orderby'] ) && in_array( $args['orderby'], $fields ) )
		{
			$query .= " ORDER BY {$args['orderby']} ";
		}//end if
		else
		{
			$query .= ' ORDER BY `date` ';
		}//end else

		$args['order'] = ( isset( $args['order'] ) && $args['order'] == 'DESC' ) ? 'DESC' : 'ASC';
		$query .= " {$args['order']} ";

		if ( isset( $args['limit'] ) )
		{
			list( $start, $limit ) = explode( ',', $args['limit'] );

			if ( ! $limit && $start )
			{
				$limit = $start;
				$start = 0;
			}//end if

			$start = absint( $start );
			$limit = absint( $limit );

			$query .= " LIMIT {$start}, {$limit} ";
		}//end if

		$sql = $wpdb->prepare( $query, $curated_args );
		$results = $wpdb->get_results( $sql );

		if ( isset( $args['count'] ) && $args['count'] )
		{
			$record = array_shift( $results );

			return $record->num;
		}//end if

		if ( isset( $args['sum'] ) && $args['sum'] )
		{
			$record = array_shift( $results );

			return $record->sum;
		}//end if

		return $results;
	}// end get

	/**
	 * calculate page views using unions
	 * for some reason, MySQL does not perform well with >9 IDs in the "IN()"
	 * with unions, it is super speedy quick like
	 *
	 * @param  array $post_ids post IDs
	 * @return int count of page views for the posts
	 */
	public function calc_pvs( $post_ids )
	{
		global $wpdb;

		$sql = 'SELECT SUM(v) FROM ( ';
		$sub_sql = 'SELECT SUM(views) v FROM wp_go_content_stats where post_id=';
		$sql .= $sub_sql . implode( ' union ' . $sub_sql, $post_ids ) . ') t';
		return $wpdb->get_var( $sql );
	}//end calc_pvs

	/**
	 * delete from the queue
	 *
	 * @param array fields to use as conditional for delete
	 */
	public function delete( $args )
	{
		global $wpdb;

		// make sure args only has good keys
		$args = array_intersect_key( $args, $this->fields );

		// get only the formats for the elements that are in args
		$formats = array_values( array_intersect_key( $this->fields, $args ) );

		return $wpdb->delete( $this->table,
			$args,
			$formats
		);
	}// end delete

	/**
	 */
	public function update( $data, $where )
	{
		global $wpdb;

		// make sure data only has good keys
		$data = array_intersect_key( $data, $this->fields );

		// get only the formats for the elements that are in data
		$formats = array_values( array_intersect_key( $this->fields, $data ) );

		// make sure where only has good keys
		$where = array_intersect_key( $where, $this->fields );

		// get only the formats for the elements that are in data
		$where_formats = array_values( array_intersect_key( $this->fields, $where ) );

		return $wpdb->update( $this->table,
			$data,
			$where,
			$formats,
			$where_formats
		);
	}// end update

	/**
	 * fill post_id
	 */
	public function fill_post_id( $date = NULL )
	{
		$args = array(
			'post_id' => 0,
			'limit' => '0,50',
			'orderby' => 'date',
			'order' => 'DESC',
		);

		if ( ! empty( $date ) )
		{
			$args['date'] = date( 'Y-m-d', strtotime( $date ) );
			$args['orderby'] = 'views';
		}

		$records = $this->get( $args );

		$count = 0;

		if ( ! count( $records ) )
		{
			return 0;
		}

		$remote_args = array(
			'compress' => TRUE,
		);

		foreach ( $records as $row )
		{
			$post_id = -1; // couldn't find a GUID
			$guid = '';

			if ( $temp_post_id = wp_cache_get( $row->url, $this->cache_group ) && $temp_post_id > 0 )
			{
				$post_id = $temp_post_id;
			}//end if
			else
			{
				$post_id = $this->get_post_id_from_stats( $row->url );

				if ( $post_id <= 0 )
				{
					$url = 'gigaom' == $row->property ? str_replace( 'http://', 'https://', $row->url ) : $row->url;
					$content = wp_remote_get( $url, $remote_args );

					if ( is_wp_error( $content ) )
					{
						$post_id = -2; // page failed to load
					}//end if
					else
					{
						$pattern = '/var bstat = ({.+});/';
						if ( 1 === preg_match( $pattern, $content['body'], $matches ) )
						{
							// the json payload
							$bstat_var = json_decode( $matches[1] );
							if ( ! empty( $bstat_var ) )
							{
								$guid = $bstat_var->guid;
							}//end if

							if ( $guid )
							{
								$post_id = $this->get_post_id_by_guid( $row->url, $guid );
							}//end if
						}//end if
					}//end else
				}//end if
			}//end else

			// if we found a post match for the URL, go ahead and update all the entries
			$where = $post_id > 0 ? array( 'url' => $row->url, 'post_id' => $args['post_id'] ) : array( 'id' => $row->id );
			$count += $this->update( array( 'post_id' => $post_id ), $where );
		}//end foreach

		return $count;
	}//end fill_post_id

	/**
	 * retrieves a post ID by url from stats
	 *
	 * @param string $url URL of page hit
	 */
	public function get_post_id_from_stats( $url )
	{
		global $wpdb;
		$sql = "SELECT post_id FROM {$this->table} WHERE url = %s AND post_id <> 0 LIMIT 1";
		$sql = $wpdb->prepare( $sql, $url );
		$result = $wpdb->get_var( $sql );
		$post_id = $result ?: -4; // couldn't find in stats

		if ( $post_id > 0 )
		{
			wp_cache_set( $url, $post_id, $this->cache_group );
		}//end if

		return $post_id;
	}//end get_post_id_from_stats

	/**
	 * retrieves a post ID by guid
	 *
	 * @param string $url URL of page hit
	 * @param string $guid WP Post GUID
	 */
	public function get_post_id_by_guid( $url, $guid )
	{
		global $wpdb;
		$sql = "SELECT ID FROM {$wpdb->posts} WHERE guid = %s";
		$sql = $wpdb->prepare( $sql, $guid );
		$result = $wpdb->get_var( $sql );
		$post_id = $result ?: -3; // no GUID match

		if ( $post_id > 0 )
		{
			wp_cache_set( $url, $post_id, $this->cache_group );
		}//end if

		return $post_id;
	}//end get_post_id_by_guid

	/**
	 * create table if it doesn't exist
	 */
	public function create_table()
	{
		global $wpdb;

		$charset_collate = '';
		if ( version_compare( mysql_get_server_info(), '4.1.0', '>=' ) )
		{
			if ( ! empty( $wpdb->charset ) )
			{
				$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
			}//end if

			if ( ! empty( $wpdb->collate ) )
			{
				$charset_collate .= " COLLATE {$wpdb->collate}";
			}//end if
		}//end if

		require_once ABSPATH . 'wp-admin/upgrade-functions.php';
		$sql = "
			CREATE TABLE {$this->table} (
				`id` int unsigned NOT NULL auto_increment,
				`date` DATE NOT NULL,
				`property` varchar(20) NOT NULL,
				`url` varchar(255) NOT NULL,
				`post_id` int NOT NULL DEFAULT 0,
				`views` mediumint unsigned NOT NULL DEFAULT 0,
				`added_timestamp` timestamp DEFAULT 0,
				PRIMARY KEY (id),
				KEY `date` (`date`),
				KEY `post_id` (`post_id`),
				KEY `url` (`url`)
			) ENGINE=InnoDB $charset_collate
		";

		dbDelta( $sql );
	}//end create_table

	/**
	 * access the core object
	 *
	 * @return GO_Alerts object
	 */
	private function core()
	{
		return $this->core;
	}// end core
}//end class
