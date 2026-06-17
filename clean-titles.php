<?php
/**
 * One-off maintenance: strip the "---" separator artifact from product titles.
 *   wp eval-file clean-titles.php          # DRY RUN — shows changes, writes nothing
 *   wp eval-file clean-titles.php apply     # APPLY — writes the cleaned titles
 *
 * Rule: collapse any run of 3+ dashes (with surrounding spaces) to a single
 * space, then trim.  "Naziv ---" -> "Naziv";  "UCFL 001 --- AL" -> "UCFL 001 AL".
 * Updates post_title only (slug/post_name is left untouched, so /proizvod/{slug}/
 * URLs stay stable). Clears each product's cache after writing.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "Run via WP-CLI: wp eval-file clean-titles.php [apply]\n";
	return;
}

$apply = ( isset( $args[0] ) && 'apply' === $args[0] );

global $wpdb;
$rows = $wpdb->get_results(
	"SELECT ID, post_title FROM {$wpdb->posts}
	 WHERE post_type = 'product' AND post_title LIKE '%---%'"
);

WP_CLI::log( ( $apply ? 'APPLY' : 'DRY RUN' ) . ' — ' . count( $rows ) . ' product titles contain "---"' );

$changed = 0;
foreach ( $rows as $r ) {
	$new = trim( preg_replace( '/\s*-{3,}\s*/u', ' ', $r->post_title ) );

	if ( '' === $new || $new === $r->post_title ) {
		continue; // nothing to do (or would empty the title — skip for safety)
	}
	$changed++;

	if ( $apply ) {
		$wpdb->update( $wpdb->posts, array( 'post_title' => $new ), array( 'ID' => (int) $r->ID ) );
		clean_post_cache( (int) $r->ID );
	} else {
		WP_CLI::log( "  [{$r->ID}]  {$r->post_title}   =>   {$new}" );
	}
}

if ( $apply ) {
	WP_CLI::success( "Updated {$changed} product titles." );
} else {
	WP_CLI::log( "Would update {$changed} titles. Re-run with 'apply' to write." );
}
