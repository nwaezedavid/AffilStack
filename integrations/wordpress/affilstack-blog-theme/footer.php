<?php
/**
 * Footer: columns, tagline and social links from affilstack.com.
 */
defined('ABSPATH') || exit;

$footer = (array) affilstack_kit('footer', []);
?>
<footer class="afs-footer">
	<div class="afs-footer__inner">
		<div class="afs-footer__top">
			<div>
				<?php affilstack_brand(true); ?>
				<?php if (! empty($footer['tagline'])) { ?>
					<p class="afs-footer__tagline"><?php echo esc_html((string) $footer['tagline']); ?></p>
				<?php } ?>
				<?php if (! empty($footer['socials'])) { ?>
					<div class="afs-socials">
						<?php foreach ((array) $footer['socials'] as $social) { ?>
							<a href="<?php echo esc_url((string) ($social['url'] ?? '')); ?>" rel="me noopener" target="_blank" aria-label="<?php echo esc_attr(ucfirst((string) ($social['network'] ?? ''))); ?>"><?php affilstack_icon((string) ($social['network'] ?? '')); ?></a>
						<?php } ?>
					</div>
				<?php } ?>
			</div>
			<div class="afs-footer__cols">
				<?php foreach ((array) ($footer['columns'] ?? []) as $column) { ?>
					<div>
						<h3><?php echo esc_html((string) ($column['heading'] ?? '')); ?></h3>
						<ul>
							<?php foreach ((array) ($column['links'] ?? []) as $item) { ?>
								<li><a href="<?php echo esc_url((string) ($item['url'] ?? '')); ?>"><?php echo esc_html((string) ($item['label'] ?? '')); ?></a></li>
							<?php } ?>
						</ul>
					</div>
				<?php } ?>
			</div>
		</div>
		<div class="afs-footer__bottom">
			<p><?php echo esc_html((string) ($footer['copyright'] ?? '')); ?></p>
			<?php if (! empty($footer['note'])) { ?>
				<p><?php echo esc_html((string) $footer['note']); ?></p>
			<?php } ?>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
