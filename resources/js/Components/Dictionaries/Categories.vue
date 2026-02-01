<script setup>
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'
import { route } from "ziggy-js";

const categories = ref([])
const total = ref(0)
const loading = ref(false)

const options = ref({
    page: 1,
    itemsPerPage: 25,
    sortBy: [],
})

const search = ref('')

const headers = [
    { title: 'ID', key: 'id' },
    { title: 'Name', key: 'name' },
]

async function fetchCategories() {
    loading.value = true
    try {
        const { page, itemsPerPage, sortBy } = options.value
        const sort = sortBy[0] ?? {}

        const response = await axios.get(
            route('categories.index'),
            {
                params: {
                    page,
                    per_page: itemsPerPage,
                    search: search.value,
                    ...(sort.key && { sortBy: sort.key }), // Условно добавляем, только если sort.key существует
                    ...(sort.key && { sortDesc: sort.order === 'desc' }), // Только с sortBy
                }
            }
        )

        categories.value = response.data.data
        total.value = response.data.meta.total
    } catch (error) {
        console.error('Error fetching categories:', error)
        if (error.response) {
            console.log('Validation errors:', error.response.data.errors) // Здесь увидите точные ошибки (e.g., {"per_page": ["..."]})
        }
    }
    loading.value = false
}

watch(options, fetchCategories, { deep: true })

watch(search, () => {
    options.value.page = 1
    fetchCategories()
})

onMounted(() => {
    fetchCategories()
})
</script>

<template>
    <v-card flat>
        <v-card-title class="d-flex gap-2">
            <v-text-field
                v-model="search"
                label="Search category"
                density="compact"
                variant="outlined"
                prepend-inner-icon="mdi-magnify"
                clearable
            />

            <v-spacer />

            <v-btn color="primary" variant="tonal">
                + Category
            </v-btn>
        </v-card-title>

        <v-data-table-server
            :headers="headers"
            :items="categories"
            :loading="loading"
            :items-length="total"
            v-model:options="options"
        />
    </v-card>
</template>
