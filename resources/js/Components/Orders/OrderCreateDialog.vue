<script setup>
import { computed, onBeforeUnmount, reactive, ref, useId, watch } from 'vue'
import OrderDialogForm from '@/Components/Orders/OrderDialogForm.vue'
import { useOrderDialogEditor } from '@/Composables/useOrderDialogEditor'

const props = defineProps({
    modelValue: Boolean,
    canCreateDelivery: Boolean,
})
const emit = defineEmits(['update:modelValue', 'saved'])
const titleId = `order-create-${useId()}`
const errorElement = ref(null)
const bodyElement = ref(null)
const editor = reactive(useOrderDialogEditor({
    order: ref(null),
    orderId: () => null,
    visible: () => props.modelValue,
    editable: () => true,
    creating: () => true,
    canCreateDelivery: () => props.canCreateDelivery,
    onSaved: order => {
        emit('saved', order)
        emit('update:modelValue', false)
    },
}))
const validationMessages = computed(() => [...new Set(Object.values(editor.errors).flat())])

function close() {
    if (editor.saving) return
    if (editor.dirty && !window.confirm('Закрыть без сохранения заказа?')) return
    editor.reset()
    emit('update:modelValue', false)
}

watch(() => props.modelValue, open => {
    if (open) editor.beginEdit()
}, { immediate: true })
watch(() => editor.error, message => {
    if (!message) return
    if (bodyElement.value) bodyElement.value.scrollTop = 0
    errorElement.value?.focus({ preventScroll: true })
}, { flush: 'post' })
onBeforeUnmount(() => editor.dispose())
</script>

<template>
    <v-dialog :model-value="modelValue" :aria-labelledby="titleId" :persistent="editor.saving || editor.dirty" max-width="1000" scrollable @update:model-value="value => { if (!value) close() }">
        <v-card class="order-create" theme="light">
            <header class="order-create__header">
                <span class="order-create__icon"><v-icon icon="mdi-receipt-text-plus-outline" size="22" /></span>
                <div><h2 :id="titleId">Новый заказ</h2><p>Покупатель, доставка и состав заказа</p></div>
                <button type="button" :disabled="editor.saving" aria-label="Закрыть создание заказа" @click="close"><v-icon icon="mdi-close" size="20" /></button>
            </header>
            <v-progress-linear v-if="editor.loadingOptions" indeterminate color="#7f1d1d" height="2" />
            <div ref="bodyElement" class="order-create__body">
                <div v-if="editor.error" ref="errorElement" class="order-create__error" role="alert" tabindex="-1">
                    <strong>{{ editor.error }}</strong>
                    <ul v-if="validationMessages.length"><li v-for="message in validationMessages" :key="message">{{ message }}</li></ul>
                    <p v-if="editor.stale">Закройте окно и обновите список заказов, чтобы проверить, появился ли заказ.</p>
                    <button v-else-if="!editor.optionsReady" type="button" :disabled="editor.loadingOptions" @click="editor.beginEdit">Повторить загрузку</button>
                </div>
                <OrderDialogForm :editor="editor" @cancel="close" />
            </div>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.order-create { --details-bg: #fff; --details-text: #252b33; --details-border: #d7dce2; --details-header: #f5f6f8; --details-muted: #737c88; --details-accent: #7f1d1d; --details-hover: #e7ebef; --details-panel: #fbfcfd; --details-total: #185c4b; --details-note: #fffcf5; max-height: min(90dvh, 900px); border: 1px solid var(--details-border); border-radius: 12px; color: var(--details-text); background: var(--details-bg); }
.order-create__header { display: flex; align-items: center; flex-shrink: 0; gap: 11px; padding: 14px 18px; border-bottom: 1px solid var(--details-border); background: var(--details-header); }
.order-create__icon { display: grid; place-items: center; width: 38px; height: 38px; border: 1px solid #e3cdcd; border-radius: 9px; color: var(--details-accent); background: #f4e9e9; }
.order-create__header h2 { font-size: 17px; font-weight: 750; }
.order-create__header p { margin: 2px 0 0; color: var(--details-muted); font-size: 11px; }
.order-create__header button { display: grid; place-items: center; margin-left: auto; width: 30px; height: 30px; border-radius: 6px; color: var(--details-muted); }
.order-create__header button:hover { background: var(--details-hover); }
.order-create button:focus-visible { outline: 2px solid var(--details-accent); outline-offset: 2px; }
.order-create button:disabled { opacity: .5; }
.order-create__body { overflow: auto; min-height: 0; }
.order-create__error { padding: 12px 18px; color: #a22f2f; background: #fff6f6; font-size: 12px; }
.order-create__error ul { padding-left: 18px; margin-top: 6px; }
.order-create__error button { margin-top: 6px; text-decoration: underline; }
@media (max-width: 600px) { .order-create { max-height: 94dvh; } .order-create__header { padding: 11px 12px; } }
</style>
