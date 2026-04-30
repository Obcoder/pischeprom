<script setup>
import { computed } from 'vue'

const model = defineModel({
    type: Boolean,
    default: false,
})

const props = defineProps({
    message: {
        type: Object,
        default: null,
    },
    loading: Boolean,
})

const emit = defineEmits([
    'reload',
])

const bodyHtml = computed(() => {
    return props.message?.html || null
})

const bodyText = computed(() => {
    return props.message?.text || 'Тело письма не загружено или письмо пустое.'
})

function formatDate(value) {
    if (!value) {
        return '—'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

function recipients(list) {
    return list?.map((item) => item.address).join(', ') || '—'
}
</script>

<template>
    <v-dialog
        v-model="model"
        width="1200"
        scrollable
    >
        <v-card class="rounded border border-blue-900 bg-slate-950">
            <v-card-title class="d-flex justify-space-between align-start">
                <div>
                    <div class="text-blue-lighten-3 text-base">
                        {{ message?.subject || 'Без темы' }}
                    </div>

                    <div class="text-[11px] text-grey mt-1">
                        {{ formatDate(message?.message_date) }}
                    </div>
                </div>

                <div class="d-flex ga-1">
                    <v-chip
                        size="small"
                        :color="message?.direction === 'incoming' ? 'purple' : 'blue'"
                        variant="tonal"
                    >
                        {{ message?.direction === 'incoming' ? 'Входящее' : 'Исходящее' }}
                    </v-chip>

                    <v-btn
                        icon="mdi-refresh"
                        size="small"
                        variant="text"
                        color="blue"
                        :loading="loading"
                        @click="emit('reload')"
                    />
                </div>
            </v-card-title>

            <v-divider />

            <v-card-text>
                <v-row dense>
                    <v-col cols="12" lg="6">
                        <div class="text-[11px] text-grey">From</div>
                        <div class="text-sm text-purple-lighten-3">
                            {{ message?.from_name || '' }}
                            {{ message?.from_address || '—' }}
                        </div>
                    </v-col>

                    <v-col cols="12" lg="6">
                        <div class="text-[11px] text-grey">To</div>
                        <div class="text-sm text-blue-lighten-3">
                            {{ recipients(message?.to) }}
                        </div>

                        <div
                            v-if="message?.cc?.length"
                            class="mt-1"
                        >
                            <div class="text-[11px] text-grey">CC</div>
                            <div class="text-sm text-blue-lighten-3">
                                {{ recipients(message?.cc) }}
                            </div>
                        </div>
                    </v-col>
                </v-row>

                <v-divider class="my-4" />

                <v-progress-linear
                    v-if="loading"
                    indeterminate
                    color="blue"
                    class="mb-3"
                />

                <div
                    v-if="bodyHtml"
                    class="mail-body bg-white text-black rounded pa-4"
                    v-html="bodyHtml"
                />

                <pre
                    v-else
                    class="mail-body-text rounded border border-blue-900 bg-slate-900 text-grey-lighten-2 pa-4"
                >{{ bodyText }}</pre>
            </v-card-text>

            <v-card-actions>
                <v-spacer />

                <v-btn
                    text="Закрыть"
                    variant="text"
                    @click="model = false"
                />
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.mail-body {
    max-height: 620px;
    overflow: auto;
}

.mail-body-text {
    max-height: 620px;
    overflow: auto;
    white-space: pre-wrap;
    font-size: 12px;
    line-height: 1.5;
}
</style>
