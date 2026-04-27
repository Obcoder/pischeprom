<script setup>
const props = defineProps({
    city: {
        type: Object,
        required: true,
    },
})

defineEmits(['edit'])

function formatNumber(value) {
    return new Intl.NumberFormat('ru-RU').format(value)
}
</script>

<template>
    <v-card class="rounded border border-teal-900 bg-slate-950 h-100">
        <v-card-title class="d-flex justify-space-between align-center">
            <span class="text-teal-lighten-3">Население</span>

            <v-btn
                icon="mdi-pencil"
                size="x-small"
                variant="text"
                color="amber"
                @click="$emit('edit')"
            />
        </v-card-title>

        <v-card-text>
            <v-table
                v-if="city.populations?.length"
                density="compact"
                class="bg-transparent"
            >
                <thead>
                <tr>
                    <th>Год</th>
                    <th class="text-right">Кол-во</th>
                </tr>
                </thead>

                <tbody>
                <tr
                    v-for="item in city.populations"
                    :key="item.id"
                >
                    <td class="text-teal-lighten-3">
                        {{ item.year }}
                    </td>

                    <td class="text-right text-grey-lighten-1">
                        {{ formatNumber(item.population) }}
                    </td>
                </tr>
                </tbody>
            </v-table>

            <div
                v-else
                class="text-grey text-sm"
            >
                Данных по населению пока нет.
            </div>
        </v-card-text>
    </v-card>
</template>
