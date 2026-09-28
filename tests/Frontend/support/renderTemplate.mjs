import * as Vue from 'vue'

// Render the compiled template to VNodes so tests can exercise its real event
// bindings without a browser or replacing the component's setup logic.
export function templateRenderer(compiledTemplate, api, props = {}) {
    const code = compiledTemplate.code
        .replace(/^import \{ (.+) \} from "vue"$/gm, (_, imports) => `const { ${imports.replace(/ as /g, ': ')} } = Vue`)
        .replace('export function render', 'return function render')
    const render = new Function('Vue', code)({
        ...Vue,
        resolveComponent: name => name,
        // DOM directive hooks do not run during VNode-only interaction tests.
        withDirectives: vnode => vnode,
    })
    return () => render({}, [], props, Vue.proxyRefs(api), {}, {})
}

export function findVNode(root, predicate) {
    if (!root || typeof root !== 'object') return null
    if (predicate(root)) return root
    const children = Array.isArray(root) ? root
        : Array.isArray(root.children) ? root.children
            : typeof root.children?.default === 'function' ? root.children.default() : []
    for (const child of children) {
        const match = findVNode(child, predicate)
        if (match) return match
    }
    return null
}

export function hasClass(node, name) {
    return String(node.props?.class || '').split(/\s+/).includes(name)
}
