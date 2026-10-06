<?php
use Buchin\Bing\Image;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Offline specs: Bing is never contacted, the HTTP layer is mocked
 * and the response is a stored fixture.
 */
describe('Buchin\Bing\Image (offline)', function () {
    $mockedScraper = function (Response $response) {
        $scraper = new Image();
        $scraper->client = new Client([
            'base_uri' => 'http://www.bing.com/',
            'http_errors' => false,
            'handler' => HandlerStack::create(new MockHandler([$response])),
        ]);

        return $scraper;
    };

    describe('scrape()', function () use ($mockedScraper) {
        given('scraper', function () use ($mockedScraper) {
            return $mockedScraper(new Response(200, [], file_get_contents(__DIR__ . '/../fixtures/image.html')));
        });

        given('images', function () {
            return $this->scraper->scrape('cats');
        });

        it('drops duplicates by url, entries without description and invalid json', function () {
            expect($this->images)->toHaveLength(2);
            expect(array_column($this->images, 'title'))->toBe(['Cute cat', 'Dog']);
        });

        it('maps the raw json to the result fields', function () {
            $cat = $this->images[0];

            expect($cat['url'])->toBe('https://img.example.com/cat.jpg?w=800');
            expect($cat['mediaurl'])->toBe('https://img.example.com/cat.jpg?w=800');
            expect($cat['link'])->toBe('https://example.com/cats');
            expect($cat['thumbnail'])->toBe('https://tse1.mm.bing.net/th?id=1');
            expect($cat['desc'])->toBe('A cat on a sofa');
            expect($cat['domain'])->toBe('example.com');
        });

        it('derives filetype from the url', function () {
            expect($this->images[0]['filetype'])->toBe('jpg');
            expect($this->images[1]['filetype'])->toBe('png');
        });

        it('parses width and height from the size info', function () {
            expect($this->images[0]['width'])->toBe(800);
            expect($this->images[0]['height'])->toBe(600);
        });

        it('falls back to 0 x 0 when the size info is missing', function () {
            expect($this->images[1]['size'])->toBe('0 x 0');
            expect($this->images[1]['width'])->toBe(0);
            expect($this->images[1]['height'])->toBe(0);
        });

        it('collects related searches', function () {
            $this->images;

            expect($this->scraper->getRelated())->toBe(['kitten', 'puppy']);
            expect($this->scraper->getImages())->toBe($this->images);
        });

        it('returns false when bing does not answer with 200', function () use ($mockedScraper) {
            expect($mockedScraper(new Response(503))->scrape('cats'))->toBe(false);
        });
    });

    describe('postProcessSingleImage()', function () {
        given('scraper', function () {
            return new Image();
        });

        it('normalises jpeg and animatedgif', function () {
            $base = ['link' => 'https://example.com', 'size' => '1×1'];

            expect($this->scraper->postProcessSingleImage($base + ['url' => 'a·jpeg'])['filetype'])->toBe('jpg');
            expect($this->scraper->postProcessSingleImage($base + ['url' => 'a·animatedgif'])['filetype'])->toBe('gif');
        });

        it('takes the extension of urls without query string', function () {
            $base = ['link' => 'https://example.com', 'size' => '1×1'];

            expect($this->scraper->postProcessSingleImage($base + ['url' => 'https://x.test/a/b.webp'])['filetype'])->toBe('webp');
        });

        it('keeps the whole url when it contains no dot', function () {
            $base = ['link' => 'https://example.com', 'size' => '1×1'];

            expect($this->scraper->postProcessSingleImage($base + ['url' => 'https://localhost/image'])['filetype'])->toBe('https://localhost/image');
        });

        it('takes everything after the last dot, even if that is the host (existing behaviour)', function () {
            $base = ['link' => 'https://example.com', 'size' => '1×1'];

            expect($this->scraper->postProcessSingleImage($base + ['url' => 'https://x.test/image'])['filetype'])->toBe('test/image');
        });
    });
});
