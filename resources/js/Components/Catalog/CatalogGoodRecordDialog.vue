<script setup>
import axios from 'axios'
import { onScopeDispose, ref, watch } from 'vue'
import CatalogNodeDialog from './CatalogNodeDialog.vue'
import CatalogSchemaDialog from './CatalogSchemaDialog.vue'

const open = defineModel({ type: Boolean, default: false })
const props = defineProps({ goodId: { type: [Number, String], required: true } })
const emit = defineEmits(['saved', 'deleted'])
const node = ref(null)
const nodes = ref([])
const levels = ref([])
const loading = ref(false)
const error = ref('')
const schemaOpen = ref(false)
let version = 0
let controller = null

function cancel() {
    version++
    controller?.abort()
    controller = null
    loading.value = false
}
async function load(clear = false) {
    cancel()
    if (!open.value) return
    if (clear) node.value = null
    const requestVersion = version
    controller = new AbortController()
    loading.value = true
    error.value = ''
    try {
        const { data } = await axios.get('/api/catalog', { signal: controller.signal })
        if (requestVersion !== version || !open.value) return
        const matches = data.nodes.filter(item => item.entity_type === 'good' && String(item.entity_id) === String(props.goodId))
        const selected = matches.find(item => item.id === node.value?.id) || matches[0]
        if (!selected) throw new Error('Товар не найден в каталоге. Возможно, он был удалён.')
        levels.value = data.levels
        nodes.value = data.nodes
        node.value = selected
    } catch (failure) {
        if (requestVersion !== version || !open.value) return
        error.value = failure.response?.data?.message || failure.message || 'Не удалось открыть карточку записи.'
    } finally {
        if (requestVersion === version) { loading.value = false; controller = null }
    }
}
watch([open, () => props.goodId], () => {
    schemaOpen.value = false
    if (open.value) load(true)
    else cancel()
}, { immediate: true })
onScopeDispose(cancel)
</script>

<template>
    <v-dialog :model-value="open && !node" max-width="480" @update:model-value="value => { if (!value) open = false }">
        <v-card>
            <v-card-title>Карточка записи</v-card-title>
            <v-card-text>
                <div v-if="loading" class="d-flex align-center ga-3"><v-progress-circular indeterminate size="24" width="2" />Загрузка карточки…</div>
                <v-alert v-else-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>
            </v-card-text>
            <v-card-actions><v-spacer /><v-btn @click="open = false">Закрыть</v-btn><v-btn v-if="error" :loading="loading" @click="load(true)">Повторить</v-btn></v-card-actions>
        </v-card>
    </v-dialog>
    <CatalogNodeDialog v-if="node" :model-value="open" :node="node" :nodes="nodes" :levels="levels"
        @update:model-value="open = $event" @saved="emit('saved', $event)" @deleted="emit('deleted', $event)"
        @changed="load()" @schema="schemaOpen = true" />
    <CatalogSchemaDialog v-model="schemaOpen" :levels="levels" @changed="load()" />
    <v-snackbar :model-value="Boolean(error && node && open)" color="error" @update:model-value="value => { if (!value) error = '' }">{{ error }}</v-snackbar>
</template>
