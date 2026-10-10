<?php

namespace App\Services\Seo;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

class ClassPageHtmlVerifier
{
    public function verify(string $html, array $page): void
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $roots = $xpath->query('//body//*[@data-class-guide]');
        $root = $roots->item(0);
        $this->require($roots->length === 1 && $root instanceof DOMElement
            && $root->getAttribute('data-class-guide') === $page['guide'], 'The rendered class guide is missing.');
        $articles = $xpath->query('.//*[@data-guide-article]', $root);
        $article = $articles->item(0);
        $structured = isset($page['content']);
        $this->require($articles->length === 1 && $article instanceof DOMElement
            && $article->getAttribute('data-guide-article') === $page['guide']
            && ($structured || mb_strlen(trim($article->textContent)) > 200), 'The rendered guide article is missing or empty.');
        if ($structured) {
            $expectedBlocks = collect($page['content']['blocks'] ?? [])->filter(fn (array $block): bool => $block['enabled'] !== false)
                ->map(fn (array $block): array => ['id' => $block['id'], 'type' => $block['type']])->values()->all();
            $actualBlocks = [];
            foreach ($xpath->query('.//*[@data-landing-block]', $article) as $block) {
                $actualBlocks[] = ['id' => $block->getAttribute('id'), 'type' => $block->getAttribute('data-landing-block')];
            }
            $this->require($actualBlocks === $expectedBlocks, 'The rendered landing blocks differ from the published content.');
        }
        $headings = $xpath->query('.//h1', $root);
        $this->require($headings->length === 1 && trim($headings->item(0)->textContent) === $page['seo']['h1'], 'The rendered H1 is incorrect.');
        $breadcrumbs = $page['breadcrumbs'];
        $breadcrumbNavs = $xpath->query('.//nav[@aria-label="Хлебные крошки"]', $root);
        $this->require($breadcrumbNavs->length === 1, 'The rendered catalog breadcrumbs are missing.');
        $breadcrumbLinks = $xpath->query('.//a[@href]', $breadcrumbNavs->item(0));
        $actualBreadcrumbs = [];
        foreach ($breadcrumbLinks as $link) {
            $actualBreadcrumbs[] = ['name' => trim($link->textContent), 'url' => $link->getAttribute('href')];
        }
        $this->require($actualBreadcrumbs === array_slice($breadcrumbs, 0, -1), 'The rendered breadcrumb links do not match the catalog hierarchy.');
        $currentBreadcrumbs = $xpath->query('.//*[@aria-current="page"]', $breadcrumbNavs->item(0));
        $this->require($currentBreadcrumbs->length === 1
            && trim($currentBreadcrumbs->item(0)->textContent) === end($breadcrumbs)['name'], 'The current catalog breadcrumb is incorrect.');

        $catalogs = $xpath->query('.//*[@data-class-goods]', $root);
        $catalogEnabled = ! $structured || ($page['content']['catalog']['enabled'] ?? true);
        $this->require($catalogs->length === ($catalogEnabled ? 1 : 0), 'The rendered class catalog visibility is incorrect.');
        $cards = $catalogEnabled ? $xpath->query('.//article[@data-good-id]', $catalogs->item(0)) : [];
        $expectedGoods = collect($page['goods'])->keyBy('id');
        $this->require(count($cards) === $expectedGoods->count(), 'The rendered catalog count differs from the public catalog.');
        $seen = [];
        foreach ($cards as $card) {
            $id = (int) $card->getAttribute('data-good-id');
            $good = $expectedGoods->get($id);
            $this->require($good !== null && ! isset($seen[$id]), 'The rendered catalog contains an unexpected or duplicate product.');
            $seen[$id] = true;
            $links = $xpath->query('.//a[@href]', $card);
            $this->require(collect($links)->contains(fn (DOMElement $link): bool => $link->getAttribute('href') === $good['url']), 'A rendered product link is missing or outdated.');
        }
        $this->require(array_keys($seen) === $expectedGoods->keys()->all(), 'The rendered catalog order differs from the public catalog.');
        $inlineGoods = (array) ($page['inlineGoods'] ?? []);
        $inlineIds = [];
        foreach ($xpath->query('.//a[@data-inline-good-link]', $article) as $link) {
            $id = (int) $link->getAttribute('data-good-id');
            $good = $inlineGoods[$id] ?? null;
            $this->require($good !== null && $link->getAttribute('href') === $good['url'], 'An article link is not a current public product URL.');
            $inlineIds[$id] = true;
        }
        if (! $structured) {
            foreach ($inlineGoods as $id => $good) {
                $this->require(isset($inlineIds[$id]), 'A published contextual product link is missing from the article.');
            }
        }

        $canonicals = $xpath->query('//head/link[@rel="canonical"]');
        $this->require($canonicals->length === 1 && $canonicals->item(0)->getAttribute('href') === $page['seo']['canonical'], 'The canonical URL is missing or incorrect.');
        foreach (['description', 'robots'] as $name) {
            $metas = $xpath->query('//head/meta[@name="'.$name.'"]');
            $this->require($metas->length === 1 && $metas->item(0)->getAttribute('content') === $page['seo'][$name], 'The '.$name.' metadata is missing or incorrect.');
        }
        $titles = $xpath->query('//head/title');
        $this->require($titles->length === 1 && trim($titles->item(0)->textContent) === $page['seo']['title'], 'The rendered page must have exactly one correct title.');

        $schemas = [];
        foreach ($xpath->query('//head/script[@type="application/ld+json"]') as $script) {
            $value = json_decode($script->textContent, true);
            $this->require(is_array($value), 'The rendered JSON-LD is invalid.');
            $this->collectSchemas($value, $schemas);
        }
        $collection = $schemas['CollectionPage'][0] ?? null;
        $list = $schemas['ItemList'][0] ?? null;
        $this->require(count($schemas['CollectionPage'] ?? []) === 1 && count($schemas['ItemList'] ?? []) === 1
            && count($schemas['BreadcrumbList'] ?? []) === 1, 'Rendered JSON-LD has missing or duplicate class page schemas.');
        $this->require(($collection['url'] ?? null) === $page['seo']['canonical'] && isset($schemas['BreadcrumbList']), 'CollectionPage or breadcrumbs are missing from rendered JSON-LD.');
        $this->require(($list['numberOfItems'] ?? null) === $expectedGoods->count()
            && array_column($list['itemListElement'] ?? [], 'url') === $expectedGoods->pluck('url')->values()->all(), 'Rendered ItemList does not match the visible catalog.');
        $expectedBreadcrumbSchema = array_map(fn (array $item, int $index): array => [
            '@type' => 'ListItem', 'position' => $index + 1,
            'name' => $item['name'], 'item' => $item['url'],
        ], $breadcrumbs, array_keys($breadcrumbs));
        $this->require(($schemas['BreadcrumbList'][0]['itemListElement'] ?? null) === $expectedBreadcrumbSchema,
            'Rendered BreadcrumbList does not match the catalog hierarchy.');
    }

    private function collectSchemas(array $value, array &$schemas): void
    {
        if (is_string($value['@type'] ?? null)) {
            $schemas[$value['@type']][] = $value;
        }
        foreach ($value as $child) {
            if (is_array($child)) {
                $this->collectSchemas($child, $schemas);
            }
        }
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
