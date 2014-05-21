<?php
// create a sub-list of content match table headers
$content_match_th = '';
$content_summary = '';
$content_row = '';
if ( is_array( $this->config['content_matches'] ) )
{
	foreach ( $this->config['content_matches'] as $key => $match )
	{
		$content_match_th .= '<th class="' . esc_attr( $key ) . '">' . esc_attr( $match['label'] ) . '</th>';
		$content_summary .= '<th class="' . esc_attr( $key ) . '"></th>';
		$content_row .= '<td class="' . esc_attr( $key ) . '">{{number_format ' . esc_html( $key ) . '}}</td>';
	}// end foreach
}// end if

$type = isset( $_GET['type'] ) ? $_GET['type'] : 'general';
$key = isset( $_GET['key'] ) ? $_GET['key']: '';

$start = isset( $_GET['start'] ) ? preg_replace( '/[^0-9\-]/', '', $_GET['start'] ) : '';
$end = isset( $_GET['end'] ) ? preg_replace( '/[^0-9\-]/', '', $_GET['end'] ) : '';

if ( ! $start || ! $end )
{
	$start = date( 'Y-m-d', strtotime( '-30 days' ) );
	$end = date( 'Y-m-d' );
}//end if
?>

<div class="wrap" id="go-content-stats">
	<input type="hidden" id="<?php echo $this->get_field_id( 'type' ); ?>" name="<?php echo $this->get_field_name( 'type' ); ?>" value="<?php echo esc_attr( $type ); ?>"/>
	<input type="hidden" id="<?php echo $this->get_field_id( 'key' ); ?>" name="<?php echo $this->get_field_name( 'key' ); ?>" value="<?php echo esc_attr( $key ); ?>"/>

	<a href="#" id="<?php echo $this->get_field_id( 'clear-cache' ); ?>">Clear local cache</a>
	<h2>Gigaom Content Stats</h2>
	<section id="content-stats">
		<div id="date-range" class="pull-right">
			<i class="fa fa-calendar fa-lg"></i>
			<span><?php echo date( 'F j, Y', strtotime( $start ) ); ?> - <?php echo date( 'F j, Y', strtotime( $end ) ); ?></span>
			<b class="fa fa-angle-down"></b>
			<input type="hidden" id="<?php echo $this->get_field_id( 'start' ); ?>" name="<?php echo $this->get_field_name( 'start' ); ?>" value="<?php echo esc_attr( $start ); ?>"/>
			<input type="hidden" id="<?php echo $this->get_field_id( 'end' ); ?>" name="<?php echo $this->get_field_name( 'end' ); ?>" value="<?php echo esc_attr( $end ); ?>"/>
		</div>
		<header>Post performance</header>
		<ul class="filters"></ul>

		<div id="stat-data">
			<!-- stat-row-template template will render here -->
			<div class="data-placeholder"></div>
		</div>
	</section>

	<section id="criteria">
		<header>Select a knife to slice through the stats</header>
		<div id="taxonomy-data"><!-- taxonomy-criteria-template template will render here --></div>
	</section>

	<p>WPCom stats using API Key <?php echo esc_html( $this->get_wpcom_api_key() ); ?></p>
</div>

<script type="text/x-handlebars-template" id="taxonomy-criteria-template">
	<h3>Authors</h3>
	<ul>
		{{#each authors}}
			<li>
				<a href="#" data-type="author" data-key="{{key}}">{{name}} ({{number_format hits}})</a>
			</li>
		{{/each}}
	</ul>

	{{#each taxonomies}}
		<h3>{{@key}}</h3>
		<ul>
			{{#each this}}
				<li>
					<a href="#" data-type="{{taxonomy}}" data-key="{{key}}">{{name}} ({{number_format hits}})</a>
				</li>
			{{/each}}
		</ul>
	{{/each}}
</script>

<script type="text/x-handlebars-template" id="stat-row-template">
	<table>
		<thead>
			<tr>
				<th class="day">Day</th>
				<th class="posts">Posts</th>
				<th class="pvs">PVs</th>
				<th class="pvs-per-post">PVs/post</th>
				<th class="comments">Comments</th>
				<th class="comments-per-posts">Comments/post</th>
				<?php echo $content_match_th; ?>
			</tr>
			<tr class="stat-summary" data-num-posts="{{summary.posts}}">
				<th class="day">{{summary.days}} days</th>
				<th class="posts">{{summary.posts}}</th>
				<th class="pvs">{{summary.pvs}}</th>
				<th class="pvs-per-post">loading...</th>
				<th class="comments">{{summary.comments}}</th>
				<th class="comments-per-post"></th>
				<?php echo $content_summary; ?>
			</tr>
		</thead>
		<tbody>
			{{#each stats}}
			<tr id="row-{{@key}}" class="stat-row" data-num-posts="{{posts}}">
				<td class="day">{{day}}</td>
				<td class="posts"><a href="<?php echo admin_url( '/edit.php?m=' ); ?>{{day}}">{{number_format posts}}</a></td>
				<td class="pvs">{{pvs}}</td>
				<td class="pvs-per-post">{{pvs_per_post}}</td>
				<td class="comments">{{number_format comments}}</td>
				<td class="comments-per-posts">{{number_format comments_per_posts}}</td>
				<?php echo $content_row; ?>
			</tr>
			{{/each}}
		</tbody>
		<tfoot>
			<tr class="stat-summary" data-num-posts="{{summary.posts}}">
				<th class="day">{{summary.days}} days</th>
				<th class="posts">{{summary.posts}}</th>
				<th class="pvs">{{summary.pvs}}</th>
				<th class="pvs-per-post">loading...</th>
				<th class="comments">{{summary.comments}}</th>
				<th class="comments-per-post"></th>
				<?php echo $content_summary; ?>
			</tr>
			<tr>
				<th class="day">Day</th>
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
