<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import { useHead } from '@vueuse/head'
import { route } from 'ziggy-js'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import EntityDetailCard from '@/Components/Dictionaries/Entities/EntityDetailCard.vue'

const props = defineProps({
    entityId: {
        type: Number,
        required: true,
    },
})

defineOptions({
    layout: VerwalterLayout,
})

const entity = ref(null)
const loading = ref(false)
const error = ref(null)

async function fetchEntity() {
    loading.value = true
    error.value = null

    try {
        const { data } = await axios.get(`/api/entities/${props.entityId}`)
        entity.value = data.data || data
    } catch (err) {
        console.error(err)
        error.value = 'Не удалось загрузить Entity.'
    } finally {
        loading.value = false
    }
}

onMounted(fetchEntity)

useHead({
    title: `Entity #${props.entityId}`,
})
</script>

<template>
    <v-container fluid class="entity-page pa-4">
        <div class="entity-page__toolbar mb-4">
            <div>
                <div class="text-caption text-medium-emphasis">CRM Entity</div>
                <h1>{{ entity?.name || `Entity #${entityId}` }}</h1>
            </div>

            <Link :href="route('Ameise.großbuch')" class="entity-page__back">
                <v-btn variant="tonal" color="#800000" rounded="lg" prepend-icon="mdi-arrow-left">
                    Grossbuch
                </v-btn>
            </Link>
        </div>

        <v-progress-linear v-if="loading" indeterminate color="#800000" class="mb-3" />

        <v-alert v-if="error" type="error" variant="tonal" class="mb-3">
            {{ error }}
        </v-alert>

        <EntityDetailCard :entity="entity" />
    </v-container>
</template>

<style scoped>
.entity-page {
    min-height: 100vh;
    background: linear-gradient(135deg, #fffaf4 0%, #f7f2eb 48%, #fff 100%);
}

.entity-page__toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.entity-page__toolbar h1 {
    margin: 0;
    color: #35160d;
    font-size: clamp(1.4rem, 2vw, 2.4rem);
    font-weight: 950;
}

.entity-page__back {
    text-decoration: none;
}
</style>
