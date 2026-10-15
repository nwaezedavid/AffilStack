<?php
/**
 * A single post: article, call to action, newsletter and related posts.
 */
defined('ABSPATH') || exit;

get_header();

while (have_posts()) {
    the_post();
    $category = affilstack_primary_category();
    ?>
	<main id="main">
		<article <?php post_class(); ?>>
			<header class="afs-article__head">
				<nav class="afs-crumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'affilstack'); ?>">
					<a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Blog', 'affilstack'); ?></a>
					<?php if ($category) { ?>
						<span aria-hidden="true">/</span>
						<a href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a>
					<?php } ?>
				</nav>
				<?php if ($category) { ?>
					<span class="afs-eyebrow"><?php echo esc_html($category->name); ?></span>
				<?php } ?>
				<h1><?php the_title(); ?></h1>
				<div class="afs-meta">
					<span><?php the_author(); ?></span>
					<time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date()); ?></time>
					<span><?php echo esc_html(affilstack_reading_time()); ?></span>
				</div>
			</header>

			<?php if (has_post_thumbnail()) { ?>
				<div class="afs-article__media"><?php the_post_thumbnail('affilstack-hero', ['loading' => 'eager', 'fetchpriority' => 'high']); ?></div>
			<?php } ?>

			<div class="afs-content">
				<?php
                the_content();
    wp_link_pages();
    ?>
			</div>

			<?php
            $tags = get_the_tags();
    if ($tags) {
        ?>
				<div class="afs-tags">
					<?php foreach ($tags as $tag) { ?>
						<a class="afs-chip" href="<?php echo esc_url(get_tag_link($tag)); ?>">#<?php echo esc_html($tag->name); ?></a>
					<?php } ?>
				</div>
			<?php } ?>

			<div class="afs-content-wrap">
				<?php echo affilstack_cta_box(get_the_ID()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.?>
				<?php echo affilstack_newsletter_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.?>
			</div>

			<?php if (comments_open() || get_comments_number()) { ?>
				<div class="afs-comments"><?php comments_template(); ?></div>
			<?php } ?>
		</article>

		<?php
        $related = new WP_Query(
            [
                'post__not_in' => [get_the_ID()],
                'posts_per_page' => 3,
                'ignore_sticky_posts' => true,
                'cat' => $category ? $category->term_id : 0,
                'no_found_rows' => true,
            ]
        );
    if (! $related->have_posts() && $category) {
        // Nothing else in this category yet: show the latest posts instead.
        $related = new WP_Query(
            [
                'post__not_in' => [get_the_ID()],
                'posts_per_page' => 3,
                'ignore_sticky_posts' => true,
                'no_found_rows' => true,
            ]
        );
    }
    if ($related->have_posts()) {
        ?>
			<section class="afs-related" aria-labelledby="afs-related-heading">
				<div class="afs-wrap">
					<h2 id="afs-related-heading"><?php esc_html_e('Keep reading', 'affilstack'); ?></h2>
					<div class="afs-grid">
						<?php
                    while ($related->have_posts()) {
                        $related->the_post();
                        affilstack_card();
                    }
        wp_reset_postdata();
        ?>
					</div>
				</div>
			</section>
		<?php } ?>
	</main>
	<?php
}

get_footer();
