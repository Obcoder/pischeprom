<script setup>
import { useDate } from 'vuetify'
import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'

defineProps({
    stages: {
        type: Array,
        default: () => [],
    },
})

const date = useDate()

function timeDiff(time) {
    if (!time) return null
    const now = new Date()
    const diffMilliseconds = now - Date.parse(time)
    const denominator = 1000 * 3600 * 24
    return Math.round(diffMilliseconds / denominator)
}
</script>

<template>
    <BaseSectionCard
        title="Stages"
        icon="mdi-timeline-clock-outline"
        compact
    >
        <div class="d-flex flex-column ga-3">
            <v-sheet
                v-for="stage in stages"
                :key="stage.id"
                rounded="lg"
                border
                class="pa-3"
            >
                <div class="d-flex justify-space-between align-start mb-2">
                    <div class="font-weight-bold">
                        {{ stage.name }}
                    </div>

                    <v-chip
                        size="small"
                        :color="stage.pivot?.isActive ? 'green' : 'grey'"
                        variant="tonal"
                    >
                        {{ stage.pivot?.isActive ? 'Active' : 'Inactive' }}
                    </v-chip>
                </div>

                <div class="text-body-2 mb-1">
                    Start:
                    <span class="font-weight-medium">
            {{ stage.pivot?.startDate ? date.format(stage.pivot.startDate, 'fullDate') : '—' }}
          </span>
                </div>

                <div class="text-body-2 mb-1">
                    Year:
                    <span class="font-weight-medium">
            {{ stage.pivot?.startDate ? date.format(stage.pivot.startDate, 'year') : '—' }}
          </span>
                </div>

                <div class="text-body-2">
                    Days passed:
                    <v-chip size="small" variant="outlined" class="ms-2">
                        {{ timeDiff(stage.pivot?.startDate) ?? '—' }}
                    </v-chip>
                </div>
            </v-sheet>
        </div>
    </BaseSectionCard>
</template>
