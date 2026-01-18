<script setup>
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'

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

async function fetchCategories () {
    loading.value = true

    const { page, itemsPerPage, sortBy } = options.value
    const sort = sortBy[0] || {}

    const response = await axios.get(
        route('categories.index'),
        {
            params: {
                page,
                itemsPerPage,
                search: search.value,
                sortBy: sort.key,
                sortDesc: sort.order === 'desc',
            }
        }
    )

    categories.value = response.data.data
    total.value = response.data.meta.total

    loading.value = false
}

watch(options, fetchCategories, { deep: true })

watch(search, () => {
    options.value.page = 1
    fetchCategories()
})

onMounted(fetchCategories)
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
            :items-length="total"
            :loading="loading"
            v-model:options="options"
        />
    </v-card>
</template>
