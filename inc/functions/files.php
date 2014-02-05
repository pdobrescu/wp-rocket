<?php
defined( 'ABSPATH' ) or	die( __( 'Cheatin&#8217; uh?', 'rocket' ) );


/**
 * Generate the content of advanced-cache.php file
 *
 * @since 2.0.3
 *
 */

function get_rocket_advanced_cache_file()
{

	$buffer = '<?php' . "\n";
	$buffer .= 'defined( \'ABSPATH\' ) or die( \'Cheatin\\\' uh?\' );' . "\n\n";

	// Get cache path
	$buffer .= '$rocket_cache_path = \'' . WP_ROCKET_CACHE_PATH . '\'' . ";\n";

	// Get config path
	$buffer .= '$rocket_config_path = \'' . WP_ROCKET_CONFIG_PATH . '\'' . ";\n";

	// Include the process file in buffer
	$buffer .= 'include( \''. WP_ROCKET_FRONT_PATH . 'process.php' . '\' );';

	return $buffer;

}



/**
 * Create advanced-cache.php file
 *
 * @since 2.0
 *
 */


function rocket_generate_advanced_cache_file()
{

	$buffer  = get_rocket_advanced_cache_file();
	rocket_put_content( WP_CONTENT_DIR . '/advanced-cache.php', $buffer );

}




/**
 * Generates the configuration file for the current domain based on the values ​​of options
 *
 * @since 2.0
 *
 */

function get_rocket_config_file()
{

	$options = get_option( WP_ROCKET_SLUG );

	if( !$options )
		return;

	$buffer = '<?php' . "\n";
	$buffer .= 'defined( \'ABSPATH\' ) or die( \'Cheatin\\\' uh?\' );' . "\n\n";
	$buffer .= '$rocket_cookie_hash = \'' . COOKIEHASH . '\'' . ";\n";

	foreach( $options as $option => $value )
	{

		if( $option == 'cache_ssl' || $option == 'cache_mobile' || $option == 'secret_cache_key' )
			$buffer .= '$rocket_' . $option . ' = \'' . $value . '\';' . "\n";

		if( $option == 'cache_reject_uri' )
			$buffer .= '$rocket_' . $option . ' = \'' . get_rocket_cache_reject_uri() . '\';' . "\n";

		if( $option == 'cache_reject_cookies' )
		{
			$cookies = get_rocket_cache_reject_cookies();
			$cookies = get_rocket_option( 'cache_logged_user' ) ? trim( str_replace( 'wordpress_logged_in_', '', $cookies ), '|' ) : $cookies;
			$buffer .= '$rocket_' . $option . ' = \'' . $cookies . '\';' . "\n";
		}
	}

	// If user use the rocket_url_no_dots filter
	if( apply_filters( 'rocket_url_no_dots', false ) )
		$buffer .= '$rocket_url_no_dots = \'1\';';


	$config_files_path = array();
	$urls = rocket_has_translation_plugin_active() ? get_rocket_subdomains_langs() : array( home_url() );

	foreach( $urls as $url )
	{

		$url = parse_url( rtrim( $url, '/' ) );

		if( !isset( $url['path'] ) )
		{
			$config_files_path[] = WP_ROCKET_CONFIG_PATH . $url['host'] . '.php';
		}
		else
		{

			$home_url_path       = explode( '/', trim( $url['path'], '/' ) );
			$home_url_start_path = reset( ( $home_url_path ) );
			$home_url_end_path   = end  ( ( $home_url_path ) );

			$config_dir_path     = WP_ROCKET_CONFIG_PATH . $url['host'];

			if( $home_url_start_path != $home_url_end_path )
				$config_dir_path = $config_dir_path . '/' . trim( str_replace( $home_url_end_path , '', $url['path'] ), '/' );

			if( !is_dir( $config_dir_path ) )
				rocket_mkdir_p( $config_dir_path );

			$config_file_name = $home_url_end_path . '.php';
			$config_files_path[] = $config_dir_path . '/' . $config_file_name;

		}

	}

	return array( $config_files_path, $buffer );

}



/**
 * Create the current config domain file
 * For example, if home_url() return example.com, the config domain file will be in /config/example.com
 *
 * @since 2.0
 *
 */

function rocket_generate_config_file()
{

	list( $config_files_path, $buffer ) = get_rocket_config_file();

	foreach( $config_files_path as $file )
		rocket_put_content( $file , $buffer );

}



/**
 * Added or set the value of the WP_CACHE constant
 *
 * @since 2.0
 *
 */

function set_rocket_wp_cache_define( $turn_it_on )
{

	// If WP_CACHE is already define, return to get a coffee
	if( $turn_it_on && defined( 'WP_CACHE' ) && WP_CACHE  )
		return;
		
	// Get path of the config file
	$config_file_path = rocket_find_wpconfig_path();
	
    if ( !$config_file_path )
        return;
		
	// Get content of the config file
	$config_file = file( $config_file_path );
	
	// Get permissions of wp-config.php
	$config_file_chmod = get_rocket_chmod( $config_file_path );
	
	// Get the value of WP_CACHE constant
	$turn_it_on = $turn_it_on ? 'true' : 'false';
	
	// Lets find out if the constant WP_CACHE is defined or not
	$is_wp_cache_exist = false;
	
	// Get WP_CACHE constant define
	$constant = "define('WP_CACHE', $turn_it_on); // Added by " . WP_ROCKET_PLUGIN_NAME . "\r\n";
	
	foreach ( $config_file as &$line ) 
	{
		
		if ( !preg_match( '/^define\(\'([A-Z_]+)\',([ ]+)/', $line, $match ) )
			continue;
			
		if( $match[1] == 'WP_CACHE' ) 
		{
			$is_wp_cache_exist = true;
			$line = $constant;
		}
					
	}
	unset( $line );
	
	// If the constant does not exist, it is created
	if( !$is_wp_cache_exist ) 
	{
		array_shift($config_file);
		array_unshift( $config_file, "<?php\r\n", $constant);	
	}
	
	// Insert the constant in wp-config.php file
	$handle = fopen( $config_file_path, 'w' );
	foreach( $config_file as $line ) 
		fwrite( $handle, $line );
	fclose( $handle );
	
	// Update the writing permissions of wp-config.php file
	chmod( $config_file_path, 0644 );
	
}



/**
 * Added or set the value of the COOKIE_DOMAIN constant
 *
 * @since 2.0
 *
 */

function set_rocket_cookie_domain_define( $turn_it_on )
{

	if( is_multisite() )
		return false;

	// If COOKIE_DOMAIN is already defined, return to get a coffee
	if( $turn_it_on && defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN  )
		return;

	$config_file = rocket_find_wpconfig_path();
    if ( !$config_file )
        return;

	// Get content of the config file
	$config_file_content = @file_get_contents( $config_file );

	if( !$turn_it_on )
	{

		$config_file_content = preg_replace( "~\\/\\*\\* Enable Cookie domain \\*\\*?\\/.*?\\/\\/ Added by ".WP_ROCKET_PLUGIN_NAME."(\r\n)*~s", '', $config_file_content );
		$config_file_content = preg_replace( "~(\\/\\/\\s*)?define\\s*\\(\\s*['\"]?COOKIE_DOMAIN['\"]?\\s*,.*?\\)\\s*;+\\r?\\n?~is", '', $config_file_content );

	}
	else
	{

		// Get the content of the COOKIE_DOMAIN constant added by WP Rocket
		$host = parse_url( home_url(), PHP_URL_HOST );
		$define = "/** Enable Cookie domain */\r\n" . "define('COOKIE_DOMAIN', '$host'); // Added by ".WP_ROCKET_PLUGIN_NAME."\r\n";

		$config_file_content = preg_replace( '~<\?(php)?~', "\\0\r\n" . $define, $config_file_content );

	}

	// Put the constant to the beginning of wp-config.php
	rocket_put_content( rocket_find_wpconfig_path(), $config_file_content );

}



/**
 * Delete all minify cache files
 *
 * @since 2.1
 *
 */

function rocket_clean_minify()
{

	do_action( 'before_rocket_clean_minify' );
	
	$extensions = apply_filters( 'rocket_clean_minify_ext', array( 'js', 'css' ) );
	$files = @glob( WP_ROCKET_MINIFY_CACHE_PATH . '/*.{' . implode( ',', $extensions ) . '}', GLOB_BRACE );
	@array_map( 'unlink' , $files );
	
	do_action( 'after_rocket_clean_minify' );

}



/**
 * Delete one or several cache files
 *
 * @since 2.0 Delete cache files for all users
 * @since 1.0
 *
 */

function rocket_clean_files( $urls )
{

	if( is_string( $urls ) )
		$urls = (array)$urls;

	$urls = apply_filters( 'rocket_clean_files', $urls );

    foreach( array_filter($urls) as $url )
    {

		do_action( 'before_rocket_clean_file', $url );

		if( $dirs = glob( WP_ROCKET_CACHE_PATH . rocket_remove_url_protocol( $url ) ) )
		{
			foreach( $dirs as $dir )
				rocket_rrmdir( $dir );
		}

		do_action( 'after_rocket_clean_file', $url );

	}

}



/**
 * Remove the home cache file and pagination
 *
 * @since 2.0 Delete cache files for all users
 * @since 1.0
 *
 */

function rocket_clean_home()
{
	$root = WP_ROCKET_CACHE_PATH . rocket_remove_url_protocol( home_url() );

	do_action( 'before_rocket_clean_home', $root );

	// Delete homepage
	if( $files = glob( $root . '*/index.html' ) )
	{
		foreach( $files as $file )
			@unlink( $file );
	}

	// Delete homepage pagination
	if( $dirs = glob( $root . '*/' . $GLOBALS['wp_rewrite']->pagination_base ) )
	{
		foreach( $dirs as $dir )
			rocket_rrmdir( $dir );
	}

    do_action( 'after_rocket_clean_home', $root );
}



/**
 * Remove all cache files of the domain
 *
 * @since 2.0 Delete domain cache files for all users
 * @since 1.0
 *
 */

function rocket_clean_domain()
{
	$domain = WP_ROCKET_CACHE_PATH . rocket_remove_url_protocol( home_url() );

	do_action( 'before_rocket_clean_domain', $domain );

	// Delete cache domain files
	if( $dirs = glob( $domain . '*' ) )
	{
		foreach( $dirs as $dir )
			rocket_rrmdir( $dir );
	}

    do_action( 'after_rocket_clean_domain', $domain );
}



/**
 * Remove only cache files of selected lang
 *
 * @since 2.0
 *
 */

function rocket_clean_domain_for_selected_lang( $lang )
{

	do_action( 'before_purge_cache_for_selected_lang' , $lang );

	list( $host, $path ) = get_rocket_parse_url_for_lang( $lang );
	if( $dirs = glob( WP_ROCKET_CACHE_PATH . $host . '*/' . $path ) )
	{
		foreach( $dirs as $dir )
			rocket_rrmdir( $dir, get_rocket_langs_to_preserve( $lang ) );
	}

	do_action( 'after_purge_cache_for_selected_lang' , $lang );
}



/**
 * Remove cache files of all langs
 *
 * @since 2.0
 *
 */

function rocket_clean_domain_for_all_langs()
{

	$langs = get_rocket_all_active_langs();

	if( rocket_is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) )
	{
		global $sitepress;
		$langs = array_keys( $langs );
	}

	do_action( 'before_rocket_clean_domain_for_all_langs' , $langs );

	// Remove all cache langs
	foreach ( $langs as $lang )
	{
		list( $host ) = get_rocket_parse_url_for_lang( $lang );
		if( $dirs = glob( WP_ROCKET_CACHE_PATH . $host . '*' ) )
		{
			foreach( $dirs as $dir )
				rocket_rrmdir( $dir );
		}
	}

	do_action( 'after_rocket_clean_domain_for_all_langs' , $langs );
}



/**
 * Remove a single file or a folder recursively
 *
 * @since 1.0
 *
 */

function rocket_rrmdir( $dir, $dirs_to_preserve = array() )
{

	$dir = rtrim( $dir, '/' );

	do_action( 'before_rocket_rrmdir', $dir, $dirs_to_preserve );

	if( !is_dir( $dir ) )
	{
		@unlink( $dir );
		return;
	};

    if( $dirs = glob( $dir . '/*' ) )
    {

		$keys = array();
		foreach( $dirs_to_preserve as $dir_to_preserve )
		{
			$matches = preg_grep( "#^$dir_to_preserve$#" , $dirs );
			$keys[] = reset( $matches );
		}

		$dirs = array_diff( $dirs, array_filter( $keys ) );
		foreach( $dirs as $dir )
		{
			if( is_dir( $dir ) )
				rocket_rrmdir( $dir, $dirs_to_preserve );
			else
				@unlink( $dir );
		}
	}

	@rmdir($dir);

	do_action( 'after_rocket_rrmdir', $dir, $dirs_to_preserve );
}



/**
 * Directory creation based on WordPress Filesystem
 *
 * @since 1.3.4
 *
 */

function rocket_mkdir( $dir )
{

	global $wp_filesystem;
    if( !$wp_filesystem )
    {
		require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
		require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );
		$wp_filesystem = new WP_Filesystem_Direct( new StdClass() );
	}
	return $wp_filesystem->mkdir( $dir, CHMOD_WP_ROCKET_CACHE_DIRS );
}



/**
 * Recursive directory creation based on full path.
 *
 * @source wp_mkdir_p() in /wp-includes/functions.php
 * @since 1.3.4
 */

function rocket_mkdir_p( $target )
{

	// from php.net/mkdir user contributed notes
	$target = str_replace( '//', '/', $target );

	// safe mode fails with a trailing slash under certain PHP versions.
	$target = rtrim($target, '/'); // Use rtrim() instead of untrailingslashit to avoid formatting.php dependency.
	if ( empty($target) )
		$target = '/';

	if ( file_exists( $target ) )
		return @is_dir( $target );

	// Attempting to create the directory may clutter up our display.
	if ( rocket_mkdir( $target ) ) {
		return true;
	} elseif ( is_dir( dirname( $target ) ) ) {
			return false;
	}

	// If the above failed, attempt to create the parent node, then try again.
	if ( ( $target != '/' ) && ( rocket_mkdir_p( dirname( $target ) ) ) )
		return rocket_mkdir_p( $target );

	return false;
}



/**
 * File creation based on WordPress Filesystem
 *
 * @since 2.1 add $chmod arg
 * @since 1.3.5
 *
 */

function rocket_put_content( $file, $content )
{

	global $wp_filesystem;
    if( !$wp_filesystem )
    {
		require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
		require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );
		$wp_filesystem = new WP_Filesystem_Direct( new StdClass() );
	}
	
	return $wp_filesystem->put_contents( $file, $content );
}



/**
 * TO DO
 *
 * @since 2.1
 *
 */

function rocket_fetch_and_cache_minify( $url, $pretty_url )
{

	$pretty_path = str_replace( WP_ROCKET_MINIFY_CACHE_URL, WP_ROCKET_MINIFY_CACHE_PATH, $pretty_url );

	if( file_exists( $pretty_path ) )
		return false;

	$ch = curl_init();
	$timeout = 5; // set to zero for no timeout
	curl_setopt ($ch, CURLOPT_URL, $url);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	curl_setopt ($ch, CURLOPT_USERAGENT, 'WP-Rocket-Minify');

	$content = curl_exec($ch);
	curl_close($ch);

	if ( $content )
	{

		if ( is_array( $content ) )
			$content = implode( $content );

		// save cache file
		if( rocket_put_content( $pretty_path, $content ) )
			return $content;

	}

	return false;

}


/**
 * Try to find the correct wp-config.php file, support one level up in filetree
 *
 * @since 2.1
 *
 */

function rocket_find_wpconfig_path()
{

	$config_file = get_home_path() . 'wp-config.php';
	$config_file_alt = dirname( get_home_path() ) . '/wp-config.php';
	
	if ( file_exists( $config_file ) && is_writable( $config_file ) )
	{
		
		return $config_file;

	} 
	else if ( file_exists( $config_file_alt ) && is_writable( $config_file_alt ) && !file_exists( dirname( get_home_path() ) . '/wp-settings.php' ) ) 
	{

		return $config_file_alt;

	}

	// No writable file found
	return false;

}