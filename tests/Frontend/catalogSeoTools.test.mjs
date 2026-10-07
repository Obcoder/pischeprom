import assert from 'node:assert/strict'
import { test } from 'node:test'
import { normalizeSemanticCoreRows, parseSemanticCoreTable, semanticCorePhrases } from '../../resources/js/Components/Catalog/semanticCore.js'
import { buildGoodSeoPrompt } from '../../resources/js/Components/Catalog/seoPrompt.js'

test('ChatGPT Markdown imports groups and all product-specific punctuation without headers or prose', () => {
    const parsed = parseSemanticCoreTable('Готовое ядро:\n```markdown\n| Группа | Поисковая фраза |\n|:---|---:|\n| Основная | путассу неразделанная 23+ |\n| Профессиональное сокращение | путассу н/р 23+ |\n| **Цена** | путассу цена за кг |\n```\nКомментарий после таблицы.')
    assert.deepEqual(parsed, { rows: [
        { group: 'Основная', phrase: 'путассу неразделанная 23+' },
        { group: 'Профессиональное сокращение', phrase: 'путассу н/р 23+' },
        { group: 'Цена', phrase: 'путассу цена за кг' },
    ], errors: [], duplicates: 0 })
})

test('Excel TSV preserves a blank group; Markdown supports escaped pipes and omitted outer pipes', () => {
    assert.deepEqual(parseSemanticCoreTable('\uFEFFГруппа\tПоисковая фраза\r\n\tпутассу 15 кг\r\nЦена\tпутассу оптом').rows,
        [{ group: '', phrase: 'путассу 15 кг' }, { group: 'Цена', phrase: 'путассу оптом' }])
    assert.deepEqual(parseSemanticCoreTable('Группа | Поисковая фраза\n--- | ---\nВид \\| сорт | рыба \\| филе').rows,
        [{ group: 'Вид | сорт', phrase: 'рыба | филе' }])
})

test('duplicate rows are counted while same phrase in different groups is retained; advertising receives phrases only', () => {
    const parsed = parseSemanticCoreTable('| Основная | рыба оптом |\n| основная | РЫБА  оптом |\n| Опт | рыба оптом |')
    assert.equal(parsed.duplicates, 1)
    assert.equal(parsed.rows.length, 2)
    assert.deepEqual(semanticCorePhrases(parsed.rows), ['рыба оптом'])
    assert.deepEqual(normalizeSemanticCoreRows([' рыба ', null, { group: 'Цена', phrase: '' }]), [{ group: '', phrase: 'рыба' }])
})

test('malformed and oversized imports surface errors instead of silently dropping data', () => {
    for (const input of ['просто абзац', '| Цена | |', '| Цена | рыба | лишняя колонка |', `| ${'г'.repeat(256)} | рыба |`, `| Цена | ${'р'.repeat(1001)} |`, 'x'.repeat(500001), Array.from({ length: 2001 }, (_, i) => `| Группа | рыба ${i} |`).join('\n')]) {
        assert.ok(parseSemanticCoreTable(input).errors.length, input.slice(0, 50))
    }
    const partial = parseSemanticCoreTable('| Опт | рыба |\n| Цена | |')
    assert.equal(partial.rows.length, 1)
    assert.match(partial.errors[0], /Строка 2/)
})

test('import treats embedded markup as inert text', () => {
    assert.equal(parseSemanticCoreTable('| Вид | <img src=x onerror=alert(1)> |').rows[0].phrase, '<img src=x onerror=alert(1)>')
})

test('pasting a full ChatGPT answer imports only its semantic table', () => {
    const parsed = parseSemanticCoreTable('| Поле | Значение |\n|---|---|\n| h1 | Филе рыбы |\n\nСемантическое ядро:\n| Группа | Поисковая фраза |\n|---|---|\n| Опт | рыба оптом |\n\nFAQ:\n| Вопрос | Ответ |\n|---|---|\n| Как купить? | По запросу. |')
    assert.deepEqual(parsed, { rows: [{ group: 'Опт', phrase: 'рыба оптом' }], errors: [], duplicates: 0 })
    assert.ok(parseSemanticCoreTable('| Поле | Значение |\n|---|---|\n| h1 | Рыба |').errors.length)
})

test('quoted Excel cells decode safely and multiline cells cannot be truncated silently', () => {
    assert.ok(parseSemanticCoreTable('Группа\tПоисковая фраза\nОсновная\t"форель\nоптом"').errors.length)
    assert.deepEqual(parseSemanticCoreTable('Основная\t"форель ""Радужная"" оптом"').rows,
        [{ group: 'Основная', phrase: 'форель "Радужная" оптом' }])
})

test('ChatGPT assignment includes draft facts, all SEO deliverables and paste-compatible table instructions without internal data', () => {
    const prompt = buildGoodSeoPrompt({ good: { name: 'Путассу н/р 23+', description: '<p>Блочная заморозка</p>', country: { name: 'Фарерские острова' }, denominator: 15,
        products: [{ rus: 'Путассу' }], seo_properties: [{ name: 'Фасовка', value: '15 кг' }], api_token: 'secret-token', quotations: [{ cost: 234.56 }], counts: { purchases: 80 } },
    form: { h1: 'Черновой заголовок', min_order: 'Одна упаковка', semantic_core_rows: [{ group: 'Основная', phrase: 'путассу оптом' }], keywords_text: 'рыба\nпутассу', faq_text: 'Фасовка? | 15 кг' }, publicUrl: 'https://example.test/g/putassu' })
    for (const value of ['Путассу н/р 23+', 'Фарерские острова', 'Блочная заморозка', '15 кг', 'Черновой заголовок', 'Одна упаковка', '| Группа | Поисковая фраза |', 'https://example.test/g/putassu', 'canonical_url', 'yandex_direct_text', 'Не выдумывай']) assert.ok(prompt.includes(value), value)
    for (const value of ['secret-token', '234.56', 'quotations', 'purchases', '<p>']) assert.equal(prompt.includes(value), false, value)
})
