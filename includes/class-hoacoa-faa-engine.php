<?php
/**
 * Fuzzy Logic & File Processing Engine
 * @package HOA/COA File Archive Assistant
 * @version 1.2.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HOACOA_FAA_Engine {

	public function __construct() {
		add_action( 'wp_ajax_hoacoa_faa_run_system_audit', [ $this, 'ajax_run_system_audit' ] );
	}

	/**
	 * Runs the system audit, processes "Newest File" logic, and saves to persistent options.
	 */
	public function ajax_run_system_audit(): void {
		$opt = get_option( 'hoacoa_faa_options', [] );
		$root = trailingslashit($opt['path_owner'] ?? '');
		if ( ! is_dir($root) ) wp_send_json_error('Owner Portal Root not found.');

		$all_files = $this->get_files_recursive($root);
		$categories = (array)($opt['categories'] ?? []);
		$report = [];
		
		// Helper to track the latest date found per category unique key
		$latest_tracker = [];

		foreach ($all_files as $file) {
			$status = 'Ignored';
			$category_name = 'Unmanaged';
			$group_name = 'None';
			$retention_mode = 'sweep';
			$move_date = 'N/A';
			$extracted_timestamp = 0;
			$category_id = null;

			foreach ($categories as $index => $cat) {
				$cat_folder = trim($cat['folder'], DIRECTORY_SEPARATOR);
				
				// Match file path against defined category folder
				if ( strpos($file['full_path'], $cat_folder) !== false ) {
					$category_name = $cat['name'];
					$group_name = $cat['group'] ?? 'General';
					$retention_mode = $cat['mode'] ?? 'sweep';
					$category_id = $index;
					
					$regex = $this->generate_regex($cat['format'] ?? '');
					$matches = (bool)preg_match($regex, $file['name']);
					$status = $matches ? 'Valid' : 'Mismatch';
					
					if ($matches) {
						$extracted_date = $this->extract_date_string($file['name']);
						if ($extracted_date) {
							$extracted_timestamp = strtotime($extracted_date);
							$move_date = date('Y-m-d', strtotime('+1 year', $extracted_timestamp));
						}
					}
					break;
				}
			}

			$report_item = [
				'name'      => $file['name'],
				'category'  => $category_name,
				'group'     => $group_name,
				'status'    => $status,
				'move_on'   => $move_date,
				'mode'      => $retention_mode,
				'path'      => str_replace($root, '', $file['full_path']),
				'timestamp' => $extracted_timestamp,
				'cat_id'    => $category_id,
				'is_newest' => false
			];

			// Identify if this is the newest valid file for this specific category
			if ($status === 'Valid' && $category_id !== null) {
				if (!isset($latest_tracker[$category_id]) || $extracted_timestamp > $latest_tracker[$category_id]['ts']) {
					$latest_tracker[$category_id] = [
						'ts' => $extracted_timestamp,
						'idx' => count($report)
					];
				}
			}

			$report[] = $report_item;
		}

		// Mark the newest files in the final report
		foreach ($latest_tracker as $cat_data) {
			$report[$cat_data['idx']]['is_newest'] = true;
		}
		
		// Persistence: Move from transient to option 
		update_option( 'hcaa_last_audit_report', $report );
		update_option( 'hcaa_last_audit_time', current_time('mysql') );
		
		wp_send_json_success($report);
	}

	/**
	 * Extracts date from YYYY-MM-DD format.
	 */
	private function extract_date_string( string $filename ): ?string {
		if ( preg_match('/(\d{4})-(\d{2})-(\d{2})/', $filename, $m) ) {
			return "{$m[1]}-{$m[2]}-{$m[3]}";
		}
		return null;
	}

	/**
	 * Recursively scans directory for files.
	 */
	private function get_files_recursive( string $dir ): array {
		$results = [];
		if ( ! is_dir($dir) ) return [];
		$items = array_diff( scandir( $dir ), ['.', '..'] );
		foreach ( $items as $item ) {
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $path ) ) { 
				$results = array_merge( $results, $this->get_files_recursive( $path ) ); 
			} else { 
				$results[] = ['name' => $item, 'full_path' => $path]; 
			}
		}
		return $results;
	}

	/**
	 * Converts user format (YYYY-MM-DD %) into a PHP Regex.
	 */
	public function generate_regex( string $format ): string {
		if ( empty( $format ) ) return '/.*/';
		$find = ['YYYY', 'MM', 'DD', '%'];
		$replace = ['__Y__', '__M__', '__D__', '__W__'];
		$step1 = str_replace($find, $replace, $format);
		$step2 = preg_quote($step1, '/');
		return '/^' . str_replace(['__Y__', '__M__', '__D__', '__W__'], ['\d{4}', '\d{2}', '\d{2}', '.*'], $step2) . '$/i';
	}
}
