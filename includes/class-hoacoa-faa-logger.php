<?php
/**
 * System Audit Logger
 *
 * @package           HOA/COA File Archive Assistant
 * @author            South Florida Web Advisors 
 * @version           1.0.5
 * @license           GPLv2 or later 
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HOACOA_FAA_Logger {

	public static function log( string $type, string $msg, string $status = 'Success' ): void {
		$options = get_option( 'hoacoa_faa_options', [] );
		$archive = $options['path_archive'] ?? '';

		if ( empty($archive) || ! is_dir($archive) ) return;

		$file = trailingslashit( $archive ) . 'hcaa-activity-log.txt';
		$entry = sprintf( "[%s] | %s | %s | %s\n", current_time('mysql'), strtoupper($type), $msg, strtoupper($status) );
		file_put_contents( $file, $entry, FILE_APPEND );
	}

	public static function get_logs(): array {
		$options = get_option( 'hoacoa_faa_options', [] );
		$file = trailingslashit( $options['path_archive'] ?? '' ) . 'hcaa-activity-log.txt';
		return ( file_exists($file) ) ? array_reverse( file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ) : [];
	}
}
