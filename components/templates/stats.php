<?php
// create a sub-list of content match table headers
$content_match_th = '';
$content_summary = '';
$content_row = '';
$posts_content_row = '';
$columns = 6;
if ( is_array( $this->config['content_matches'] ) )
{
	foreach ( $this->config['content_matches'] as $key => $match )
	{
		$content_match_th .= '<th class="' . esc_attr( $key ) . '">' . esc_attr( $match['label'] ) . '</th>';
		$content_summary .= '<th class="' . esc_attr( $key ) . '"></th>';
		$content_row .= '<td class="matches ' . esc_attr( $key ) . '">{{number_format ' . esc_html( $key ) . '}}</td>';
		$posts_content_row .= '<td class="matches ' . esc_attr( $key ) . '">{{' . esc_html( $key ) . '}}</td>';
		$columns++;
	}// end foreach
}// end if

$type = isset( $_GET['type'] ) ? $_GET['type'] : 'general';
$key = isset( $_GET['key'] ) ? $_GET['key']: '';

$start = isset( $_GET['start'] ) ? preg_replace( '/[^0-9\-]/', '', $_GET['start'] ) : '';
$end = isset( $_GET['end'] ) ? preg_replace( '/[^0-9\-]/', '', $_GET['end'] ) : '';
?>

<div class="wrap" id="go-content-stats">
	<input type="hidden" id="<?php echo /* @INSANE */ esc_attr( $this->get_field_id( 'type' ) ); ?>" name="<?php echo /* @INSANE */ esc_attr( $this->get_field_id( 'type' ) ); ?>" value="<?php echo esc_attr( $type ); ?>"/>
	<input type="hidden" id="<?php echo /* @INSANE */ esc_attr( $this->get_field_id( 'key' ) ); ?>" name="<?php echo /* @INSANE */ esc_attr( $this->get_field_id( 'key' ) ); ?>" value="<?php echo esc_attr( $key ); ?>"/>

	<a href="#" id="<?php echo /* @INSANE */ esc_attr( $this->get_field_id( 'clear-cache' ) ); ?>">Clear local cache</a>
	<h2>Gigaom Content Stats</h2>
	<section id="content-stats">
		<?php
		do_action( 'go_timepicker_date_range_picker', array(
			'start' => $start,
			'start_field_id' => $this->get_field_id( 'start' ),
			'start_field_name' => $this->get_field_name( 'start' ),
			'end' => $end,
			'end_field_id' => $this->get_field_id( 'end' ),
			'end_field_name' => $this->get_field_name( 'end' ),
		) );
		?>

		<header>Post performance</header>
		<?php ob_start(); ?>
		<li data-type="{{type}}" data-key="{{key}}">
			<span class="type">{{type_pretty}}</span>
			<span class="value">{{name}}</span>
			<span class="remove">
				<i class="fa fa-times"></i>
			</span>
		</li>
		<?php
		$filter_template = ob_get_clean();

		$item = '';

		if ( ! empty( $_GET['type'] ) )
		{
			$type = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $_GET['type'] );
			$key = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $_GET['key'] );

			if ( 'author' == $type )
			{
				$filter_object = get_user_by( 'id', $key );
			}//end if
			else
			{
				$filter_object = get_term_by( 'slug', $key, $type );
			}//end else

			$name = '';
			if ( ! is_wp_error( $filter_object ) && is_object( $filter_object ) )
			{
				$name = ! empty( $filter_object->name ) ? $filter_object->name : $filter_object->display_name;
			}//end if

			$item = $filter_template;
			$item = str_replace( '{{type}}', esc_attr( $type ), $item );
			$item = str_replace( '{{type_pretty}}', esc_attr( str_replace( '_', ' ', $type ) ), $item );
			$item = str_replace( '{{key}}', esc_attr( $key ), $item );
			$item = str_replace( '{{name}}', esc_html( $name ), $item );
		}//end if

		$zoom_levels = array(
			'day',
			'week',
			'month',
			'quarter',
		);

		$zoom = empty( $_GET['zoom'] ) ? 'day' : preg_replace( '/[^a-z]/', '', $_GET['zoom'] );
		$zoom = in_array( $zoom, $zoom_levels ) ? $zoom : 'day';
		?>
		<div class="options">
			<div id="zoom-levels" class="pull-right">
				<?php
				foreach ( $zoom_levels as $zoom_level )
				{
					?>
					<button type="button" data-zoom-level="<?php echo esc_attr( $zoom_level ); ?>" class="<?php echo $zoom_level == $zoom ? 'active' : ''; ?>"><?php echo ucwords( $zoom_level ); ?></button>
					<?php
				}//end foreach
				?>
			</div>
			<ul class="filters"><?php echo $item; ?></ul>
		</div>
		<script type="text/x-handlebars-template" id="filter-template">
			<?php echo $filter_template; ?>
		</script>

		<div id="legend"></div>
		<div id="top-graph">
			<div id="y-axis-left"></div>
			<div id="chart"></div>
			<div id="y-axis-right"></div>
			<div id="x-axis"></div>
		</div>

		<div id="stat-data">
			<!-- stat-row-template template will render here -->
			<div class="data-placeholder">
				<i class="fa fa-spinner fa-spin"></i>
			</div>
		</div>
	</section>

	<section id="criteria">
		<header>Select a knife to slice through the stats</header>
		<div id="taxonomy-data"><!-- taxonomy-criteria-template template will render here --></div>
	</section>
</div>

<script type="text/x-handlebars-template" id="taxonomy-criteria-template">
	<h3>Authors</h3>
	<ul>
		{{#each authors}}
			<li>
				<a href="#" data-type="author" data-key="{{key}}">{{name}}</a> ({{number_format hits}})
			</li>
		{{/each}}
	</ul>

	{{#each taxonomies}}
		<h3>{{@key}}</h3>
		<ul>
			{{#each this}}
				<li>
					<a href="#" data-type="{{taxonomy}}" data-key="{{key}}">{{name}}</a> ({{number_format hits}})
				</li>
			{{/each}}
		</ul>
	{{/each}}
</script>

<script type="text/x-handlebars-template" id="stat-row-template">
	<table>
		<thead>
			<tr>
				<th class="day">Item</th>
				<th class="posts">Posts</th>
				<th class="pvs">PVs</th>
				<th class="pvs-per-post">PVs/post</th>
				<th class="comments">Comments</th>
				<th class="comments-per-posts">Comments/post</th>
				<?php echo $content_match_th; ?>
			</tr>
			<tr class="stat-summary" data-num-posts="{{summary.posts}}">
				<th class="item">{{summary.items}} items</th>
				<th class="posts">{{summary.posts}}</th>
				<th class="pvs" data-num-pvs="{{summary.pvs}}"><i class="fa fa-spinner fa-spin"></i></th>
				<th class="pvs-per-post"><i class="fa fa-spinner fa-spin"></i></th>
				<th class="comments">{{number_format summary.comments}}</th>
				<th class="comments-per-post"></th>
				<?php echo $content_summary; ?>
			</tr>
		</thead>
		<tbody>
			{{#each stats}}
			<tr id="row-{{@key}}" class="stat-row" data-num-posts="{{posts}}">
				<td class="item">{{item}}</td>
				<td class="posts">
					{{#if ../link_posts}}
						<a href="#">{{number_format posts}} <i class="fa fa-angle-down"></i></a>
					{{else}}
						{{number_format posts}}
					{{/if}}
				</td>
				<td class="pvs">{{number_format pvs}}</td>
				<td class="pvs-per-post">{{decimal_format pvs_per_post}}</td>
				<td class="comments">{{number_format comments}}</td>
				<td class="comments-per-posts">{{decimal_format comments_per_post}}</td>
				<?php echo $content_row; ?>
			</tr>
			<tr id="row-posts-{{@key}}" class="stat-row-posts">
				<td colspan="<?php echo absint( $columns ); ?>">
				</td>
			</tr>
			{{/each}}
		</tbody>
		<tfoot>
			<tr class="stat-summary" data-num-posts="{{summary.posts}}">
				<th class="item">{{summary.items}} items</th>
				<th class="posts">{{summary.posts}}</th>
				<th class="pvs" data-num-pvs="{{summary.pvs}}"><i class="fa fa-spinner fa-spin"></i></th>
				<th class="pvs-per-post"><i class="fa fa-spinner fa-spin"></i></th>
				<th class="comments">{{number_format summary.comments}}</th>
				<th class="comments-per-post"></th>
				<?php echo $content_summary; ?>
			</tr>
			<tr>
				<th class="item">Item</th>
				<th class="posts">Posts</th>
				<th class="pvs">PVs</th>
				<th class="pvs-per-post">PVs/post</th>
				<th class="comments">Comments</th>
				<th class="comments-per-posts">Comments/post</th>
				<?php echo $content_match_th; ?>
			</tr>
		</tfoot>
	</table>
</script>

<script type="text/x-handlebars-template" id="post-row-template">
	<table>
		<thead>
			<tr>
				<th class="title">Title</th>
				<th class="pvs">PVs</th>
				<th class="comments">Comments</th>
				<?php echo $content_match_th; ?>
			</tr>
		</thead>
		<tbody>
			{{#each posts}}
			<tr id="post-{{id}}" class="post-row" data-num-posts="{{posts}}">
				<td class="title"><a href="{{{permalink}}}" target="_blank">{{{title}}}</a></td>
				<td class="pvs">{{number_format pvs}}</td>
				<td class="comments">{{number_format comments}}</td>
				<?php echo $posts_content_row; ?>
			</tr>
			{{/each}}
		</tbody>
	</table>
</script>