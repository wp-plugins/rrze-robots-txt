<?php
/**
 * Plugin Name: RRZE-Robots-Txt
 * Description: Ermöglich die Bearbeitung der robots.txt Inhalt um weitere Direktiven hinzuzufügen.
 * Version: 1.0
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

class RRZE_Robots_Txt {

    const version = '1.0'; // Plugin-Version
    
    const option_name = '_rrze_robots_txt';

    const version_option_name = '_rrze_robots_txt_version';
    
    private $options;
    
    const php_version = '5.2.4'; // Minimal erforderliche PHP-Version
    
    const wp_version = '3.4.1'; // Minimal erforderliche WordPress-Version
    
    public static function init() {
        
        if( get_option( 'blog_public' ) == 0 )
            return;

        load_plugin_textdomain( self::option_name, false, sprintf( '%slang', plugin_dir_path( __FILE__ ) ) );
                                
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

        add_action( 'admin_init', array( __CLASS__, 'settings_init' ) );

        add_filter( 'robots_txt', array( __CLASS__, 'robots_txt_filter' ), 10, 2 );

     }

    public static function activation() {
        self::version_compare();
        
        update_option( self::version_option_name , self::version );
    }
    
    public static function deactivation() {
        
    }
    
    public static function uninstall() {
        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || ( __FILE__ != WP_UNINSTALL_PLUGIN ) )
            return;
        
        delete_option( self::option_name );
        delete_option( self::version_option_name );
    }
    
    public static function version_compare() {
        $error = '';
        
        if ( version_compare( PHP_VERSION, self::php_version, '<' ) ) {
            $error = sprintf( __('Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', '_rrze'), PHP_VERSION, self::php_version );
        }

        if ( version_compare( $GLOBALS['wp_version'], self::wp_version, '<' ) ) {
            $error = sprintf( __('Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', '_rrze'), $GLOBALS['wp_version'], self::wp_version );
        }

        if( ! empty( $error ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ), false, true );
            wp_die( $error );
        }
        
    }
    
    private static function get_options( $key = '' ) {
        $defaults = array(
            'content' => ''
        );

        $options = (array) get_option( self::option_name );    
        $options = wp_parse_args( $options, $defaults );
        $options = array_intersect_key( $options, $defaults );

        if( !empty( $key ) )
            return isset($options[$key]) ? $options[$key] : null;

        return $options;
    }
        
    public static function enqueue_scripts() {
        wp_register_script( 'rrze-robots-txt', sprintf( '%sjs/rrze-robots-txt.js', plugin_dir_url( __FILE__ ) ) );
        wp_enqueue_script( 'rrze-robots-txt' );
    }
    
    public function settings_init() {
        register_setting( 'privacy', self::option_name, array( __CLASS__, 'options_validate' ) );
        add_settings_field( self::option_name, __( 'Inhalt der Datei robots.txt', '_rrze' ), array( __CLASS__, 'field_robots_txt_callback' ), 'privacy', 'default', array( 'label_for' => 'robots_txt' ) );
    }
    
    public function field_robots_txt_callback() {
        $content = self::get_options( 'content' );
        if( empty( $content ) ) 
            $content = self::default_content();
        ?>
        <textarea class="large-text code" id="<?php printf( '%s-content', self::option_name ); ?>" name="<?php printf( '%s[content]', self::option_name ); ?>" cols="50" rows="10"><?php echo esc_html( $content ); ?></textarea>
        <p class="description">
            <?php _e( 'Hinweis: Löschen Sie den Inhalt und speichern Sie die Änderungen, um die Standardeinstellungen wieder herzustellen.', '_rrze' ); ?>
        </p>
        <?php
    }

    public static function options_validate( $input ) {
        if( empty( $input['content'] ) ) {
            $options['content'] = self::default_content();
            add_settings_error( self::option_name, 'default-robots-txt', __( 'Inhalt der Datei robots.txt wieder auf die Standardwerte.', '_rrze' ), 'updated' );
        } else {
            $options['content'] = esc_html( strip_tags( $input['content'] ) );
        }
        return $options;
    }
    
    public static function robots_txt_filter( $robots_txt, $public ) {
        $content = self::get_options( 'content' );
        if( ! empty( $content ) && $public != 0 ) {
            $robots_txt = esc_attr( strip_tags( $content ) );
        }
        return $robots_txt;        
    }
    
    private static function default_content( ) {
        $content = "User-agent: *\n";
        $site_url = parse_url( site_url() );
        $path = ( !empty( $site_url['path'] ) ) ? $site_url['path'] : '';
        $content .= "Disallow: $path/wp-admin/\n";
        $content .= "Disallow: $path/wp-includes/\n";
        return $content;
    }
     
}

add_action( 'plugins_loaded', array( 'RRZE_Robots_Txt', 'init' ) );

register_activation_hook( __FILE__, array( 'RRZE_Robots_Txt', 'activation' ) );

register_deactivation_hook( __FILE__, array( 'RRZE_Robots_Txt', 'deactivation' ) );

register_uninstall_hook( __FILE__, array( 'RRZE_Robots_Txt', 'uninstall' ) );
