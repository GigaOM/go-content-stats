<?php
/**
 * Plugin Name: Gigaom Content Stats
 * Plugin URI: http://kitchen.gigaom.com
 * Description: Stats about posts and authors
 * Version: 0a
 * Author: Casey Bisson
 * Author URI: http://kitchen.gigaom.com
 * Dependencies: go-google, go-graphing, go-timepicker
 */

require_once __DIR__ .'/components/class-go-content-stats.php';
go_content_stats();

register_activation_hook( __FILE__, array( go_content_stats(), 'activate' ) );
