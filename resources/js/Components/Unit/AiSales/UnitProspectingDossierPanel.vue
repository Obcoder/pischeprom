<script setup>
import axios from 'axios'
import { onMounted, ref } from 'vue'

const props = defineProps({ unitId: { type: Number, required: true } })
const contexts = ref([])
const selectedContext = ref(null)
const dossier = ref(null)
const error = ref('')
const tab = ref('overview')

async function loadContexts() {
    try {
        const { data } = await axios.get(`/api/ai-sales/units/${props.unitId}/dossier`)
        contexts.value = data.data.contexts || []
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Контексты недоступны.'
    }
}

async function loadDossier() {
    dossier.value = null
    error.value = ''
    if (!selectedContext.value) return
    try {
        const { data } = await axios.get(`/api/ai-sales/units/${props.unitId}/prospecting-dossier`, { params: { context_id: selectedContext.value } })
        dossier.value = data.data
    } catch (requestError) {
        error.value = requestError.response?.status === 404 ? 'Stage 08 dossier выключен.' : (requestError.response?.data?.message || 'Досье недоступно.')
    }
}

onMounted(loadContexts)
</script>

<template>
    <v-card variant="outlined">
        <v-card-title>Prospecting dossier Unit</v-card-title>
        <v-card-text>
            <v-alert type="info" variant="tonal" density="compact" class="mb-3">Контекст обязателен; sales и procurement никогда не объединяются по умолчанию.</v-alert>
            <v-select v-model="selectedContext" :items="contexts" item-value="id" :item-title="item => `${item.lane_label}: ${item.role_label}`" label="Выберите бизнес-контекст" clearable @update:model-value="loadDossier" />
            <v-alert v-if="dossier?.dual_role_warning" type="warning" variant="tonal">Unit одновременно участвует в sales и procurement. Показан только выбранный lane.</v-alert>
            <v-alert v-if="error" type="info" variant="tonal">{{ error }}</v-alert>
        </v-card-text>
        <template v-if="dossier">
            <v-tabs v-model="tab" show-arrows>
                <v-tab value="overview">Обзор</v-tab><v-tab value="contexts">Контексты</v-tab><v-tab value="sources">Источники</v-tab>
                <v-tab value="observations">Наблюдения</v-tab><v-tab value="contacts">Контакты</v-tab><v-tab value="goods">Товары</v-tab>
                <v-tab value="communications">Переписка</v-tab><v-tab value="entities">Entities и сделки</v-tab><v-tab value="runs">AI-запуски</v-tab><v-tab value="timeline">История</v-tab>
            </v-tabs>
            <v-tabs-window v-model="tab">
                <v-tabs-window-item value="overview"><v-card-text>{{ dossier.unit.name }} · {{ dossier.context.lane }} · {{ dossier.context.stage }}</v-card-text></v-tabs-window-item>
                <v-tabs-window-item value="contexts"><v-card-text>Контекст #{{ dossier.context.id }} · {{ dossier.context.role_code }}</v-card-text></v-tabs-window-item>
                <v-tabs-window-item value="sources"><v-list><v-list-item v-for="source in dossier.sources" :key="source.id" :title="source.label || source.type" :subtitle="source.reference" /></v-list></v-tabs-window-item>
                <v-tabs-window-item value="observations"><v-list><v-list-item v-for="observation in dossier.observations" :key="observation.id" :title="observation.key" :subtitle="observation.summary" /></v-list></v-tabs-window-item>
                <v-tabs-window-item value="contacts"><v-list><v-list-item v-for="link in dossier.contact_links" :key="link.id" :title="link.channel_type" :subtitle="`${link.contact_role} · ${link.verification_status} · ${link.communication_state}`" /></v-list></v-tabs-window-item>
                <v-tabs-window-item value="goods"><v-list><v-list-item v-for="match in dossier.good_matches" :key="match.id" :title="match.good?.name" :subtitle="`${match.match_type} · ${match.status} · ${match.relevance}/100`" /></v-list></v-tabs-window-item>
                <v-tabs-window-item value="communications"><v-card-text>Сообщений: {{ dossier.communications.message_count }} · вложений: {{ dossier.communications.attachment_count }}. Raw correspondence не отображается и не экспортируется.</v-card-text></v-tabs-window-item>
                <v-tabs-window-item value="entities"><v-card-text>Транзакций: {{ dossier.transaction_count }}</v-card-text><v-list><v-list-item v-for="entity in dossier.linked_entities" :key="entity.id" :title="entity.name" /></v-list></v-tabs-window-item>
                <v-tabs-window-item value="runs">
                    <v-list><v-list-item v-for="run in dossier.ai_runs" :key="run.id" :title="run.definition_code" :subtitle="`${run.status} · ${run.created_at}`" /></v-list>
                    <v-list><v-list-item v-for="call in dossier.tool_calls" :key="call.id" :title="call.tool_code" :subtitle="`${call.status} · rows ${call.row_count || 0} · queries ${call.query_count || 0}`" /></v-list>
                    <v-card-text v-if="!dossier.ai_runs.length && !dossier.tool_calls.length">Контекстных AI-запусков и server-owned tool calls нет или они скрыты permissions.</v-card-text>
                </v-tabs-window-item>
                <v-tabs-window-item value="timeline"><v-list><v-list-item v-for="item in dossier.timeline.data" :key="`${item.reference.type}-${item.reference.id}`" :title="item.summary" :subtitle="`${item.type} · ${item.occurred_at}`" /></v-list></v-tabs-window-item>
            </v-tabs-window>
        </template>
    </v-card>
</template>
