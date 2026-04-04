<?php
/**
 * Admin UI & Infrastructure
 * @package HOA/COA File Archive Assistant
 * @version 1.2.19
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HOACOA_FAA_Admin {

	public const DISCLAIMER = 'This tool is provided for administrative guidance and reminder purposes only. It does not constitute legal advice or guarantee statutory compliance with Florida Statutes (FS 718/720). Your Association’s specific Governing Documents may require stricter notice periods; always verify requirements with legal counsel.';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'settings_init' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_head', [ $this, 'inject_fm_pro_hash_fix' ], 1 );
		add_action( 'wp_dashboard_setup', [ $this, 'add_dashboard_widgets' ] );
		
		add_action( 'wp_ajax_hoacoa_faa_check_path', [ $this, 'ajax_check_path' ] );
		add_action( 'wp_ajax_hoacoa_faa_create_path', [ $this, 'ajax_create_path' ] );
		add_action( 'wp_ajax_hoacoa_faa_validate_category', [ $this, 'ajax_validate_category' ] );
	}

	public function add_menu(): void {
		$dashboard = new HOACOA_FAA_Dashboard();
		
		add_menu_page( 'Archive Assistant', 'Archive Assistant', 'manage_options', 'hoacoa-faa-main', [ $dashboard, 'render' ], 'dashicons-archive', 4 );
		add_submenu_page( 'hoacoa-faa-main', 'Dashboard', 'Dashboard', 'manage_options', 'hoacoa-faa-main', [ $dashboard, 'render' ] );
		add_submenu_page( 'hoacoa-faa-main', 'Audit Report', 'Audit Report', 'manage_options', 'hoacoa-faa-audit', [ $this, 'render_audit_report' ] );
		add_submenu_page( 'hoacoa-faa-main', 'Settings', 'Settings', 'manage_options', 'hoacoa-faa-settings', [ $this, 'render_settings' ] );
		add_submenu_page( 'hoacoa-faa-main', 'Activity Log', 'Activity Log', 'manage_options', 'hoacoa-faa-logs', [ $this, 'render_logs' ] );
	}

	public function render_audit_report() {
		$cached = get_transient( 'hcaa_last_audit_report' );
		?>
		<div class="wrap">
			<h1>System Audit Report</h1>
			<div class="hcaa-audit-actions" style="margin: 20px 0; display: flex; align-items: center; gap: 20px;">
				<button type="button" class="button button-primary" id="hcaa-run-audit">Re-Scan System Now</button>
				<?php if ( $cached ) : ?>
					<ul class="subsubsub" style="margin: 0; float: none;">
						<li><a href="#" class="hcaa-filter current" data-filter="all">All <span class="count">(<?php echo count($cached); ?>)</span></a> |</li>
						<li><a href="#" class="hcaa-filter" data-filter="Valid">Valid <span class="count">(<?php echo count(array_filter($cached, fn($f) => $f['status'] === 'Valid')); ?>)</span></a> |</li>
						<li><a href="#" class="hcaa-filter" data-filter="Mismatch">Mismatch <span class="count" style="color:#d63638;">(<?php echo count(array_filter($cached, fn($f) => $f['status'] === 'Mismatch')); ?>)</span></a> |</li>
						<li><a href="#" class="hcaa-filter" data-filter="Ignored">Unmanaged <span class="count">(<?php echo count(array_filter($cached, fn($f) => $f['status'] === 'Ignored')); ?>)</span></a></li>
					</ul>
				<?php endif; ?>
			</div>
			<div id="hcaa-audit-results">
				<?php if ( $cached ) : ?>
					<table class="widefat striped" id="hcaa-audit-table">
						<thead><tr><th>File</th><th>Category</th><th>Audit Status</th><th>Scheduled Move</th></tr></thead>
						<tbody>
							<?php foreach ( $cached as $f ) : 
								$color = ( $f['status'] === 'Valid' ) ? 'green' : ( $f['status'] === 'Mismatch' ? 'red' : 'gray' );
								$fm_url = $this->get_file_manager_link($f['path']);
								?>
								<tr class="hcaa-row" data-status="<?php echo esc_attr($f['status']); ?>">
									<td>
										<strong><?php echo esc_html($f['name']); ?></strong><br>
										<small><?php echo esc_html($f['path']); ?></small>
										<?php if ($f['status'] === 'Mismatch' && !empty($fm_url)) : ?>
											<div style="margin-top:5px;"><a href="<?php echo esc_url($fm_url); ?>" class="button button-small" target="_blank">Open Folder to Rename</a></div>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html($f['category']); ?></td>
									<td style="color:<?php echo $color; ?>; font-weight:bold;"><?php echo esc_html($f['status']); ?></td>
									<td><code><?php echo esc_html($f['move_on']); ?></code></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function render_settings() { 
		$tab = $_GET['tab'] ?? 'folders'; $opt = get_option('hoacoa_faa_options', []);
		?>
		<div class="wrap">
			<h1>Settings</h1>
			<div class="nav-tab-wrapper">
				<a href="?page=hoacoa-faa-settings&tab=folders" class="nav-tab <?php echo $tab=='folders'?'nav-tab-active':''; ?>">Folders & Bridges</a>
				<a href="?page=hoacoa-faa-settings&tab=categories" class="nav-tab <?php echo $tab=='categories'?'nav-tab-active':''; ?>">Categories</a>
			</div>
			<form method="post" action="options.php">
				<?php settings_fields('hoacoa_faa_options_group'); ?>
				<?php if ($tab == 'folders') : ?>
					<table class="form-table">
						<tr><th>Owners Root</th><td><input type="text" name="hoacoa_faa_options[path_owner]" value="<?php echo esc_attr($opt['path_owner']??''); ?>" class="large-text"><button type="button" class="button hcaa-validate-path">Check</button><span class="path-status"></span></td></tr>
						<tr><th>Archive Root</th><td><input type="text" name="hoacoa_faa_options[path_archive]" value="<?php echo esc_attr($opt['path_archive']??''); ?>" class="large-text"><button type="button" class="button hcaa-validate-path">Check</button><span class="path-status"></span></td></tr>
						<tr>
							<th>File Manager Bridge</th>
							<td>
								<select name="hoacoa_faa_options[fm_bridge]">
									<option value="none" <?php selected($opt['fm_bridge']??'', 'none'); ?>>None</option>
									<option value="wp-file-manager" <?php selected($opt['fm_bridge']??'', 'wp-file-manager'); ?>>WP File Manager</option>
									<option value="file-manager-advanced" <?php selected($opt['fm_bridge']??'', 'file-manager-advanced'); ?>>File Manager Advanced</option>
									<option value="filester" <?php selected($opt['fm_bridge']??'', 'filester'); ?>>Filester</option>
								</select>
							</td>
						</tr>
					</table>
				<?php else : ?>
					<table class="widefat striped" id="hcaa-category-table">
						<thead><tr><th>Category</th><th>Sub-folder</th><th>Format</th><th>Action</th></tr></thead>
						<tbody>
							<?php foreach ((array)($opt['categories']??[]) as $i => $c) : ?>
								<tr>
									<td><input type="text" name="hoacoa_faa_options[categories][<?php echo $i; ?>][name]" value="<?php echo esc_attr($c['name']??''); ?>"></td>
									<td><input type="text" name="hoacoa_faa_options[categories][<?php echo $i; ?>][folder]" value="<?php echo esc_attr($c['folder']??''); ?>"><button type="button" class="button hcaa-validate-path hcaa-validate-category">Check</button><span class="path-status"></span></td>
									<td><input type="text" name="hoacoa_faa_options[categories][<?php echo $i; ?>][format]" value="<?php echo esc_attr($c['format']??''); ?>" style="width:100%;"></td>
									<td><button type="button" class="button hcaa-remove-row">Remove</button></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p><button type="button" class="button hcaa-add-category">Add Category</button></p>
					<script type="text/template" id="hcaa-category-template"><tr><td><input type="text" name="hoacoa_faa_options[categories][{{INDEX}}][name]"></td><td><input type="text" name="hoacoa_faa_options[categories][{{INDEX}}][folder]"><button type="button" class="button hcaa-validate-path hcaa-validate-category">Check</button><span class="path-status"></span></td><td><input type="text" name="hoacoa_faa_options[categories][{{INDEX}}][format]" style="width:100%;"></td><td><button type="button" class="button hcaa-remove-row">Remove</button></td></tr></script>
				<?php endif; ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function render_logs() {
		$logs = HOACOA_FAA_Logger::get_logs();
		echo '<div class="wrap"><h1>Activity Log</h1><table class="widefat striped"><thead><tr><th>Time</th><th>Type</th><th>Message</th></tr></thead><tbody>';
		foreach($logs as $l) { $p = explode('|',$l); echo '<tr><td>'.($p[0]??'').'</td><td>'.($p[1]??'').'</td><td>'.($p[2]??'').'</td></tr>'; }
		echo '</tbody></table></div>';
	}
	
	private function get_file_manager_link( string $full_file_path = '' ): string {
		$opt = get_option('hoacoa_faa_options', []);
		$bridge = $opt['fm_bridge'] ?? 'none';
		$base_url = match($bridge) {
			'wp-file-manager' => admin_url('admin.php?page=wp_file_manager'),
			'file-manager-advanced' => admin_url('admin.php?page=file_manager_advanced_ui'),
			'filester' => admin_url('admin.php?page=njt-fs-filemanager'),
			default => '',
		};
		if ( empty($base_url) || empty($full_file_path) ) return $base_url;
		$absolute_dir = dirname(trailingslashit($opt['path_owner'] ?? '') . ltrim($full_file_path, '/'));
		$volume_root = ABSPATH;
		if ( $bridge === 'file-manager-advanced' ) {
			$fma_opt = get_option('fmaoptions');
			$volume_root = $fma_opt['public_path'] ?? ABSPATH;
		} elseif ( $bridge === 'filester' ) {
			$fs_opt = get_option('njt_fs_settings');
			if ( !empty($fs_opt['njt_fs_file_manager_settings']['root_folder_path']) ) { $volume_root = $fs_opt['njt_fs_file_manager_settings']['root_folder_path']; }
		} elseif ( $bridge === 'wp-file-manager' ) {
			$wpfm_opt = get_option('wp_file_manager_settings');
			if ( !empty($wpfm_opt['public_path']) ) { $volume_root = $wpfm_opt['public_path']; }
		}
		$relative = str_replace( trailingslashit($volume_root), '', trailingslashit($absolute_dir) );
		$relative = trim(str_replace('\\', '/', $relative), '/'); 
		if (empty($relative)) { $hash = 'l1_Lw'; } else { $hash = 'l1_' . rtrim(strtr(base64_encode($relative), '+/=', '-_.'), '.'); }
		return $base_url . '#elf_' . $hash;
	}

	public function enqueue_assets($h) { 
		if(!str_contains($h,'hoacoa-faa')) return; 
		wp_add_inline_script('jquery-core', "jQuery(document).ready(function($){ 
			$('.hcaa-filter').on('click', function(e){
				e.preventDefault();
				$('.hcaa-filter').removeClass('current');
				$(this).addClass('current');
				var filter = $(this).data('filter');
				if(filter === 'all'){ $('.hcaa-row').show(); } 
				else { $('.hcaa-row').hide().filter('[data-status=\"'+filter+'\"]').show(); }
			});
			$('#hcaa-run-audit').on('click', function(){ 
				var b=$(this); b.attr('disabled',true).text('Scanning...'); 
				$.post(ajaxurl,{action:'hoacoa_faa_run_system_audit'},function(){location.reload();}); 
			}); 
			$(document).on('click','.hcaa-validate-path',function(){ 
				var b=$(this); var td=b.closest('td'); 
				$.post(ajaxurl,{action:b.hasClass('hcaa-validate-category')?'hoacoa_faa_validate_category':'hoacoa_faa_check_path',path:td.find('input[type=text]').val()},function(r){
					td.find('.path-status').html(r.success?'✔':'✖ <button type=\"button\" class=\"button hcaa-create-path\">Create</button>');
				}); 
			}); 
			$(document).on('click','.hcaa-create-path',function(){ 
				var td=$(this).closest('td'); 
				$.post(ajaxurl,{action:'hoacoa_faa_create_path',path:td.find('input[type=text]').val(),is_category:td.find('.hcaa-validate-category').length>0},function(r){
					if(r.success)td.find('.path-status').html('✔');
				}); 
			}); 
			$('.hcaa-add-category').on('click',function(){ 
				var t=$('#hcaa-category-table tbody'); 
				t.append($('#hcaa-category-template').html().replace(/{{INDEX}}/g,t.find('tr').length)); 
			}); 
			$(document).on('click','.hcaa-remove-row',function(){ $(this).closest('tr').remove(); }); 
		});"); 
	}

	public function inject_fm_pro_hash_fix(): void {
		if ( ! isset( $_GET['page'] ) ) return;
		$fm_pages = [ 'wp_file_manager', 'file_manager_advanced_ui', 'njt-fs-filemanager' ];
		if ( ! in_array( $_GET['page'], $fm_pages ) ) return;
		?>
		<script>
		(function($) {
			if (window.location.hash && window.location.hash.indexOf('#elf_l1_') === 0) {
				var originalReplaceState = history.replaceState;
				history.replaceState = function(state, title, url) {
					if (url && url.indexOf('admin.php') !== -1 && url.indexOf('#') === -1) { return; }
					return originalReplaceState.apply(history, arguments);
				};
				$(document).on('elfinderready', function() {
					if (typeof elFinderInstance !== 'undefined') {
						elFinderInstance._fmaFirstRootBound = true; 
						elFinderInstance._wpfmFirstRootBound = true;
					}
				});
			}
		})(jQuery);
		</script>
		<?php
	}

	public function add_dashboard_widgets(): void {
		wp_add_dashboard_widget( 'hcaa_disclaimer_widget', 'HCAA Compliance Notice', [ $this, 'render_disclaimer_widget' ] );
	}

	public function render_disclaimer_widget(): void {
		echo '<div class="hcaa-widget-content">';
		echo '<p style="font-style: italic; color: #646970; border-left: 3px solid #d63638; padding-left: 12px; margin-bottom: 15px;">' . esc_html( self::DISCLAIMER ) . '</p>';
		echo '<a href="' . admin_url('admin.php?page=hoacoa-faa-main') . '" class="button button-primary">Archive Assistant Dashboard</a>';
		echo '</div>';
	}

	public function settings_init() { register_setting( 'hoacoa_faa_options_group', 'hoacoa_faa_options', [ $this, 'sanitize_options' ] ); }
	public function sanitize_options( $new ) { return array_merge( (array)get_option('hoacoa_faa_options', []), (array)$new ); }
	public function ajax_check_path() { wp_send_json(['success'=>is_dir($_POST['path'])]); }
	public function ajax_validate_category() { $opt = get_option('hoacoa_faa_options'); $p = trailingslashit($opt['path_owner']).$_POST['path']; wp_send_json(['success'=>is_dir($p)]); }
	public function ajax_create_path() { $opt = get_option('hoacoa_faa_options'); $p = filter_var($_POST['is_category'], FILTER_VALIDATE_BOOLEAN) ? trailingslashit($opt['path_owner']).$_POST['path'] : $_POST['path']; if(wp_mkdir_p($p)){ HOACOA_FAA_Logger::log('System', "Created: $p"); wp_send_json_success(); } else wp_send_json_error(); }
}
