<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class WLA_Storage {
        /**
         * Get configured storage location.
         *
         * @return string
         */
        public static function get_location() {
                $loc = get_option( 'wla_storage_location', 'content' );
                if ( ! in_array( $loc, [ 'content', 'uploads' ], true ) ) {
                        return 'content';
                }

                return $loc;
        }

        /**
         * Base directory for storage.
         *
         * @return string
         */
        public static function get_base_dir() {
                if ( 'uploads' === self::get_location() ) {
                        $uploads = wp_upload_dir();
                        return trailingslashit( $uploads['basedir'] ) . 'wla-assets/';
                }

                return trailingslashit( WP_CONTENT_DIR ) . 'wla-assets/';
        }

        /**
         * Base URL for storage.
         *
         * @return string
         */
        public static function get_base_url() {
                if ( 'uploads' === self::get_location() ) {
                        $uploads = wp_upload_dir();
                        return trailingslashit( $uploads['baseurl'] ) . 'wla-assets/';
                }

                return trailingslashit( content_url() ) . 'wla-assets/';
        }

        /**
         * Ensure required directories exist and are writable.
         *
         * @return bool
         */
        public static function ensure_dirs() {
                $base_dir = self::get_base_dir();
                $dirs     = [
                        $base_dir,
                        $base_dir . 'fonts/',
                        $base_dir . 'css/',
                        $base_dir . 'logs/',
                ];

                foreach ( $dirs as $dir ) {
                        if ( ! wp_mkdir_p( $dir ) ) {
                                return false;
                        }

                        if ( ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
                                return false;
                        }
                }

                return true;
        }

        /**
         * Get directory for subfolder.
         *
         * @param string $sub
         * @return string
         */
        public static function get_dir( $sub ) {
                $sub = self::normalize_subdir( $sub );
                return self::get_base_dir() . $sub . '/';
        }

        /**
         * Get URL for subfolder.
         *
         * @param string $sub
         * @return string
         */
        public static function get_url( $sub ) {
                $sub = self::normalize_subdir( $sub );
                return self::get_base_url() . $sub . '/';
        }

        /**
         * Normalize allowed subdirectories.
         *
         * @param string $sub
         * @return string
         */
        private static function normalize_subdir( $sub ) {
                if ( ! in_array( $sub, [ 'fonts', 'css', 'logs' ], true ) ) {
                        $sub = 'css';
                }

                return $sub;
        }
}
