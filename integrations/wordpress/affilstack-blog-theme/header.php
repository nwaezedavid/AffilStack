<?php
/**
 * Header: the AffilStack menu, kept in sync with affilstack.com.
 */
defined('ABSPATH') || exit;

$header = (array) affilstack_kit('header', []);
$tools = (array) ($header['tools'] ?? []);
$resources = (array) ($header['resources'] ?? []);
$link = function (string $key) use ($header): array {
    $item = (array) ($header[$key] ?? []);

    return ['label' => (string) ($item['label'] ?? ''), 'url' => (string) ($item['url'] ?? '')];
};
$pricing = $link('pricing');
$expert = $link('expert');
$login = $link('login');
$cta = $link('cta');
$all = $link('all_features');
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e('Skip to content', 'affilstack'); ?></a>

<header class="afs-header">
	<div class="afs-wrap afs-header__bar">
		<div style="display:flex;align-items:center;gap:10px"><?php affilstack_brand(false, true); ?></div>

		<nav class="afs-nav" aria-label="<?php esc_attr_e('Main', 'affilstack'); ?>">
			<?php if ($tools) { ?>
				<div class="afs-nav__item afs-nav__item--tools">
					<button type="button" class="afs-nav__toggle" aria-expanded="false" aria-controls="afs-menu-tools"><?php echo esc_html((string) ($header['tools_label'] ?? 'Tools')); ?><?php affilstack_icon('chevron'); ?></button>
					<div class="afs-menu afs-menu--tools" id="afs-menu-tools">
						<?php foreach ($tools as $group) { ?>
							<div class="afs-menu__group">
								<h3><?php echo esc_html((string) ($group['label'] ?? '')); ?></h3>
								<?php foreach ((array) ($group['items'] ?? []) as $tool) { ?>
									<a href="<?php echo esc_url((string) ($tool['url'] ?? '')); ?>">
										<strong><?php echo esc_html((string) ($tool['name'] ?? '')); ?></strong>
										<?php if (! empty($tool['description'])) { ?>
											<span><?php echo esc_html((string) $tool['description']); ?></span>
										<?php } ?>
									</a>
								<?php } ?>
							</div>
						<?php } ?>
						<?php if ($all['url'] !== '') { ?>
							<div class="afs-menu__all"><a href="<?php echo esc_url($all['url']); ?>"><strong><?php echo esc_html($all['label']); ?> →</strong></a></div>
						<?php } ?>
					</div>
				</div>
			<?php } ?>

			<?php if ($resources) { ?>
				<div class="afs-nav__item">
					<button type="button" class="afs-nav__toggle" aria-expanded="false" aria-controls="afs-menu-resources"><?php echo esc_html((string) ($header['resources_label'] ?? 'Resources')); ?><?php affilstack_icon('chevron'); ?></button>
					<div class="afs-menu" id="afs-menu-resources">
						<?php foreach ($resources as $item) { ?>
							<a href="<?php echo esc_url((string) ($item['url'] ?? '')); ?>">
								<strong><?php echo esc_html((string) ($item['label'] ?? '')); ?></strong>
								<?php if (! empty($item['description'])) { ?>
									<span><?php echo esc_html((string) $item['description']); ?></span>
								<?php } ?>
							</a>
						<?php } ?>
					</div>
				</div>
			<?php } ?>

			<?php if ($pricing['url'] !== '') { ?>
				<a href="<?php echo esc_url($pricing['url']); ?>"><?php echo esc_html($pricing['label']); ?></a>
			<?php } ?>
		</nav>

		<div class="afs-header__actions">
			<?php if ($expert['url'] !== '') { ?>
				<a class="afs-link" href="<?php echo esc_url($expert['url']); ?>"><?php echo esc_html($expert['label']); ?></a>
			<?php } ?>
			<?php if ($login['url'] !== '') { ?>
				<a class="afs-link" href="<?php echo esc_url($login['url']); ?>"><?php echo esc_html($login['label']); ?></a>
			<?php } ?>
			<?php if ($cta['url'] !== '') { ?>
				<a class="afs-btn afs-btn--navy" href="<?php echo esc_url($cta['url']); ?>"><?php echo esc_html($cta['label']); ?><?php affilstack_icon('arrow'); ?></a>
			<?php } ?>
		</div>

		<button type="button" class="afs-burger" aria-expanded="false" aria-controls="afs-mobile" aria-label="<?php esc_attr_e('Open menu', 'affilstack'); ?>"><?php affilstack_icon('menu'); ?></button>
	</div>

	<div class="afs-mobile" id="afs-mobile">
		<div class="afs-mobile__inner">
			<?php if ($tools) { ?>
				<details>
					<summary><?php echo esc_html((string) ($header['tools_label'] ?? 'Tools')); ?></summary>
					<?php foreach ($tools as $group) { ?>
						<?php foreach ((array) ($group['items'] ?? []) as $tool) { ?>
							<a href="<?php echo esc_url((string) ($tool['url'] ?? '')); ?>"><?php echo esc_html((string) ($tool['name'] ?? '')); ?></a>
						<?php } ?>
					<?php } ?>
				</details>
			<?php } ?>
			<?php if ($resources) { ?>
				<details>
					<summary><?php echo esc_html((string) ($header['resources_label'] ?? 'Resources')); ?></summary>
					<?php foreach ($resources as $item) { ?>
						<a href="<?php echo esc_url((string) ($item['url'] ?? '')); ?>"><?php echo esc_html((string) ($item['label'] ?? '')); ?></a>
					<?php } ?>
				</details>
			<?php } ?>
			<div class="afs-mobile__links">
				<?php foreach ([$pricing, $expert] as $item) { ?>
					<?php if ($item['url'] !== '') { ?>
						<a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
					<?php } ?>
				<?php } ?>
			</div>
			<div class="afs-mobile__cta">
				<?php if ($login['url'] !== '') { ?>
					<a class="afs-btn afs-btn--ghost" href="<?php echo esc_url($login['url']); ?>"><?php echo esc_html($login['label']); ?></a>
				<?php } ?>
				<?php if ($cta['url'] !== '') { ?>
					<a class="afs-btn afs-btn--navy" href="<?php echo esc_url($cta['url']); ?>"><?php echo esc_html($cta['label']); ?><?php affilstack_icon('arrow'); ?></a>
				<?php } ?>
			</div>
		</div>
	</div>
</header>
<?php affilstack_newsletter_notice(); ?>
