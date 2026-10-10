<script>
import { h } from 'vue'
import { landingTextParts } from './links.js'

export default {
    props: { text: { type: [String, Number], default: '' }, goods: { type: Object, default: () => ({}) }, anchors: { type: Object, default: null } },
    setup: props => () => landingTextParts(props.text, props.goods, props.anchors).map(part => part.href
        ? h('a', {
            href: part.href,
            class: part.goodId ? undefined : part.href.startsWith('#source-') ? 'source-ref' : 'inline-link',
            'data-good-id': part.goodId || undefined,
            'data-inline-good-link': part.goodId ? '' : undefined,
        }, part.text)
        : part.text),
}
</script>
