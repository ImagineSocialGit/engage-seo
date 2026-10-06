<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Pages\FeedbackPreviewResolver;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FeedbackPreviewResolverTest extends TestCase
{
    private function resolver(array $links = []): FeedbackPreviewResolver
    {
        return new FeedbackPreviewResolver(new Repository([
            'feedback_preview' => ['page_key' => 'feedback', 'review_links' => $links],
        ]));
    }

    public function testOnlyTheConfiguredPageReceivesPreviewData(): void
    {
        $this->assertNull($this->resolver()->resolve(['key' => 'contact', 'path' => '/contact'], Request::create('/contact')));
        $empty = new FeedbackPreviewResolver(new Repository);
        $this->assertNull($empty->resolve(['key' => 'feedback', 'path' => '/feedback'], Request::create('/feedback')));
    }

    public function testQueryStatesAreExplicitAndMalformedValuesFallBack(): void
    {
        foreach (['' => 'choose', '?t=great' => 'great', '?t=not-great' => 'not-great',
            '?t=unknown' => 'choose', '?t[]=great' => 'choose', '?t=GREAT' => 'choose'] as $query => $state) {
            $data = $this->resolver()->resolve(['key' => 'feedback', 'path' => '/feedback'], Request::create('/feedback'.$query));
            $this->assertSame($state, $data['state']);
            $this->assertSame('preview', $data['submission_mode']);
            $this->assertSame('/feedback?t=great', $data['positive_url']);
            $this->assertSame('/feedback?t=not-great', $data['negative_url']);
        }
    }

    public function testClientLinksArePreservedWithoutNetworkRequests(): void
    {
        $links = [['label' => 'Example', 'url' => 'https://example.com/profile?id=12']];
        $data = $this->resolver($links)->resolve(['key' => 'feedback', 'path' => '/feedback'], Request::create('/feedback?t=great'));
        $this->assertSame($links, $data['review_links']);
    }

    public function testUnsafeReviewUrlsAreRejected(): void
    {
        foreach (['javascript:alert(1)', '//example.com', 'https://user:secret@example.com',
            "https://example.com/\n", 'https://example.com\\evil', 'http://example.com'] as $url) {
            try {
                $this->resolver([['label' => 'Example', 'url' => $url]])->resolve(
                    ['key' => 'feedback', 'path' => '/feedback'], Request::create('/feedback')
                );
                $this->fail('Unsafe review destination accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}