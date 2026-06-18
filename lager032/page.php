<?php
/**
 * Page template. Cart/checkout render full-width with no page title (they bring
 * their own headings); ordinary pages keep the title.
 *
 * @package Lager032
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$is_checkout = function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() );
?>

<div class="container page-wrap<?php echo $is_checkout ? ' page-wrap--checkout' : ''; ?>">
	<?php while ( have_posts() ) : the_post(); ?>
		<?php if ( ! $is_checkout ) : ?>
			<h1 class="page-title"><?php the_title(); ?></h1>
		<?php endif; ?>
		<div class="entry-content"><?php the_content(); ?></div>
	<?php endwhile; ?>
</div>

<?php
get_footer();
