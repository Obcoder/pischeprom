<script setup>
import { useHead } from '@unhead/vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import MailMessagesPage from '@/Components/Contacts/Emails/MailMessagesPage.vue'

defineProps({
    mailTimezone: { type: String, default: 'Europe/Moscow' },
})

defineOptions({
    layout: VerwalterLayout,
})

useHead({
    title: 'Почта Ameise',
    meta: [
        {
            name: 'description',
            content: 'Работа с почтовыми ящиками, входящими и исходящими письмами Ameise',
        },
    ],
})
</script>

<template>
    <v-theme-provider theme="dark">
        <v-container fluid class="ameise-mail-page">
            <MailMessagesPage standalone :timezone="mailTimezone" />
        </v-container>
    </v-theme-provider>
</template>

<style scoped>
.ameise-mail-page {
    width: 100%;
    height: calc(100dvh - var(--v-layout-top, 58px));
    min-height: 0;
    padding: 12px 12px 36px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background:
        radial-gradient(circle at 16% 10%, rgba(14, 165, 233, 0.16), transparent 28%),
        radial-gradient(circle at 78% 0%, rgba(128, 0, 0, 0.18), transparent 32%),
        linear-gradient(180deg, #0f172a 0%, #111827 52%, #0b1120 100%);
}
</style>
