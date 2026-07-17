<?php

declare(strict_types=1);

namespace TheWPFeeds\Tests\Unit;

use TheWPFeeds\Provider\Rss\RssNormalizer;

final class RssNormalizerTest extends TestCase
{
    private RssNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new RssNormalizer();
    }

    public function testRss2Fixture(): void
    {
        $items = $this->normalizer->normalize(
            (string) file_get_contents(THEWPFEEDS_FIXTURES_DIR . '/rss2-sample.xml')
        );

        $this->assertCount(3, $items);

        $first = $items[0];
        $this->assertSame('rss', $first->provider);
        $this->assertSame('Shipping the cold chain dashboard', $first->title);
        $this->assertSame('https://blog.acme.example/cold-chain-dashboard', $first->url);
        $this->assertSame('2026-07-01 08:00', $first->datetime()->format('Y-m-d H:i'));
        $this->assertStringContainsString('the full story', $first->content, 'content:encoded preferred over description');
        $this->assertStringNotContainsString('<strong>', $first->content, 'HTML stripped to plain text');
        $this->assertStringContainsString('—', $first->content, 'entities decoded');
        $this->assertSame('https://blog.acme.example/img/dashboard.jpg', $first->image?->remoteUrl);
        $this->assertSame(1200, $first->image?->width);
        $this->assertSame('Acme Engineering Blog', $first->author()?->name);

        $this->assertSame('https://blog.acme.example/img/stampede.png', $items[1]->image?->remoteUrl, 'image enclosure used as fallback');
        $this->assertNull($items[2]->image);
        $this->assertStringContainsString("that’s fine", $items[2]->content);
    }

    public function testYouTubeAtomFixture(): void
    {
        $items = $this->normalizer->normalize(
            (string) file_get_contents(THEWPFEEDS_FIXTURES_DIR . '/atom-youtube.xml'),
            'youtube'
        );

        $this->assertCount(2, $items);

        $video = $items[0];
        $this->assertSame('youtube', $video->provider);
        $this->assertSame('A-500e walkaround: electric refrigeration in 4 minutes', $video->title);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcA', $video->url);
        $this->assertSame(
            'https://i.ytimg.com/vi/dQw4w9WgXcA/hqdefault.jpg',
            $video->image?->remoteUrl,
            'media:thumbnail wins; the x-shockwave-flash media:content is skipped'
        );
        $this->assertSame('2026-06-20', $video->datetime()->format('Y-m-d'), 'published preferred over updated');
        $this->assertSame('Acme Transport Refrigeration', $video->author()?->name);
    }

    public function testDistinctIdsAndStableHashing(): void
    {
        $items = $this->normalizer->normalize(
            (string) file_get_contents(THEWPFEEDS_FIXTURES_DIR . '/rss2-sample.xml')
        );

        $ids = array_map(static fn ($i) => $i->id, $items);
        $this->assertCount(3, array_unique($ids));
        $this->assertStringStartsWith('rss:', $ids[0]);
    }

    public function testInvalidXmlThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->normalizer->normalize('this is not xml');
    }

    public function testNonFeedXmlThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->normalizer->normalize('<?xml version="1.0"?><html><body>nope</body></html>');
    }
}
