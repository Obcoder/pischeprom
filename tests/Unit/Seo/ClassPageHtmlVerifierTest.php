<?php

namespace Tests\Unit\Seo;

use App\Services\Seo\ClassPageHtmlVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ClassPageHtmlVerifierTest extends TestCase
{
    public function test_actual_rendered_article_and_product_links_pass(): void
    {
        (new ClassPageHtmlVerifier)->verify($this->html(), $this->page());
        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('invalidHtml')]
    public function test_serialized_props_and_incorrect_rendered_catalogs_do_not_pass(string $change): void
    {
        $html = $this->html();
        $html = match ($change) {
            'only-props' => '<html><head></head><body><div data-page="'.htmlspecialchars(json_encode($this->page())).'"></div></body></html>',
            'old-link' => str_replace('href="https://example.test/g/current"', 'href="https://example.test/g/old"', $html),
            'duplicate' => str_replace('</section>', '<article data-good-id="75"><a href="https://example.test/g/current">Duplicate</a></article></section>', $html),
            'noindex' => str_replace('content="index,follow"', 'content="noindex,nofollow"', $html),
            'wrong-canonical' => str_replace('href="https://example.test/catalog/ryba/skumbriia"', 'href="https://prototype.test/"', $html),
            'missing-article' => str_replace('data-guide-article=', 'data-other-article=', $html),
            'wrong-schema' => str_replace('"numberOfItems":1', '"numberOfItems":0', $html),
            'duplicate-title' => str_replace('<head>', '<head><title>Default title</title>', $html),
            'wrong-breadcrumb-link' => str_replace('href="https://example.test/catalog/ryba"', 'href="https://example.test/category/fish"', $html),
            'wrong-breadcrumb-schema' => str_replace('"position":2', '"position":9', $html),
        };
        $this->expectException(RuntimeException::class);
        (new ClassPageHtmlVerifier)->verify($html, $this->page());
    }

    public static function invalidHtml(): array
    {
        return array_map(fn ($case) => [$case], ['only-props', 'old-link', 'duplicate', 'noindex', 'wrong-canonical', 'missing-article', 'wrong-schema', 'duplicate-title', 'wrong-breadcrumb-link', 'wrong-breadcrumb-schema']);
    }

    private function page(): array
    {
        return [
            'guide' => 'mackerel',
            'goods' => [['id' => 75, 'url' => 'https://example.test/g/current']],
            'inlineGoods' => [75 => ['url' => 'https://example.test/g/current']],
            'breadcrumbs' => [
                ['name' => 'Главная', 'url' => 'https://example.test'],
                ['name' => 'Рыба', 'url' => 'https://example.test/catalog/ryba'],
                ['name' => 'Скумбрия', 'url' => 'https://example.test/catalog/ryba/skumbriia'],
            ],
            'seo' => ['h1' => 'Скумбрия', 'title' => 'Скумбрия: каталог', 'description' => 'Гид и каталог',
                'canonical' => 'https://example.test/catalog/ryba/skumbriia', 'robots' => 'index,follow'],
        ];
    }

    private function html(): string
    {
        $schema = json_encode([
            ['@type' => 'CollectionPage', 'url' => 'https://example.test/catalog/ryba/skumbriia', 'mainEntity' => [
                '@type' => 'ItemList', 'numberOfItems' => 1, 'itemListElement' => [['url' => 'https://example.test/g/current']],
            ]],
            ['@type' => 'BreadcrumbList', 'itemListElement' => array_map(fn ($item, $index) => [
                '@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['name'], 'item' => $item['url'],
            ], $this->page()['breadcrumbs'], array_keys($this->page()['breadcrumbs']))],
        ]);

        return '<html><head><title>Скумбрия: каталог</title><link rel="canonical" href="https://example.test/catalog/ryba/skumbriia">'
            .'<meta name="description" content="Гид и каталог"><meta name="robots" content="index,follow">'
            .'<script type="application/ld+json">'.$schema.'</script></head><body><div data-class-guide="mackerel"><h1>Скумбрия</h1>'
            .'<nav aria-label="Хлебные крошки"><a href="https://example.test">Главная</a><a href="https://example.test/catalog/ryba">Рыба</a><span aria-current="page">Скумбрия</span></nav>'
            .'<article data-guide-article="mackerel">'.str_repeat('Справочник по выбору скумбрии. ', 12)
            .'<a data-inline-good-link data-good-id="75" href="https://example.test/g/current">Товар</a></article>'
            .'<section data-class-goods><article data-good-id="75"><a href="https://example.test/g/current">Товар</a></article></section>'
            .'</div></body></html>';
    }
}
