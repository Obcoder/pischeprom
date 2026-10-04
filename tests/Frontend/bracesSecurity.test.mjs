import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { test } from 'node:test'
import { runInNewContext } from 'node:vm'

const require = createRequire(import.meta.url)
const micromatch = require('micromatch')
const chokidar = require('chokidar')
const installedCopies = [
    ['application', require('braces')],
    ['micromatch', createRequire(require.resolve('micromatch'))('braces')],
    ['chokidar', createRequire(require.resolve('chokidar'))('braces')],
]
const methods = ['parse', 'compile', 'expand', 'stringify']

// A vulnerable recursive walker or cyclic parent chain must fail this test,
// rather than hang the CI process. Native stack overflows are not a valid guard.
function bounded(callback) {
    return runInNewContext('invoke()', { invoke: callback }, { timeout: 500 })
}

function rejectsDepth(callback) {
    assert.throws(() => bounded(callback), error =>
        (error instanceof SyntaxError || error instanceof RangeError)
        && /exceeds max depth/.test(error.message))
}

function nested(kind, depth) {
    const [open, close, value] = kind === 'braces' ? ['{', '}', 'a,b'] : ['(', ')', 'a']
    return open.repeat(depth) + value + close.repeat(depth)
}

function astAtDepth(depth, type = 'brace', root = true) {
    let ast = { type: 'text', value: 'a' }
    for (let i = 0; i < depth; i++) ast = { type, nodes: [ast] }
    return root ? { type: 'root', nodes: [ast] } : ast
}

for (const [consumer, braces] of installedCopies) {
    test(`${consumer} resolves braces that rejects 4000-level exploits below the input length limit`, () => {
        for (const kind of ['braces', 'parentheses']) {
            const input = nested(kind, 4000)
            assert.ok(input.length < 10000)
            for (const method of methods) rejectsDepth(() => braces[method](input))
            rejectsDepth(() => braces(input))
            rejectsDepth(() => braces(input, { expand: true }))
        }
    })

    test(`${consumer} enforces the 100/101 boundary for braces, parentheses and mixed nesting`, () => {
        for (const kind of ['braces', 'parentheses']) {
            for (const method of methods) {
                assert.doesNotThrow(() => bounded(() => braces[method](nested(kind, 100))))
                rejectsDepth(() => braces[method](nested(kind, 101)))
            }
        }
        const mixed = depth => '({'.repeat(depth) + 'a,b' + '})'.repeat(depth)
        for (const method of methods) {
            assert.doesNotThrow(() => bounded(() => braces[method](mixed(50))))
            rejectsDepth(() => braces[method](mixed(51)))
        }
    })

    test(`${consumer} cannot disable the depth cap and honors stricter fractional limits`, () => {
        for (const maxDepth of [false, Infinity, Number.NaN, 1e9]) {
            for (const method of methods) {
                rejectsDepth(() => braces[method](nested('braces', 101), { maxDepth }))
                rejectsDepth(() => braces[method](nested('parentheses', 101), { maxDepth }))
            }
        }
        for (const maxDepth of [1, 1.5]) {
            for (const kind of ['braces', 'parentheses']) {
                for (const method of methods) {
                    assert.doesNotThrow(() => bounded(() => braces[method](nested(kind, 1), { maxDepth })))
                    rejectsDepth(() => braces[method](nested(kind, 2), { maxDepth }))
                }
            }
        }
    })

    test(`${consumer} accepts many escaped or quoted braces without treating them as nested syntax`, () => {
        const escaped = '\\{'.repeat(2000) + 'a' + '\\}'.repeat(2000)
        const quoted = '"' + '{'.repeat(4000) + 'a' + '}'.repeat(4000) + '"'
        const flat = '{a}'.repeat(2000)
        for (const pattern of [escaped, quoted, flat]) {
            assert.ok(pattern.length < 10000)
            for (const method of methods) {
                assert.doesNotThrow(() => bounded(() => braces[method](pattern)))
            }
        }
        assert.equal(braces.stringify(escaped, { keepEscaping: true }), escaped)
        assert.equal(braces.stringify(quoted, { keepQuotes: true }), quoted)
    })

    test(`${consumer} applies the depth cap to caller ASTs, including ASTs without a root wrapper`, () => {
        for (const method of ['compile', 'expand', 'stringify']) {
            for (const type of ['brace', 'paren']) {
                for (const root of [true, false]) {
                    assert.doesNotThrow(() => bounded(() => braces[method](astAtDepth(100, type, root))))
                    rejectsDepth(() => braces[method](astAtDepth(101, type, root)))
                    rejectsDepth(() => braces[method](astAtDepth(4000, type, root)))
                    for (const maxDepth of [false, Infinity, 1e9]) {
                        rejectsDepth(() => braces[method](astAtDepth(101, type, root), { maxDepth }))
                    }
                }
            }
        }
    })

    test(`${consumer} bounds cyclic AST children and rejects cyclic expansion parent chains`, () => {
        for (const method of ['compile', 'expand', 'stringify']) {
            const ast = { type: 'root', nodes: [] }
            ast.nodes.push(ast)
            rejectsDepth(() => braces[method](ast))
        }
        for (const multipleParents of [false, true]) {
            const ast = { type: 'paren', nodes: [{ type: 'text', value: 'a' }] }
            ast.parent = multipleParents ? { type: 'paren', parent: ast } : ast
            assert.throws(() => bounded(() => braces.expand(ast)), error =>
                error instanceof RangeError && /parent chain contains a cycle/.test(error.message))
        }
    })

    test(`${consumer} preserves ordinary nested expansion, ranges and stringify compatibility`, () => {
        assert.deepEqual(braces.expand('src/{components/{a,b},pages}/{one,two}.vue'), [
            'src/components/a/one.vue', 'src/components/a/two.vue',
            'src/components/b/one.vue', 'src/components/b/two.vue',
            'src/pages/one.vue', 'src/pages/two.vue',
        ])
        assert.deepEqual(braces.expand('file{01..03}.{js,ts}'), [
            'file01.js', 'file01.ts', 'file02.js', 'file02.ts', 'file03.js', 'file03.ts',
        ])
        assert.equal(braces.compile('src/{a,{b,c}}/*.js'), 'src/(a|(b|c))/*.js')
        assert.deepEqual(braces.expand('foo/({a,b})'), ['foo/(a)', 'foo/(b)'])
        for (const pattern of ['{{a}}', '{a,{b}}', '{{x}y}', '{a,{b,{c}}', '{}{a}']) {
            assert.equal(braces.stringify(braces.parse(pattern), { escapeInvalid: true }), pattern)
        }
    })
}

test('micromatch public brace parsing and expansion reject deep input and retain ordinary glob behavior', () => {
    for (const kind of ['braces', 'parentheses']) {
        const input = nested(kind, 4000) + '/{one,two}.js'
        assert.ok(input.length < 10000)
        rejectsDepth(() => micromatch.parse(input))
        rejectsDepth(() => micromatch.braces(input))
        rejectsDepth(() => micromatch.braceExpand(input))
    }
    assert.deepEqual(micromatch.braceExpand('src/{a,{b,c}}/*.js'), [
        'src/a/*.js', 'src/b/*.js', 'src/c/*.js',
    ])
    assert.deepEqual(micromatch(['src/a/one.js', 'src/b/two.js', 'src/d/one.js'], 'src/{a,{b,c}}/*.js'), [
        'src/a/one.js', 'src/b/two.js',
    ])
})

test('chokidar directory glob expansion uses the bounded braces implementation', async t => {
    const watcher = new chokidar.FSWatcher({ persistent: false })
    t.after(() => watcher.close())
    // Exercise its real directory expansion without opening filesystem watchers.
    const helper = watcher._getWatchHelpers('src/{a,{b,c}}/*.js')
    assert.deepEqual(helper.dirParts, [['a'], ['b'], ['c']])
    for (const kind of ['braces', 'parentheses']) {
        const input = 'src/' + nested(kind, 4000) + '/{one,two}.js'
        assert.ok(input.length < 10000)
        rejectsDepth(() => helper.getDirParts(input))
    }
})
