<?php
/**
 * The blog home and the fallback for every list (categories, tags,
 * authors, search).
 */
defined('ABSPATH') || exit;

get_header();

$is_home = is_home() && ! is_paged();
$title = get_bloginfo('name');
$subtitle = get_bloginfo('description');

if (is_category() || is_tag() || is_tax()) {
    $title = single_term_title('', false);
    $subtitle = wp_strip_all_tags(term_description());
} elseif (is_author()) {
    $title = get_the_author_meta('display_name', (int) get_query_var('author'));
    $subtitle = get_the_author_meta('description', (int) get_query_var('author'));
} elseif (is_search()) {
    /* translators: %s: search terms */
    $title = sprintf(__('Results for “%s”', 'affilstack'), get_search_query());
    $subtitle = '';
} elseif (is_date()) {
    $title = get_the_archive_title();
}

$categories = get_categories(['orderby' => 'count', 'order' => 'DESC', 'number' => 8, 'hide_empty' => true, 'exclude' => [(int) get_option('default_category')]]);
?>
<main id="main">
	<section class="afs-hero">
		<div class="afs-hero__inner">
			<span class="afs-eyebrow"><?php echo esc_html(is_home() ? __('The blog', 'affilstack') : __('Articles', 'affilstack')); ?></span>
			<h1><?php echo esc_html($title); ?></h1>
			<?php if (trim((string) $subtitle) !== '') { ?>
				<p><?php echo esc_html((string) $subtitle); ?></p>
			<?php } ?>
			<?php if (is_search()) { ?>
				<?php get_search_form(); ?>
			<?php } elseif ($categories) { ?>
				<nav class="afs-chips" aria-label="<?php esc_attr_e('Categories', 'affilstack'); ?>">
					<a class="afs-chip<?php echo is_home() ? ' is-current' : ''; ?>" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('All', 'affilstack'); ?></a>
					<?php foreach ($categories as $category) { ?>
						<a class="afs-chip<?php echo is_category($category->term_id) ? ' is-current' : ''; ?>" href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a>
					<?php } ?>
				</nav>
			<?php } ?>
		</div>
	</section>

	<div class="afs-wrap">
		<?php if (have_posts()) { ?>
			<div class="afs-grid">
				<?php
                $first = true;
		    while (have_posts()) {
		        the_post();
		        affilstack_card($is_home && $first);
		        $first = false;
		    }
		    ?>
			</div>
			<nav class="afs-pagination" aria-label="<?php esc_attr_e('More posts', 'affilstack'); ?>">
				<?php echo wp_kses_post(paginate_links(['prev_text' => '←', 'next_text' => '→'])); ?>
			</nav>
		<?php } else { ?>
			<div class="afs-empty">
				<p><?php echo esc_html(is_search() ? __('Nothing matched that search. Try different words.', 'affilstack') : __('No posts yet — check back soon.', 'affilstack')); ?></p>
			</div>
		<?php } ?>
	</div>

	<?php if ($is_home) { ?>
		<div class="afs-content-wrap" style="padding-bottom:64px"><?php echo affilstack_newsletter_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.?></div>
	<?php } ?>
</main>
<?php
get_footer();
