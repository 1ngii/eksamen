Husk å sjekke wp-config.php om:

if ( ! defined( 'WP_DEBUG' ) ) {
	define('WP_DEBUG', true);
	define('WP_DEBUG_LOG', true);
	define('WP_DEBUG_DISPLAY', false);
}

og 

define('DISABLE_WP_CRON', false);

Hvis det enda ikke fungerer husk:
wp cron event list
wp cron event run 

Husk også å sjekke tidssone på WP
