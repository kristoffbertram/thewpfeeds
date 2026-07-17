<?php

declare(strict_types=1);

namespace TheWPFeeds\Blocks;

/**
 * Registers the thewpfeeds/feed block from the built block.json.
 * Rendering happens in blocks/feed/render.php via the same public API
 * (thewpfeeds_render) theme developers use directly.
 */
final class FeedBlock
{
    public function register(): void
    {
        $blockDir = THEWPFEEDS_DIR . 'build/feed';

        if (is_readable($blockDir . '/block.json')) {
            register_block_type($blockDir);
        }
    }
}
