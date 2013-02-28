<?php
/*
Plugin Name: GigaOM Post Stats
Plugin URI: http://kitchen.gigaom.com
Description: Stats about posts and authors
Version: 0a
Author: Casey Bisson
Author URI: http://kitchen.gigaom.com
*/

require_once __DIR__ .'/components/class-go-post-stats.php';
go_post_stats( array( 'primary_channel', 'post_tag' , 'company' , 'technology' ));