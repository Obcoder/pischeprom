<script setup>
import { computed } from 'vue'
import { Capacitor } from '@capacitor/core'
import { deliveryMapUrl, telephoneHref } from './delivery.js'

const props = defineProps({
    addresses: { type: Array, default: () => [] },
    telephone: { type: Object, default: null },
    compact: { type: Boolean, default: false },
    showTelephone: { type: Boolean, default: true },
})
const dialUrl = computed(() => telephoneHref(props.telephone))
// Android's BridgeWebViewClient opens external URLs with ACTION_VIEW and keeps
// the current WebView alive. Browsers open maps in a separate tab instead.
const externalTarget = Capacitor.isNativePlatform() ? undefined : '_blank'
</script>

<template>
    <div :class="['delivery-contacts', { compact }]">
        <div class="contact-heading">{{ showTelephone ? 'Доставка и связь' : 'Адрес доставки' }}</div>
        <template v-if="addresses.length">
            <component :is="deliveryMapUrl(address) ? 'a' : 'div'" v-for="address in addresses" :key="address.id"
                :href="deliveryMapUrl(address) || undefined" :target="externalTarget" rel="noopener noreferrer"
                class="contact-row" :aria-label="`Открыть в Яндекс Картах: ${address.full_address || address.address}`" @click.stop>
                <v-icon icon="mdi-map-marker-outline" size="21" />
                <span class="contact-copy"><span>{{ address.full_address || address.address }}</span><small>Яндекс Карты</small></span>
                <v-icon icon="mdi-open-in-new" size="17" class="contact-action" />
            </component>
        </template>
        <div v-else class="contact-row missing"><v-icon icon="mdi-map-marker-off-outline" size="21" /><span>Адрес доставки не указан</span></div>
        <template v-if="showTelephone">
        <component :is="dialUrl ? 'a' : 'div'" v-if="telephone?.number" :href="dialUrl || undefined"
            class="contact-row" :aria-label="dialUrl ? `Позвонить заказчику: ${telephone.number}` : undefined" @click.stop>
            <v-icon icon="mdi-phone-outline" size="21" />
            <span class="contact-copy"><span>{{ telephone.number }}</span><small v-if="dialUrl">Позвонить заказчику</small></span>
            <v-icon v-if="dialUrl" icon="mdi-phone-outgoing-outline" size="18" class="contact-action" />
        </component>
        <div v-else class="contact-row missing"><v-icon icon="mdi-phone-off-outline" size="21" /><span>Телефон заказчика не указан</span></div>
        </template>
    </div>
</template>
