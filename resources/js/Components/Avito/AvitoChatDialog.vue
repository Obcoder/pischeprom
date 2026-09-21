<script setup>
import { computed, ref, watch } from 'vue'
import AvitoMessages from './AvitoMessages.vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    chat: { type: Object, default: null },
    entityName: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue', 'chat-updated'])
const feedback = ref('')
const feedbackError = ref(false)
const title = computed(() => props.entityName || props.chat?.peer_name || props.chat?.title || 'Переписка')

watch(() => [props.modelValue, props.chat?.id], () => { feedback.value = '' })

function showFeedback(message, error = false) {
    feedback.value = message
    feedbackError.value = error
}
</script>

<template>
    <v-dialog
        :model-value="modelValue"
        max-width="1180"
        class="avito-chat-dialog"
        :aria-label="`Чат Авито: ${title}`"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <v-card class="avito-chat-dialog__card">
            <header class="avito-chat-dialog__header">
                <v-icon icon="mdi-message-text-outline" size="22" />
                <div><span>Чат Авито</span><strong>{{ title }}</strong></div>
                <v-btn icon="mdi-close" size="small" variant="text" title="Закрыть чат" aria-label="Закрыть чат" @click="emit('update:modelValue', false)" />
            </header>
            <div v-if="feedback" class="avito-chat-dialog__feedback" :class="{ 'is-error': feedbackError }" :role="feedbackError ? 'alert' : 'status'">
                <span>{{ feedback }}</span>
                <v-btn icon="mdi-close" size="x-small" variant="text" aria-label="Скрыть уведомление" @click="feedback = ''" />
            </div>
            <AvitoMessages
                v-if="modelValue && chat"
                :key="chat.id"
                class="avito-chat-dialog__messages"
                embedded
                full-featured
                :auto-mark-read="false"
                :chat="chat"
                @notice="showFeedback($event)"
                @error="showFeedback($event, true)"
                @chat-updated="emit('chat-updated', $event)"
            />
        </v-card>
    </v-dialog>
</template>

<style scoped>
.avito-chat-dialog__card { display: flex; flex-direction: column; height: min(840px, 88dvh); min-height: 0; overflow: hidden; border: 1px solid #51434b; border-radius: 10px; background: #25262b; color: #f1edf0; }
.avito-chat-dialog__header { display: flex; flex: 0 0 auto; align-items: center; gap: 10px; min-height: 53px; padding: 7px 12px; border-bottom: 1px solid #51434b; background: #303036; color: #ed9ebb; }
.avito-chat-dialog__header > div { flex: 1; min-width: 0; }
.avito-chat-dialog__header span { display: block; font-size: 9px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.avito-chat-dialog__header strong { display: block; overflow: hidden; color: #f6edf2; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }
.avito-chat-dialog__messages { flex: 1; min-height: 0; }
.avito-chat-dialog__feedback { display: flex; flex: 0 0 auto; align-items: center; gap: 8px; padding: 4px 12px; background: #30483d; color: #c4edda; font-size: 12px; }
.avito-chat-dialog__feedback span { flex: 1; }
.avito-chat-dialog__feedback.is-error { background: #592f3d; color: #ffd3df; }
@media (max-width: 600px) {
    .avito-chat-dialog :deep(.v-overlay__content) { width: calc(100% - 16px) !important; max-width: calc(100% - 16px) !important; max-height: calc(100% - 16px); margin: 8px; }
    .avito-chat-dialog__card { height: calc(100dvh - 16px); border-radius: 7px; }
    .avito-chat-dialog__header { padding: 6px 8px; }
}
</style>
