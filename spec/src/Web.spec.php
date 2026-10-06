<?php
use Buchin\Bing\Web;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Offline specs: Bing is never contacted, the HTTP layer is mocked
 * and the response is a stored fixture.
 */
describe('Buchin\Bing\Web (offline)', function () {
    $mockedScraper = function (Response $response) {
        $scraper = new Web();
        $scraper->client = new Client([
            'base_uri' => 'http://www.bing.com/',
            'http_errors' => false,
            'handler' => HandlerStack::create(new MockHandler([$response])),
        ]);

        return $scraper;
    };

    describe('scrape()', function () use ($mockedScraper) {
        it('combines query and hack', function () use ($mockedScraper) {
            $scraper = $mockedScraper(new Response(200, [], file_get_contents(__DIR__ . '/../fixtures/web.rss.xml')));

            $scraper->scrape('makan nasi', 'filetype:pdf');

            expect($scraper->fullQuery)->toBe('makan nasi filetype:pdf');
        });

        it('parses rss items into title, link, description and pubdate', function () use ($mockedScraper) {
            $scraper = $mockedScraper(new Response(200, [], file_get_contents(__DIR__ . '/../fixtures/web.rss.xml')));

            $results = $scraper->scrape('makan nasi');

            expect($results)->toHaveLength(3);
            expect($results[0])->toBe([
                'title' => 'Nasi Goreng Rezept',
                'link' => 'https://example.com/nasi-goreng',
                'description' => 'So gelingt Nasi Goreng.',
                'pubdate' => 'Mon, 05 Oct 2026 10:00:00 GMT',
            ]);
            expect($results[1]['link'])->toBe('https://example.org/nasi-uduk');
        });

        it('returns empty strings for fields that bing left out of an item', function () use ($mockedScraper) {
            $scraper = $mockedScraper(new Response(200, [], file_get_contents(__DIR__ . '/../fixtures/web.rss.xml')));

            $results = $scraper->scrape('makan nasi');

            expect($results[2])->toBe([
                'title' => 'Nasi Campur',
                'link' => 'https://example.net/nasi-campur',
                'description' => '',
                'pubdate' => '',
            ]);
        });

        it('returns an empty array for a feed without items', function () use ($mockedScraper) {
            $scraper = $mockedScraper(new Response(200, [], '<?xml version="1.0"?><rss><channel></channel></rss>'));

            expect($scraper->scrape('nothing'))->toBe([]);
        });

        it('returns false when bing does not answer with 200', function () use ($mockedScraper) {
            $scraper = $mockedScraper(new Response(503));

            expect($scraper->scrape('makan nasi'))->toBe(false);
        });
    });
});
