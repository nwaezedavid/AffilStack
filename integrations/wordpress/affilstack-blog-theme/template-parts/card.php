<?php
/**
 * A post card for the blog home, archives and related posts.
 */
defined('ABSPATH') || exit;

$featured = ! empty($args['featured']);
$category = affilstack_primary_category();
?>
<article <?php post_class('afs-card'.($featured ? ' afs-card--featured' : '')); ?>>
	<div class="afs-card__media<?php echo has_post_thumbnail() ? '' : ' afs-card__media--empty'; ?>">
		<?php
        if (has_post_thumbnail()) {
            the_post_thumbnail($featured ? 'affilstack-hero' : 'affilstack-card', ['loading' => $featured ? 'eager' : 'lazy', 'alt' => '']);
        } else {
            echo esc_html((string) affilstack_kit('site.name', 'AffilStack'));
        }
?>
	</div>
	<div class="afs-card__body">
		<?php if ($category) { ?>
			<span class="afs-eyebrow"><?php echo esc_html($category->name); ?></span>
		<?php } ?>
		<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p><?php echo esc_html(wp_strip_all_tags(get_the_excerpt())); ?></p>
		<div class="afs-meta">
			<time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date()); ?></time>
			<span><?php echo esc_html(affilstack_reading_time()); ?></span>
		</div>
	</div>
</article>
