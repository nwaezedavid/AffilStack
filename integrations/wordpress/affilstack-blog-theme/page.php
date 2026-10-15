<?php
/**
 * A static page.
 */
defined('ABSPATH') || exit;

get_header();

while (have_posts()) {
    the_post();
    ?>
	<main id="main">
		<header class="afs-article__head"><h1><?php the_title(); ?></h1></header>
		<div class="afs-content"><?php the_content(); ?></div>
		<div style="height:64px"></div>
	</main>
	<?php
}

get_footer();
