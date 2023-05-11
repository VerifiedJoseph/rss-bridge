<?php

class GitHubReleasesBridge extends BridgeAbstract
{
    const MAINTAINER = 'verifiedjoseph';
    const NAME = 'Github Releases';
    const URI = 'https://github.com/';
    const CACHE_TIMEOUT = 3600;
    const DESCRIPTION = 'Returns releases for a repository.';
    const PARAMETERS = [[
        'user' => [
            'type' => 'text',
            'title' => 'Username of account the repository belongs to.',
            'required' => true,
            'exampleValue' => 'RSS-Bridge',
            'name' => 'Username'
        ],
        'repo' => [
            'type' => 'text',
            'required' => true,
            'exampleValue' => 'rss-bridge',
            'name' => 'Repository'
        ],
        'preReleases' => [
            'title' => 'Include pre releases.',
            'name' => 'Pre releases',
            'type' => 'checkbox'
        ]
    ]];

    private string $apiVersion = '2022-11-28';

    public function collectData()
    {
        $json = getContents($this->getApiUrl(), ['X-GitHub-Api-Version:' . $this->apiVersion]);
        $data = json_decode($json);

        foreach($data as $release) {
            if ($this->getInput('preReleases') === false && $release->prerelease === true) {
                continue;
            }

            $item = [];
            $item['title'] = $release->name;
            $item['author'] = $release->author->login;
            $item['uri'] = $release->html_url;
            $item['timestamp'] = $release->published_at;
            $item['content'] = $this->createContent($release);

            if ($release->prerelease === true) {
                $item['categories'][] = 'Pre release';
            }

            $this->items[] = $item;
        }
    }

    public function getName()
    {
        if (is_null($this->getInput('user')) === false && is_null($this->getInput('repo')) === false) {
            return $this->getInput('user') . '/' . $this->getInput('repo') . ' - Github Releases';
        }

        return parent::getName();
    }

    private function getApiUrl()
    {
        return 'https://api.github.com/repos/'. $this->getInput('user') .'/'. $this->getInput('repo') .'/releases?per_page=10';
    }

    private function createContent($release)
    {
        $body = markdownToHtml($release->body);

        $assetHtml = '<p><strong>Assets</strong></p>';
        foreach ($release->assets as $asset) {
            $size = format_bytes($asset->size);

            $assetHtml .= <<<HTML
                <p><a href="{$asset->browser_download_url}">{$asset->name} ({$size})</a></p>
HTML;
        }

        $assetHtml .= <<<HTML
            <p><a href="{$release->zipball_url}">Source code (zip)</a></p>
            <p><a href="{$release->tarball_url}">Source code (tar.gz)</a></p>
HTML;

        return $body . $assetHtml;
    }
}
