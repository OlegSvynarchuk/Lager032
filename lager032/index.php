<?php
/**
 * Fallback template (blog/archive/single).
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="container" style="padding:60px 0">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>
				<h1><?php the_title(); ?></h1>
				<div class="entry-content"><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nema sadržaja.', 'lager032' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
