<?php
/**
 * Plugin Dashboard & Dynamic Compliance Hub
 * @package HOA/COA File Archive Assistant
 * @version 1.3.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HOACOA_FAA_Dashboard {

	/**
	 * Main render controller for the Dashboard.
	 * Splits view into Audit Gauges and Statute Archive.
	 */
	public function render(): void {
		$tab = $_GET['tab'] ?? 'audit';
		
		// Handle Statute Print View
		if ( isset( $_GET['hcaa_print'] ) ) {
			$selected_chapter = $_GET['chapter'] ?? 0;
			$this->render_print_view((int)$selected_chapter);
			exit;
		}

		// Handle Baseline Import
		if ( isset( $_POST['hcaa_import_baseline'] ) && check_admin_referer('hcaa_import_action')) {
			$this->import_baseline_archive();
		}

		?>
		<div class="wrap">
			<h1>Archive Assistant Dashboard</h1>

			<h2 class="nav-tab-wrapper">
				<a href="?page=hoacoa-faa-main&tab=audit" class="nav-tab <?php echo $tab === 'audit' ? 'nav-tab-active' : ''; ?>">System Audit & Compliance</a>
				<a href="?page=hoacoa-faa-main&tab=statutes" class="nav-tab <?php echo $tab === 'statutes' ? 'nav-tab-active' : ''; ?>">Statute Archive (History)</a>
			</h2>

			<div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
				<p style="margin:0;"><strong>Compliance Notice:</strong> <?php echo esc_html( HOACOA_FAA_Admin::DISCLAIMER ); ?></p>
			</div>

			<?php 
			if ( $tab === 'audit' ) {
				$this->render_audit_tab();
			} else {
				$this->render_statutes_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Tab 1: System Audit - The "Engine Room" Gauges
	 */
	private function render_audit_tab(): void {
		$report = get_option( 'hcaa_last_audit_report', [] );
		$last_run = get_option( 'hcaa_last_audit_time', 'Never' );
		
		// Group logic for checklist
		$groups = [];
		foreach ( $report as $file ) {
			if ( $file['status'] !== 'Valid' ) continue;
			
			$g_name = $file['group'] ?? 'General';
			if ( !isset($groups[$g_name]) ) {
				$groups[$g_name] = [
					'count' => 0,
					'latest_date' => 0,
					'latest_file' => ''
				];
			}
			
			$groups[$g_name]['count']++;
			if ( $file['timestamp'] > $groups[$g_name]['latest_date'] ) {
				$groups[$g_name]['latest_date'] = $file['timestamp'];
				$groups[$g_name]['latest_file'] = $file['name'];
			}
		}

		$mismatches = array_filter((array)$report, fn($f) => $f['status'] === 'Mismatch');
		?>
		<div style="display: grid; grid-template-columns: 1fr 300px; gap: 20px; margin-top: 20px;">
			
			<div>
				<div class="card" style="max-width: 100%; margin: 0; padding: 0;">
					<div style="padding: 15px 20px; border-bottom: 1px solid #ccd0d4; background: #f6f7f7; display: flex; justify-content: space-between; align-items: center;">
						<h2 style="margin:0;">Compliance Checklist (Grouped)</h2>
						<small>Last Scan: <?php echo esc_html($last_run); ?></small>
					</div>
					<div style="padding: 20px;">
						<?php if ( empty($groups) ) : ?>
							<p>No valid files detected. Run an audit to populate this checklist.</p>
						<?php else : ?>
							<table class="widefat striped" style="border:none;">
								<thead>
									<tr>
										<th>Reporting Group</th>
										<th>Status</th>
										<th>Newest File Detected</th>
										<th>File Count</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $groups as $name => $data ) : ?>
										<tr>
											<td><strong><?php echo esc_html($name); ?></strong></td>
											<td><span style="color:green; font-weight:bold;">✔ Compliant</span></td>
											<td>
												<code><?php echo esc_html($data['latest_file']); ?></code><br>
												<small>Dated: <?php echo date('M j, Y', $data['latest_date']); ?></small>
											</td>
											<td><?php echo (int)$data['count']; ?> Files</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div style="display: flex; flex-direction: column; gap: 20px;">
				<div class="card" style="margin:0; padding:20px; border-left:4px solid #2271b1;">
					<h3>Audit Total</h3>
					<p style="font-size:24px; font-weight:bold; margin: 10px 0;"><?php echo count($report); ?> Files</p>
					<a href="<?php echo admin_url('admin.php?page=hoacoa-faa-audit'); ?>">Deep Dive Audit Report &rarr;</a>
				</div>

				<div class="card" style="margin:0; padding:20px; border-left:4px solid #d63638;">
					<h3>Alerts</h3>
					<p style="font-size:24px; font-weight:bold; color:#d63638; margin: 10px 0;"><?php echo count($mismatches); ?> Mismatches</p>
					<small>Files in managed folders with incorrect naming formats.</small>
				</div>
				
				<button type="button" class="button button-secondary" id="hcaa-run-audit" style="height:40px;">Re-Scan Folders Now</button>
			</div>

		</div>
		<?php
	}

	/**
	 * Tab 2: Statute Archive - Historical Change Logs
	 */
	private function render_statutes_tab(): void {
		$chapters = get_terms([ 'taxonomy' => 'hcaa_statute_type', 'hide_empty' => false ]);
		$selected_chapter = $_GET['chapter'] ?? ($chapters[0]->term_id ?? 0);
		
		$versions = get_posts([
			'post_type'      => 'hcaa_statute',
			'posts_per_page' => -1,
			'tax_query'      => [[ 'taxonomy' => 'hcaa_statute_type', 'field' => 'term_id', 'terms' => $selected_chapter ]],
			'orderby'        => 'title',
			'order'          => 'DESC'
		]);

		$count = wp_count_posts('hcaa_statute')->publish;
		if ( 0 == $count ) : ?>
			<div class="notice notice-warning" style="margin: 20px 0; padding: 15px; border-left-color: #ffb900;">
				<p style="font-size:15px;"><strong>Baseline Archive Detected:</strong> No statute history found. Would you like to import the pre-configured 2017-2025 compliance logs?</p>
				<form method="post">
					<?php wp_nonce_field('hcaa_import_action'); ?>
					<button type="submit" name="hcaa_import_baseline" class="button button-primary">Import 718/720 Baseline Archive</button>
				</form>
			</div>
		<?php endif; ?>

		<div class="card" style="max-width:100%; padding:0; margin-top:20px;">
			<div style="padding:15px 20px; border-bottom:1px solid #ccd0d4; background:#f6f7f7; display:flex; justify-content:space-between; align-items:center;">
				<h2 style="margin:0;">Statute Compliance Change Log</h2>
				<div style="display:flex; gap:10px; align-items:center;">
					<a href="<?php echo admin_url('edit.php?post_type=hcaa_statute'); ?>" target="_blank" class="button">
						<span class="dashicons dashicons-edit" style="vertical-align: middle; margin-top: 4px;"></span> 
						Edit Statutes
					</a>

					<a href="<?php echo esc_url( add_query_arg( [ 'hcaa_print_log' => 1, 'hcaa_chapter' => $selected_chapter ], home_url( '/' ) ) ); ?>" target="_blank" class="button">
						<span class="dashicons dashicons-printer" style="vertical-align: middle; margin-top: 4px;"></span> 
						Print to PDF
					</a>

					<form method="get">
						<input type="hidden" name="page" value="hoacoa-faa-main">
						<input type="hidden" name="tab" value="statutes">
						<select name="chapter" onchange="this.form.submit()">
							<?php foreach ($chapters as $c) echo "<option value='{$c->term_id}' ".selected($selected_chapter,$c->term_id,false).">{$c->name}</option>"; ?>
						</select>
					</form>
				</div>
			</div>
			<div style="padding:20px; max-height: 800px; overflow-y: auto; background: #fff;">
				<?php if ( empty($versions) ) : ?>
					<p style="text-align:center; padding:40px; color:#646970;">No statute versions archived for this chapter yet.</p>
				<?php else : foreach ( $versions as $v ) : 
					$log = get_post_meta( $v->ID, '_hcaa_statute_markdown_log', true );
					if ( empty($log) ) continue;
					?>
					<div class="hcaa-log-entry" style="margin-bottom:40px; border-bottom:2px solid #f0f0f1; padding-bottom:30px;">
						<h3 style="color:#2271b1; font-size:18px;"><?php echo esc_html($v->post_title); ?></h3>
						<div class="hcaa-content-parsed" style="padding-left:15px;">
							<?php echo $this->parse_markdown_to_html($log); ?>
						</div>
					</div>
				<?php endforeach; ?>
					<div style="background:#f9f9f9; padding:20px; border:1px solid #eee; font-style:italic; color:#666; margin-top:20px;">
						<p style="margin:0;"><strong>Notice:</strong> This change log was generated using AI analysis. This document is provided for administrative summary purposes only and does not constitute legal advice. Please consult an attorney for specific legal confirmation or questions regarding statutory compliance.</p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<style>
			.hcaa-content-parsed h3 { margin-top: 25px; color: #1d2327; font-size: 17px; border-bottom: 1px solid #f0f0f1; padding-bottom: 8px; }
			.hcaa-content-parsed h3:first-child { margin-top: 0; }
			.hcaa-content-parsed strong { color: #d63638; font-weight: 600; }
			.hcaa-content-parsed ul { list-style: disc; margin-left: 20px; margin-bottom: 20px; margin-top: 10px; }
			.hcaa-content-parsed li { margin-bottom: 10px; }
			.hcaa-content-parsed p { margin-bottom: 15px; }
		</style>
		<?php
	}

	/**
	 * XML Import for Baseline Data.
	 */
	private function import_baseline_archive(): void {
		$xml_file = HOACOA_FAA_PATH . 'includes/FL718.FL720.Archive.2026-04-04.xml';
		if ( ! file_exists( $xml_file ) ) {
			echo '<div class="notice notice-error"><p>Import failed: XML file not found.</p></div>';
			return;
		}
		$xml = simplexml_load_file($xml_file, 'SimpleXMLElement', LIBXML_NOCDATA);
		$ns = $xml->getNamespaces(true);
		foreach ($xml->channel->item as $item) {
			$wp = $item->children($ns['wp']);
			$content = $item->children($ns['content']);
			$post_id = wp_insert_post([
				'post_title'   => (string) $item->title,
				'post_content' => (string) $content->encoded,
				'post_status'  => 'publish',
				'post_type'    => 'hcaa_statute'
			]);
			if ( ! is_wp_error($post_id) ) {
				foreach ($wp->postmeta as $meta) {
					if ((string)$meta->meta_key === '_hcaa_statute_markdown_log') {
						update_post_meta($post_id, '_hcaa_statute_markdown_log', (string)$meta->meta_value);
					}
				}
				foreach ($item->category as $cat) {
					if ((string)$cat['domain'] === 'hcaa_statute_type') {
						wp_set_object_terms($post_id, (string)$cat, 'hcaa_statute_type', true);
					}
				}
			}
		}
		echo '<script>window.location.reload();</script>';
	}

	/**
	 * Print View logic.
	 */
    public function render_print_view( int $chapter_id ): void {
        $term = get_term($chapter_id);
        $versions = get_posts([
            'post_type'      => 'hcaa_statute',
            'posts_per_page' => -1,
            'tax_query'      => [[ 'taxonomy' => 'hcaa_statute_type', 'field' => 'term_id', 'terms' => $chapter_id ]],
            'orderby'        => 'title',
            'order'          => 'DESC'
        ]);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Statute Change Log - ' . esc_html($term->name ?? 'Chapter') . '</title>';
        echo '<style>
            body { font-family: "Segoe UI", Tahoma, sans-serif; line-height: 1.6; color: #1a1a1a; max-width: 850px; margin: 50px auto; padding: 20px; }
            .header { border-bottom: 4px solid #2271b1; padding-bottom: 10px; margin-bottom: 40px; }
            h1 { color: #2271b1; font-size: 28px; margin: 0; }
            h2 { color: #1d2327; font-size: 22px; border-bottom: 1px solid #ddd; margin-top: 50px; padding-bottom: 5px; }
            h3 { font-size: 18px; margin-top: 25px; }
            strong { color: #d63638; }
            ul { margin-left: 25px; margin-bottom: 20px; }
            li { margin-bottom: 10px; }
            .ai-disclosure { background: #f4f4f4; border-left: 5px solid #2271b1; padding: 20px; margin: 40px 0; font-style: italic; }
            .footer { margin-top: 60px; font-size: 11px; color: #666; text-align: center; border-top: 1px solid #eee; padding-top: 20px; }
            @media print { body { margin: 0; padding: 10mm; } .no-print { display: none; } }
        </style></head><body onload="window.print()">';
        echo '<div class="header"><h1>' . esc_html($term->name ?? 'Statute') . ' Compliance Change Log</h1>';
        echo '<p>Archive Generated: ' . date('F j, Y') . '</p></div>';
        if ( ! empty($versions) ) {
            foreach ( $versions as $v ) {
                $log = get_post_meta($v->ID, '_hcaa_statute_markdown_log', true);
                if ( empty($log) ) continue;
                echo '<div class="entry"><h2>' . esc_html($v->post_title) . '</h2>';
                echo $this->parse_markdown_to_html($log);
                echo '</div>';
            }
        }
        echo '<div class="ai-disclosure"><strong>AI Analysis Disclosure:</strong> This compliance change log was generated using artificial intelligence analysis. This document is intended for administrative review and summary purposes only. It is not a substitute for professional legal counsel. Please consult a qualified attorney for specific legal confirmation or questions.</div>';
        echo '<div class="footer">' . esc_html( HOACOA_FAA_Admin::DISCLAIMER ) . '</div>';
        echo '</body></html>';
    }

	/**
	 * Simple Markdown Parser.
	 */
	private function parse_markdown_to_html( string $text ): string {
		$text = preg_replace('/### (.*)/', '<h3>$1</h3>', $text);
		$text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
		$text = preg_replace('/^\* (.*)/m', '<li>$1</li>', $text);
		$text = preg_replace('/(<li>.*<\/li>)+/s', '<ul>$0</ul>', $text);
		return wpautop($text);
	}
}
