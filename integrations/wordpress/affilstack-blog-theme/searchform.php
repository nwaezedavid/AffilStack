<?php
/**
 * Search form.
 */
defined('ABSPATH') || exit;
?>
<form role="search" method="get" class="afs-search" action="<?php echo esc_url(home_url('/')); ?>">
	<label class="afs-sr" for="afs-s"><?php esc_html_e('Search the blog', 'affilstack'); ?></label>
	<input type="search" id="afs-s" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="<?php esc_attr_e('Search the blog…', 'affilstack'); ?>">
	<button type="submit" class="afs-btn afs-btn--navy"><?php esc_html_e('Search', 'affilstack'); ?></button>
</form>
