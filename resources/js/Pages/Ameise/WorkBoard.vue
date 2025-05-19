<!-- resources/js/components/UnitWorkBoard.vue -->
<template>
    <v-container>
        <v-row>
            <v-col cols="12">
                <h1 class="text-h4 mb-4">Рабочая доска</h1>
            </v-col>
        </v-row>
        <v-row>
            <v-col cols="6">
                <v-card class="pa-4" elevation="2">
                    <v-card-title>Не было контактов</v-card-title>
                    <draggable
                        v-model="noContactUnits"
                        group="units"
                        item-key="id"
                        class="drag-area"
                        @end="onDragEnd"
                    >
                        <template #item="{ element }">
                            <v-card class="ma-2" elevation="1">
                                <v-card-text>{{ element.name }}</v-card-text>
                            </v-card>
                        </template>
                    </draggable>
                </v-card>
            </v-col>
            <v-col cols="6">
                <v-card class="pa-4" elevation="2">
                    <v-card-title>Контакт состоялся</v-card-title>
                    <draggable
                        v-model="contactedUnits"
                        group="units"
                        item-key="id"
                        class="drag-area"
                        @end="onDragEnd"
                    >
                        <template #item="{ element }">
                            <v-card class="ma-2" elevation="1">
                                <v-card-text>{{ element.name }}</v-card-text>
                            </v-card>
                        </template>
                    </draggable>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { VueDraggableNext } from 'vue-draggable-next'

const noContactUnits = ref([])
const contactedUnits = ref([])

async function fetchUnits() {
    try {
        const response = await axios.get('/api/work-list')
        noContactUnits.value = response.data.no_contact
        contactedUnits.value = response.data.contacted
    } catch (error) {
        console.error('Error fetching units:', error)
    }
}

async function onDragEnd(event) {
    const { element, to } = event
    const stage = to === noContactUnits.value ? 'no_contact' : 'contacted'

    try {
        await axios.put(`/api/units/${element.id}/work-list/stage`, { stage })
        await fetchUnits() // Обновляем списки
    } catch (error) {
        console.error('Error updating stage:', error)
        await fetchUnits() // Восстанавливаем состояние при ошибке
    }
}

onMounted(fetchUnits)
</script>

<style scoped>
.drag-area {
    min-height: 100px;
}
</style>
