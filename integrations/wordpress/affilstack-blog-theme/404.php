<?php
/**
 * Not found.
 */
defined('ABSPATH') || exit;

get_header();
?>
<main id="main">
	<section class="afs-hero">
		<div class="afs-hero__inner">
			<span class="afs-eyebrow">404</span>
			<h1><?php esc_html_e('We couldn’t find that page', 'affilstack'); ?></h1>
			<p><?php esc_html_e('It may have moved. Try a search, or head back to the latest posts.', 'affilstack'); ?></p>
			<?php get_search_form(); ?>
			<p style="margin-top:22px"><a class="afs-btn afs-btn--navy" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Latest posts', 'affilstack'); ?><?php affilstack_icon('arrow'); ?></a></p>
		</div>
	</section>
	<div style="height:64px"></div>
</main>
<?php
get_footer();
