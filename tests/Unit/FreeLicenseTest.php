<?php

declare(strict_types=1);

namespace TheWPFeeds\Tests\Unit;

use TheWPFeeds\License\FreeLicense;

final class FreeLicenseTest extends TestCase
{
    public function testFreeTierAllowsExactlyOneFeed(): void
    {
        $license = new FreeLicense();

        $this->assertFalse($license->isPro());
        $this->assertSame(1, $license->maxFeeds());
        $this->assertTrue($license->canCreateFeed(0));
        $this->assertFalse($license->canCreateFeed(1));
        $this->assertFalse($license->canCreateFeed(5));
    }
}
