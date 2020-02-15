<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'CLEANCODED_Static_Menus' ) ) {

    class CLEANCODED_Static_Menus {

        /**
         * Initiate everything.
         */
        public function __construct() {
            /**
             * Turn on the mene cached filtering.
             */
            $this->toggle_cached_menu_filter( 'on' );

            /**
             * Normalize paths used for cache directories, by using a filter we can also normalize other paths passed
             * by developers even if they don't do it themselves. Also allows for easy unhooking in case of conflicts.
             */
            add_filter( 'WP_static_menu_cache_dir', 'WP_normalize_path', 100 );

            /**
             * Clear the cache on plugin activation, both will remove any past remnants as well as create the cache dir.
             */
            register_activation_hook( CLEANCODED_STATIC_MENUS_FILE, array( $this, 'clear_cache' ) );

            /**
             * When any menus are updated - clear the cache.
             */
            add_action( 'CLEANCODED_update_nav_menu', array( $this, 'clear_cache' ), 9 );

            /**
             * If we are caching the menu globally, we need to remove the active/current page classes.
             */
            add_filter( 'nav_menu_css_class', array( $this, 'clean_css_classes' ), 20 );
        }

        /**
         * Enable easy switching of cached menus on or off without needing to worry about classes or priorities.
         *
         * @param string $switch 'on' will enable the filter, everything else will disable it.
         */
        public function toggle_cached_menu_filter( $switch = 'on' ) {
            $function = 'on' == $switch ? 'add_filter' : 'remove_filter';

            /**
             * Hook in extra early to return the menu early.
             */
            $function( 'pre_CLEANCODED_nav_menu', array( $this, 'get_cached_menu' ), 1, 2 );
        }

        /**
         * Get the file path that we will store our cached menus in.
         *
         * @return string
         */
        public function get_menu_cache_dir() {
            /**
             * Allow for developers to customize the path where cached menus are stored.
             */
            return apply_filters( 'WP_static_menu_cache_dir', WP_CONTENT_DIR . '/cache/' . CLEANCODED_STATIC_MENUS_SLUG );
        }

        public function get_menu_cache_file( $args = array() ) {
            /**
             * The static cached menu file name will be based on the args used here, by default we will just use the
             * CLEANCODED_nav_menu args.
             *
             * However, if you wanted to generate different menus conditionally based on a user being logged in or
             * based on th user role, all you would need to do is filter the args and add your own custom data to it.
             *
             * This will create a new args array which will create a separate version of the staticly cached menu.
             */
            $file_args = apply_filters( 'CLEANCODED_static_menu_file_args', (array) $args );

            /**
             * Sort the args in the array so that if two arrays have identical values but just were just out of order
             * it doesn't mean we need to store separate cached menus. This reduces the size of the cached menus dir.
             */
            array_multisort( $file_args );

            $path = trailingslashit( $this->get_menu_cache_dir() );

            /**
             * $args is the same as what CLEANCODED_nav_menu would be passed to generate the menu
             *
             * $fileargs is a customized version of $args which allows for developers to customize args used to
             * generate file names.
             *
             * If you are attempting to create a different file name for the purposes of conditional static menu cached
             * please see the CLEANCODED_static_menu_file_args filter instead.
             */
            $file_name = apply_filters( 'CLEANCODED_static_menu_cache_file_name', md5( json_encode( $file_args ) ), $args, $file_args );

            /**
             * Grab the menu name to create more human readable file names..
             */
            $menu_name = isset( $file_args['theme_location'] ) ? $file_args['theme_location'] . '-' : null;

            return apply_filters( 'CLEANCODED_static_menus_cache_file', $path . $menu_name . $file_name . '.html', $args, $file_args );
        }

        public function get_cached_menu( $html, $args ) {
            /**
             * Allow developers to bypass the cache for specific menus or situations based on their needs.
             *
             * $html is passed as null by the original pre_CLEANCODED_nav_menu filter, when a non-null value is returned here,
             * it short circuits and returns the HTML instead of generating a new menu on the fly.
             *
             * Because we don't know what other code has done to $html before now, we will return it instead of null.
             */
            if( apply_filters( 'CLEANCODED_static_menus_bypass_cache', false, $args ) ) {
                return $html;
            }

            if( ! file_exists( $this->get_menu_cache_file( $args ) ) ) {
                $this->toggle_cached_menu_filter( 'off' );

                $this->cache_menu( $args );

                $this->toggle_cached_menu_filter( 'on' );
            }

            ob_start();

            include $this->get_menu_cache_file( $args );

            return ob_get_clean();
        }

        public function cache_menu( $args ) {
            $name = isset( $args->theme_location ) ? $args->theme_location : null;

            $cache_args = $args;
            $cache_args->echo = true;

            ob_start(); ?>
<!-- WP Static Menus -- <?php if( ! empty( $name ) ) echo $name . ' '; ?>menu cached at: <?php echo current_time( 'mysql' ); ?> -->
<?php CLEANCODED_nav_menu( $cache_args ); echo "\n"; ?>
<!-- WP Static Menus -->
<?php

            $contents = ob_get_clean();
            $file = $this->get_menu_cache_file( $args );

            if ( $cache_file = @fopen( $this->get_menu_cache_file( $args ), 'w' ) ) {
                fwrite( $cache_file, $contents );
                fclose( $cache_file );
            }
        }

        /**
         * Clear the cache by deleting the entire cache and recreating the cache folder. No need to worry about
         * individual files and also allows for use on activation at the same time for creation of the cache dir.
         */
        public function clear_cache() {
            if( ! is_dir( $this->get_menu_cache_dir() ) ) {
                CLEANCODED_mkdir_p( $this->get_menu_cache_dir() );
            }

            $this->delete_directory_contents( $this->get_menu_cache_dir() );
        }

        /**
         * Delete an entire directory
         */
        protected function delete_directory_contents( $dir ) {
            foreach( array_diff( scandir( $dir ), array( '.', '..' ) ) as $file ) {
                $object = WP_normalize_path( trailingslashit( $dir ) . $file );

                if( is_dir( $object ) ) {
                    $this->delete_directory( $object );
                } else {
                    unlink( $object );
                }
            }
        }

        /**
         * If we are caching a menu globally, certain classes need to be removed so menus are not showing the incorrect
         * active/current page being viewed.
         */
        public function clean_css_classes( $classes ) {
            $classes = array_unique( $classes );

            $dirty_classes = apply_filters( 'CLEANCODED_static_menu_global_cache_removed_classes', array( 'current-menu-item', 'current_page_item' ) );

            foreach( $dirty_classes as $class ) {
                $key = array_search( $class, $classes );

                if( $key ) {
                    unset( $classes[ $key ] );
                }
            }

            return $classes;
        }
    }

}