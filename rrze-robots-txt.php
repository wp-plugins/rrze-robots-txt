<?php
/**
 * Plugin Name: RRZE-Robots-Txt
 * Description: Ermöglich die Bearbeitung der robots.txt Inhalt um weitere Direktiven hinzuzufügen.
 * Version: 1.3
 * Author: rvdforst
 * Author URI: http://blogs.fau.de/webworking/
 * License: GPLv2 or later
 */

/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

add_action( 'plugins_loaded', array( 'RRZE_Robots_Txt', 'init' ) );

register_activation_hook( __FILE__, array( 'RRZE_Robots_Txt', 'activation' ) );

class RRZE_Robots_Txt {

    const version = '1.3'; // Plugin-Version
    
    const option_name = '_rrze_robots_txt';

    const version_option_name = '_rrze_robots_txt_version';
    
    const textdomain = '_rrze_robots_txt';
    
    const php_version = '5.2.4'; // Minimal erforderliche PHP-Version
    
    const wp_version = '3.5'; // Minimal erforderliche WordPress-Version
    
    public static function init() {
        
        if( is_multisite() && ! is_subdomain_install() && ! self::is_base_site() )
            return;
        
        if( get_option( 'blog_public' ) == 0 )
            return;

        load_plugin_textdomain( self::textdomain, false, sprintf( '%slang', plugin_dir_path( __FILE__ ) ) );
        
        add_action( 'init', array( __CLASS__, 'update_version' ) );
                                
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

        add_action( 'admin_init', array( __CLASS__, 'settings_init' ) );

        add_filter( 'robots_txt', array( __CLASS__, 'robots_txt_filter' ), 10, 2 );

     }

    public static function activation() {
        self::version_compare();
        
        update_option( self::version_option_name , self::version );
    }
        
    public static function version_compare() {
        $error = '';
        
        if ( version_compare( PHP_VERSION, self::php_version, '<' ) ) {
            $error = sprintf( __( 'Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', self::textdomain ), PHP_VERSION, self::php_version );
        }

        if ( version_compare( $GLOBALS['wp_version'], self::wp_version, '<' ) ) {
            $error = sprintf( __( 'Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', self::textdomain ), $GLOBALS['wp_version'], self::wp_version );
        }

        if( ! empty( $error ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ), false, true );
            wp_die( $error );
        }
        
    }
    
    public static function update_version() {
		if( get_option( self::version_option_name, null) != self::version )
			update_option( self::version_option_name , self::version );
    }
    
    private static function get_options( $key = '' ) {
        $defaults = array(
            'content' => ''
        );

        $options = (array) get_option( self::option_name );
        $options = wp_parse_args( $options, $defaults );
        $options = array_intersect_key( $options, $defaults );

        if( !empty( $key ) )
            return isset( $options[$key] ) ? $options[$key] : null;

        return $options;
    }
        
    public static function enqueue_scripts() {
        wp_register_script( 'rrze-robots-txt', sprintf( '%sjs/rrze-robots-txt.js', plugin_dir_url( __FILE__ ) ) );
        wp_enqueue_script( 'rrze-robots-txt' );
    }
    
    public function settings_init() {
        register_setting( 'reading', self::option_name, array( __CLASS__, 'options_validate' ) );
        add_settings_field( self::option_name, __( 'Inhalt der Datei robots.txt', self::textdomain ), array( __CLASS__, 'field_robots_txt_callback' ), 'reading', 'default', array( 'label_for' => 'robots_txt' ) );
    }
    
    public function field_robots_txt_callback() {
        $content = rtrim( self::get_options( 'content' ) );
        if( empty( $content ) ) 
            $content = is_multisite() && ! is_subdomain_install() ? self::default_network_content () : self::default_site_content();
        ?>
        <textarea class="large-text code" id="<?php printf( '%s-content', self::option_name ); ?>" name="<?php printf( '%s[content]', self::option_name ); ?>" cols="50" rows="10"><?php echo esc_html( $content ); ?></textarea>
        <p class="description">
            <?php _e( 'Hinweis: Löschen Sie den Inhalt und speichern Sie die Änderungen, um die Standardeinstellungen wieder herzustellen.', self::textdomain ); ?>
        </p>
        <?php
    }

    public static function options_validate( $input ) {
        $content = '';
        
        if( isset( $input['content'] ) )
            $content = rtrim( $input['content'] );
        
        if( empty( $content ) ) {
            $content = is_multisite() && ! is_subdomain_install() ? self::default_network_content () : self::default_site_content();
            add_settings_error( self::option_name, 'default-robots-txt', __( 'Inhalt der Datei robots.txt wieder auf die Standardwerte.', self::textdomain ), 'updated' );            
        }
        
        $input['content'] = $content;
        
        return $input;
    }
    
    public static function robots_txt_filter( $robots_txt, $public ) {
        global $wpdb;
                
        $content = rtrim( self::get_options( 'content' ) );
        
        if( ! empty( $content ) && $public != 0 )
            $robots_txt = esc_attr( strip_tags( $content ) );
        
        if( is_multisite() && ! is_subdomain_install() ) {
            $site_path = rtrim( parse_url( network_site_url(), PHP_URL_PATH ), '/' );
            if( empty( $site_path ) )
                $site_path = '';
            
            $robots_txt .= PHP_EOL;
            $blogs = $wpdb->get_results( $wpdb->prepare( "SELECT path FROM {$wpdb->blogs} WHERE site_id = %d AND public = '0' AND archived = '0' AND mature = '0' AND spam = '0' AND deleted = '0' ORDER BY path ASC", $wpdb->siteid ) );        
            foreach( $blogs as $blog ) {
                $robots_txt .= sprintf( 'Disallow: %s%s%s', $site_path, $blog->path, PHP_EOL );
            }
        }
        
        return $robots_txt;        
    }
    
    private static function default_site_content( ) {
        $content = sprintf( 'User-agent: *%s', PHP_EOL );
        
        $site_path = rtrim( parse_url( site_url(), PHP_URL_PATH ), '/' );
        if( empty( $site_path ) )
            $site_path = '';
        
        $content .= sprintf( 'Disallow: %s/wp-admin/%s', $site_path, PHP_EOL );
        $content .= sprintf( 'Disallow: %s/wp-includes/%s', $site_path, PHP_EOL );
        
        return $content;
    }
     
    private static function default_network_content( ) {
        $content = sprintf( 'User-agent: *%s', PHP_EOL );
        
        $site_path = rtrim( parse_url( network_site_url(), PHP_URL_PATH ), '/' );
        if( empty( $site_path ) )
            $site_path = '';
        
        $content .= sprintf( 'Disallow: %s/wp-admin/%s', $site_path, PHP_EOL );
        $content .= sprintf( 'Disallow: %s/wp-includes/%s', $site_path, PHP_EOL );
        $content .= sprintf( 'Disallow: %s/*/wp-admin/%s', $site_path, PHP_EOL );
        $content .= sprintf( 'Disallow: %s/*/wp-includes/%s', $site_path, PHP_EOL );
                
        return $content;
    }
    
    private static function is_base_site() {
        global $current_site, $current_blog;
        
        if( $current_site->path == $current_blog->path )
            return true;
        
        return false;
    }
    
}
