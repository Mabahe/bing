<?php
use Buchin\Bing\Video;

/**
 * Offline specs: Video::getContent() uses file_get_contents() against bing,
 * so it is replaced by a stored fixture in a subclass.
 */
describe('Buchin\Bing\Video (offline)', function () {
    $scraperWith = function ($content) {
        return new class($content) extends Video {
            private $fixture;

            public function __construct($fixture)
            {
                parent::__construct();
                $this->fixture = $fixture;
            }

            public function getContent()
            {
                return $this->fixture;
            }
        };
    };

    describe('scrape()', function () use ($scraperWith) {
        given('videos', function () use ($scraperWith) {
            return $scraperWith(file_get_contents(__DIR__ . '/../fixtures/video.html'))->scrape('nasi goreng');
        });

        it('returns one entry per video and skips containers without data', function () {
            expect($this->videos)->toHaveLength(3);
        });

        it('maps title, duration and views', function () {
            expect($this->videos[0]['title'])->toBe('Nasi Goreng in 5 Minuten');
            expect($this->videos[0]['duration'])->toBe('5:12');
            expect($this->videos[0]['views'])->toBe('1,2 Mio. Aufrufe');
        });

        it('uses 0 views when the view count is missing', function () {
            expect($this->videos[1]['views'])->toBe(0);
        });

        it('builds youtube link, id and thumbnails from the v parameter', function () {
            expect($this->videos[0]['videoid'])->toBe('abc123');
            expect($this->videos[0]['link'])->toBe('https://www.youtube.com/watch?v=abc123');
            expect($this->videos[0]['thumbnail'])->toBe('https://i.ytimg.com/vi/abc123/default.jpg');
            expect($this->videos[0]['thumbnail_mq'])->toBe('https://i.ytimg.com/vi/abc123/mqdefault.jpg');
            expect($this->videos[0]['thumbnail_hq'])->toBe('https://i.ytimg.com/vi/abc123/hqdefault.jpg');
        });

        it('omits link fields when the url has no v parameter', function () {
            expect($this->videos[2])->toBe([
                'title' => 'Kein Video-Parameter',
                'duration' => '1:00',
                'views' => '12 Aufrufe',
            ]);
        });

        it('parses a real bing response (snapshot of 2026-10-06)', function () use ($scraperWith) {
            $videos = $scraperWith(file_get_contents(__DIR__ . '/../fixtures/video-live-sample.html'))->scrape('q');

            expect($videos)->toHaveLength(35);
            expect($videos[0]['title'])->toBe("The Letter Q | Alphabet A-Z | Jack Hartmann Let's Learn from A- Z");
            expect($videos[0]['duration'])->toBe('03:56');
            expect($videos[0]['videoid'])->toBe('hKdnp6NvFZg');
            expect($videos[0]['views'])->toBe('362,7Tsd. Aufrufe');
        });

        it('returns false when no content could be loaded', function () use ($scraperWith) {
            expect($scraperWith(false)->scrape('nasi goreng'))->toBe(false);
        });
    });
});
