<?php namespace Buchin\Bing;

use Buchin\Bing\Bing;
use Symfony\Component\DomCrawler\Crawler;

/**
* Bing
*/
class Web extends Bing
{
	public $prefix = 'search';

	public function getContent()
	{
		$response = $this->client->request('GET', $this->prefix, [
			'query' => array_merge([
					'q' => trim($this->fullQuery),
					'format' => 'rss'
				], $this->options)
			]);

		if($response->getStatusCode() != 200){
			return false;
		}

		$body = $this->hookBefore((string)$response->getBody());
		return $body;
	}

	public function hookBefore($content)
	{
		return str_replace('pubDate>', 'pubdate>', $content);
	}

	/**
	 * Text of the first matching child node, or '' if bing left it out.
	 */
	protected function nodeText(Crawler $item, $selector)
	{
		$node = $item->filter($selector);

		return $node->count() ? $node->text() : '';
	}

	public function parseContent()
	{
		$results = [];

		$this->crawler = new Crawler($this->content);

		$crawler = $this->crawler->filterXPath('//channel/item');

		foreach ($crawler as $node) {
			$c = new Crawler($node);

			$result = [
				'title' => $this->nodeText($c, 'title'),
				'link' => $this->nodeText($c, 'link'),
				'description' => $this->nodeText($c, 'description'),
				'pubdate' => $this->nodeText($c, 'pubdate'),
			];

			$results[] = $result;
		}

		return $results;
	}
}