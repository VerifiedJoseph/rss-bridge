<?php
class NordbayernBridge extends FeedExpander {

	const MAINTAINER = 'schabi.org';
	const NAME = 'Nordbayern Bridge';
	const CACHE_TIMEOUT = 3600;
	const URI = 'https://www.nordbayern.de';
	const DESCRIPTION = 'Bridge for Bavarian reginoal news site nordbayern.de';
	const PARAMETERS = array( array(
		'region' => array(
			'name' => 'region',
			'type' => 'list',
			'exampleValue' => 'Nürnberg',
			'title' => 'Select a region',
			'values' => array(
				'Nürnberg' => 'nuernberg',
				'Fürth' => 'fuerth',
				'Altdorf' => 'altdorf',
				'Ansbach' => 'ansbach',
				'Bad Windsheim' => 'bad-windsheim',
				'Bamberg' => 'bamberg',
				'Dinkelsbühl/Feuchtwangen' => 'dinkelsbuehl-feuchtwangen',
				'Feucht' => 'feucht',
				'Forchheim' => 'forchheim',
				'Gunzenhausen' => 'gunzenhausen',
				'Hersbruck' => 'hersbruck',
				'Herzogenaurach' => 'herzogenaurach',
				'Hilpolstein' => 'hilpoltstein',
				'Höchstadt' => 'hoechstadt',
				'Lauf' => 'lauf',
				'Neumarkt' => 'neumarkt',
				'Neustadt/Aisch' => 'neustadt-aisch',
				'Pegnitz' => 'pegnitz',
				'Roth' => 'roth',
				'Rothenburg o.d.T.' => 'rothenburg-o-d-t',
				'Schwabach' => 'schwabach',
				'Treuchtlingen' => 'treuchtlingen',
				'Weißenburg' => 'weissenburg'
			)
		)
	));

	private $feedUrls = array(
		'nuernberg' => '/cmlink/15.423?cid=2.282',
		'fuerth' => '/cmlink/15.423?cid=2.232',
		'altdorf' => '/cmlink/15.423?cid=2.220',
		'ansbach' => '/cmlink/15.423?cid=2.228',
		'bad-windsheim' => '/cmlink/15.423?cid=2.177',
		'bamberg' => '/cmlink/15.423?cid=2.6920',
		'dinkelsbuehl-feuchtwangen' => '/cmlink/15.423?cid=2.5987',
		'feucht' => '/cmlink/15.423?cid=2.191',
		'forchheim' => '/cmlink/15.423?cid=2.223',
		'gunzenhausen' => '/cmlink/15.423?cid=2.247',
		'hersbruck' => '/cmlink/15.423?cid=2.173',
		'herzogenaurach' => '/cmlink/15.423?cid=2.272',
		'hilpoltstein' => '/cmlink/15.423?cid=2.210',
		'hoechstadt' => '/cmlink/15.423?cid=2.280',
		'lauf' => '/cmlink/15.423?cid=2.195',
		'neumarkt' => '/cmlink/15.423?cid=2.264',
		'neustadt-aisch' => '/cmlink/15.423?cid=2.231',
		'pegnitz' => '/cmlink/15.423?cid=2.248',
		'roth' => '/cmlink/15.423?cid=2.251',
		'rothenburg-o-d-t' => '/cmlink/15.423?cid=2.253',
		'schwabach' => '/cmlink/15.423?cid=2.216',
		'treuchtlingen' => '/cmlink/15.423?cid=2.266',
		'weissenburg' => '/cmlink/15.423?cid=2.193'
	);

	protected function parseItem($item) {
		$item = parent::parseItem($item);
		$item['content'] = '';

		$article = getSimpleHTMLDOMCached($item['uri'])
			or returnServerError('Could not request: ' . $item['uri']);

		$article = defaultLinkTo($article, self::URI);

		// Skip items that are not articles
		if (!$article->find('div.article-content.grid_40', 0)) {
			return array();
		}

		$content = $article->find('div.article-content.grid_40', 0);
		$articleContent = $content;

		// Use image from enclosure as article header
		if (isset($item['enclosures']) && !empty($item['enclosures'])) {
			$item['content'] = '<img src="' . $item['enclosures'][0] . '">';
		}

		// Strip tags that are not needed
		$articleContent = $this->stripTags($articleContent);

		$item['content'] .= $articleContent->innertext;

		// Get author
		if ($content->find('p.autor span.disBlock', 0)) {
			$item['author'] = $content->find('p.autor span.disBlock', 0)->plaintext;
		}

		// Get image slideshow
		$item['content'] .= $this->getImageSlideshow($content);

		// Get categories
		$categories = explode(',', $article->find('meta[name="Keywords"]', 0)->content);
		$item['categories'] = array_map('trim', $categories);

		return $item;
	}

	public function collectData() {
		$this->collectExpandableDatas($this->getFeedUrl(), 10);
	}

	public function getURI() {

		if (!is_null($this->getInput('region'))) {
			return self::URI . '/region/' . $this->getInput('region');
		}

		return parent::getURI();
	}

	public function getName() {

		if (!is_null($this->getInput('region'))) {
			$parameters = $this->getParameters();
			$regionValues = array_flip($parameters[0]['region']['values']);

			return $regionValues[$this->getInput('region')] . ' - Nordbayern';
		}

		return parent::getName();
	}

	private function getFeedUrl() {
		return self::URI . $this->feedUrls[$this->getInput('region')];
	}

	private function getImageSlideshow($content) {

		$images = '';
		foreach($content->find('div[class*=article-slideshow]') as $slides) {
			$images .= $slides->find('p', 0)->outertext;

			foreach ($slides->find('a') as $a) {

				if (!$a->find('script', 0)) {
					continue;
				}

				$url = $this->getImageUrlFromScript($a->find('script', 0));
				$images .= '<img src="' . $url . '">';
			}

			$images .= '<p><strong>' . $slides->find('h5', 0)->plaintext . '</strong></p>';
			$images .= $slides->find('p', 1)->outertext;
		}

		return $images;
	}

	private function getImageUrlFromScript($script) {

		preg_match("/src='([A-Za-z:\/.0-9?=%&$]*)/", trim($script->innertext), $matches, PREG_OFFSET_CAPTURE);

		if(isset($matches[1][0])) {
			$url = parse_url($matches[1][0]);
			return 'https://' . $url['host'] . $url['path'];
		}

		return null;
	}

	private function stripTags($articleContent) {

		$articleContent->find('h1', 0)->outertext = '';

		foreach ($articleContent->find('figure') as $index => $figure) {
			$articleContent->find('figure', $index)->outertext = '';
		}

		foreach ($articleContent->find('comment') as $index => $comment) {
			$articleContent->find('comment', $index)->outertext = '';
		}

		foreach ($articleContent->find('script') as $index => $script) {
			$articleContent->find('script', $index)->outertext = '';
		}

		foreach ($articleContent->find('video') as $index => $video) {
			$articleContent->find('video', $index)->outertext = '';
		}

		foreach ($articleContent->find('div.article-slideshow') as $index => $div) {
			$articleContent->find('div.article-slideshow', $index)->outertext = '';
		}

		foreach ($articleContent->find('div.nbZoombild') as $index => $div) {
			$articleContent->find('div.nbZoombild', $index)->outertext = '';
		}

		$articleContent->find('div.artbran.left.w100', 0)->outertext = '';
		$articleContent->find('div[id=toolbar-icons-small]', 0)->outertext = '';

		if ($articleContent->find('div[id=nbPostKommentar]', 0)) {
			$articleContent->find('div[id=nbPostKommentar]', 0)->outertext = '';
		}

		if ($articleContent->find('div[class*=linksZumThema]', 0)) {
			$articleContent->find('div[class*=linksZumThema]', 0)->outertext = '';
		}

		$articleContent->find('div.weitereNews', 0)->outertext = '';

		return $articleContent;
	}
}
