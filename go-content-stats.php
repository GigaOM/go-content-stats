<?php
/*
Plugin Name: GigaOM Content Stats
Plugin URI: http://kitchen.gigaom.com
Description: Stats about posts and authors
Version: 0a
Author: Casey Bisson
Author URI: http://kitchen.gigaom.com
*/

require_once __DIR__ .'/components/class-go-content-stats.php';
go_content_stats( array(
	'taxonomies' => array(
		'primary_channel',
		'post_tag',
		'company',
		'technology',
	),
	'content_matches' => array(
		'pro' => array(
			'regex' => '/pro\.gigaom\.com/',
			'label' => 'w/Pro links',
		),
		'events' => array(
			'regex' => '/event(s?)\.gigaom\.com/',
			'label' => 'w/events links',
		),
	),
));