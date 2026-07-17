<?php
/**
 * YouTube item — video-shaped card: big thumbnail with play affordance,
 * linking to the video. Ships as the provider default so YouTube feeds look
 * right out of the box; part of the item hierarchy:
 *
 *   item-{feed-slug}.php → item-{provider}.php → item.php
 *
 * Override: copy to {your-theme}/thewpfeeds/item-youtube.php
 * (No iframe embed by default — thumbnails keep pages fast and avoid
 * third-party requests/consent until the visitor clicks through.)
 *
 * Available: $item (\TheWPFeeds\Item\Item), $feed (\TheWPFeeds\Feed\Feed).
 *
 * @package TheWPFeeds\Templates
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<article class="thewpfeeds__item thewpfeeds__item--video">
    <a class="thewpfeeds__item-media thewpfeeds__item-media--video" href="<?php echo esc_url($item->url()); ?>" target="_blank" rel="noopener noreferrer"
       aria-label="<?php echo esc_attr(sprintf(/* translators: %s: video title */ __('Watch “%s” on YouTube', 'thewpfeeds'), $item->title(''))); ?>">
        <?php echo $item->imageTag(['class' => 'thewpfeeds__item-image']); // phpcs:ignore WordPress.Security.EscapeOutput -- imageTag() escapes internally. ?>
        <span class="thewpfeeds__item-play" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="48" height="48"><circle cx="12" cy="12" r="11" fill="rgba(0,0,0,.65)"/><path d="M10 8l6 4-6 4z" fill="#fff"/></svg>
        </span>
    </a>

    <div class="thewpfeeds__item-body">
        <time class="thewpfeeds__item-date" datetime="<?php echo esc_attr($item->datetime()->format('c')); ?>">
            <?php echo esc_html($item->date()); ?>
        </time>

        <?php if ($item->title() !== null) : ?>
            <h3 class="thewpfeeds__item-title">
                <a href="<?php echo esc_url($item->url()); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html($item->title()); ?>
                </a>
            </h3>
        <?php endif; ?>

        <?php if ($item->excerpt(25) !== '') : ?>
            <p class="thewpfeeds__item-excerpt"><?php echo esc_html($item->excerpt(25)); ?></p>
        <?php endif; ?>
    </div>
</article>
