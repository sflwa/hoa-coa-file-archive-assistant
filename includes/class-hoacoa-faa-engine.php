<?php
/**
 * Fuzzy Logic & File Processing Engine
 * @package HOA/COA File Archive Assistant
 * @version 1.2.3
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HOACOA_FAA_Engine {

	public function __construct() {
		add_action( 'wp_ajax_hoacoa_faa_run_system_audit', [ $this, 'ajax_run_system_audit' ] );
	}

	public function ajax_run_system_audit(): void {
		$opt = get_option( 'hoacoa_faa_options', [] );
		$root = trailingslashit($opt['path_owner'] ?? '');
		if ( ! is_dir($root) ) wp_send_json_error('Owner Portal Root not found.');

		$all_files = $this->get_files_recursive($root);
		$categories = (array)($opt['categories'] ?? []);
		$report = [];

		foreach ($all_files as $file) {
			$status = 'Ignored';
			$category_name = 'Unmanaged';
			$move_date = 'N/A';

			foreach ($categories as $cat) {
				$cat_folder = trim($cat['folder'], DIRECTORY_SEPARATOR);
				if ( strpos($file['full_path'], $cat_folder) !== false ) {
					$category_name = $cat['name'];
					$regex = $this->generate_regex($cat['format'] ?? '');
					$matches = (bool)preg_match($regex, $file['name']);
					$status = $matches ? 'Valid' : 'Mismatch';
					
					if ($matches) {
						$move_date = $this->calculate_move_date($file['name']);
					}
					break;
				}
			}

			$report[] = [
				'name'     => $file['name'],
				'category' => $category_name,
				'status'   => $status,
				'move_on'  => $move_date,
				'path'     => str_replace($root, '', $file['full_path'])
			];
		}
		
		set_transient( 'hcaa_last_audit_report', $report, 12 * HOUR_IN_SECONDS );
		wp_send_json_success($report);
	}

	/**
	 * 12-Month Retention Logic:
	 * Extracts date from YYYY-MM-DD and adds exactly 1 year.
	 */
	private function calculate_move_date( string $filename ): string {
		if ( preg_match('/(\d{4})-(\d{2})-(\d{2})/', $filename, $m) ) {
			$doc_date = "{$m[1]}-{$m[2]}-{$m[3]}";
			return date('Y-m-d', strtotime('+1 year', strtotime($doc_date)));
		}
		return 'Check Format';
	}

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

	public function generate_regex( string $format ): string {
		if ( empty( $format ) ) return '/.*/';
		$find = ['YYYY', 'MM', 'DD', '%'];
		$replace = ['__Y__', '__M__', '__D__', '__W__'];
		$step1 = str_replace($find, $replace, $format);
		$step2 = preg_quote($step1, '/');
		return '/^' . str_replace(['__Y__', '__M__', '__D__', '__W__'], ['\d{4}', '\d{2}', '\d{2}', '.*'], $step2) . '$/i';
	}
}
