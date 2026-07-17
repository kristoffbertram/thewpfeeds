<?php

declare(strict_types=1);

namespace TheWPFeeds\Cache;

use TheWPFeeds\Feed\Feed;
use TheWPFeeds\Item\Item;
use TheWPFeeds\Item\ItemCollection;

/**
 * Localizes item images into uploads/thewpfeeds/{feed_id}/ — LinkedIn download
 * URLs are signed and expire within days, so hotlinking is not an option.
 * Plain files (not attachments): no media-library pollution, trivially pruned.
 */
final class ImageStore
{
    /**
     * Download each item's image and rewrite it to the local copy.
     * Failures fall back to the remote URL; the feed still renders.
     */
    public function localize(Feed $feed, ItemCollection $items): ItemCollection
    {
        $dir = $this->dir($feed);
        $kept = [];

        $localized = $items->map(function (Item $item) use ($feed, $dir, &$kept): Item {
            if ($item->image === null || $item->image->remoteUrl === '') {
                return $item;
            }

            $filename = $this->download($item->image->remoteUrl, $dir, $item->id);

            if ($filename === null) {
                return $item;
            }

            $kept[] = $filename;

            return $item->withImage(
                $item->image->withLocalUrl($this->baseUrl($feed) . '/' . $filename)
            );
        });

        $this->prune($dir, $kept);

        return $localized;
    }

    public function deleteForFeed(int $feedId): void
    {
        $upload = wp_upload_dir();
        $this->removeDir(trailingslashit($upload['basedir']) . 'thewpfeeds/' . $feedId);
    }

    private function download(string $url, string $dir, string $itemId): ?string
    {
        if (!wp_mkdir_p($dir)) {
            return null;
        }

        // Content-addressed by item id: unchanged posts skip the download entirely.
        $hash = substr(md5($itemId), 0, 16);

        foreach (['jpg', 'png', 'webp', 'gif'] as $ext) {
            if (is_readable($dir . '/' . $hash . '.' . $ext)) {
                return $hash . '.' . $ext;
            }
        }

        $response = wp_remote_get($url, ['timeout' => 15]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $type = wp_remote_retrieve_header($response, 'content-type');
        $ext = match (is_array($type) ? ($type[0] ?? '') : (string) $type) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        if ($body === '') {
            return null;
        }

        $filename = $hash . '.' . $ext;

        return file_put_contents($dir . '/' . $filename, $body) !== false ? $filename : null; // phpcs:ignore PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite -- $dir is inside wp_upload_dir(), never the plugin folder.
    }

    /** @param list<string> $keep */
    private function prune(string $dir, array $keep): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $file) {
            if ($file[0] !== '.' && !in_array($file, $keep, true)) {
                @unlink($dir . '/' . $file); // phpcs:ignore PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite,WordPress.WP.AlternativeFunctions -- pruning our own files in wp_upload_dir().
            }
        }
    }

    private function dir(Feed $feed): string
    {
        $upload = wp_upload_dir();

        return trailingslashit($upload['basedir']) . 'thewpfeeds/' . $feed->id;
    }

    private function baseUrl(Feed $feed): string
    {
        $upload = wp_upload_dir();

        return trailingslashit($upload['baseurl']) . 'thewpfeeds/' . $feed->id;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $file) {
            if ($file[0] !== '.') {
                @unlink($dir . '/' . $file); // phpcs:ignore PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite,WordPress.WP.AlternativeFunctions -- pruning our own files in wp_upload_dir().
            }
        }

        @rmdir($dir); // phpcs:ignore PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite,WordPress.WP.AlternativeFunctions -- removing our own dir in wp_upload_dir().
    }
}
