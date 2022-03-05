<?php
/*
Plugin Name: Direct Crawler
Plugin URI: https://directac123.com/
Description: A WP-CLI command to get posts from the HTML site
Author: Enrique Chavez
Version: 1.0.0
Author URI: https://enriquechavez.co/
*/

include plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

add_action('cli_init', function(){
    \WP_CLI::add_command('direct', 'Tmeister\DirectCrawler\Parser');
});
