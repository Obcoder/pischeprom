<script setup>
import { useDate } from 'vuetify'
import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'

defineProps({
    emails: {
        type: Array,
        default: () => [],
    },
})

const date = useDate()
</script>

<template>
    <BaseSectionCard title="Sendings" icon="mdi-email-fast-outline">
        <div class="d-flex flex-column ga-4">
            <template
                v-for="email in emails"
                :key="email.id"
            >
                <v-sheet
                    v-if="email.sendings?.length"
                    rounded="lg"
                    border
                    class="pa-3"
                >
                    <div class="font-weight-bold mb-3">
                        {{ email.address }}
                    </div>

                    <v-list density="compact" class="pa-0">
                        <v-list-item
                            v-for="sending in email.sendings"
                            :key="sending.id"
                            class="px-0"
                        >
                            <template #title>
                                <div class="text-body-2">
                                    {{ sending.subject }}
                                </div>
                            </template>

                            <template #subtitle>
                                <div class="text-caption">
                                    {{ date.format(sending.created_at, 'fullDate') }}
                                </div>
                            </template>
                        </v-list-item>
                    </v-list>
                </v-sheet>
            </template>
        </div>
    </BaseSectionCard>
</template>
