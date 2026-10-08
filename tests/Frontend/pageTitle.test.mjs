import assert from 'node:assert/strict'
import { test } from 'node:test'
import { pageTitle } from '../../resources/js/utils/pageTitle.js'

test('hydration preserves complete class SEO titles and appends the brand only when absent', () => {
    for (const title of ['Скумбрия — ПИЩЕПРОМ-СЕРВЕР', 'Скумбрия — каталог и гид по выбору | Пищепром-Сервер']) {
        assert.equal(pageTitle(title), title)
        assert.equal(pageTitle(pageTitle(title)), title)
    }
    assert.equal(pageTitle('Каталог'), 'Каталог — ПИЩЕПРОМ-СЕРВЕР')
    assert.equal(pageTitle(''), 'ПИЩЕПРОМ-СЕРВЕР — маркетплейс пищевой промышленности')
})
