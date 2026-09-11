<script setup>
import { computed } from 'vue'
import { useCommerceStore } from '@/Stores/commerce.js'

const props = defineProps({ failed: { type: Boolean, default: false } })
const store = useCommerceStore()
const labels = {
    connecting: 'Подключение…',
    live: 'Обновляется автоматически',
    offline: 'Связь потеряна',
    forbidden: 'Нет доступа к автообновлению',
    error: 'Автообновление недоступно',
}
const label = computed(() => props.failed ? 'Не удалось обновить данные' : labels[store.status])
const color = computed(() => props.failed || ['offline', 'error', 'forbidden'].includes(store.status) ? 'warning' : 'success')
</script>

<template>
    <v-chip
        v-if="store.status !== 'disabled'"
        size="x-small"
        variant="tonal"
        :color="color"
        :prepend-icon="store.status === 'live' && !failed ? 'mdi-sync' : 'mdi-connection'"
        :title="store.status === 'live' && !failed ? 'Изменения сохранённых документов появляются автоматически.' : 'Нажмите, чтобы восстановить соединение и загрузить актуальные данные.'"
        aria-live="polite"
        @click="store.reconnect()"
    >{{ label }}</v-chip>
</template>
