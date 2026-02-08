<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import debounce from 'lodash/debounce';
import {useDate} from 'vuetify';

const date = useDate()

const items = ref([])
const total = ref(0)
const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(50) // вместо 1000 — сервер!
const sortBy = ref([])
const search = ref('')

const headerUris = [
    { title: 'ID', key: 'id' },
    { title: 'Address', key: 'address' },
    { title: 'Units', key: 'units', sortable: false },
    { title: 'Valid', key: 'is_valid' },
    { title: 'Follow', key: 'follow' },
    { title: 'Created', key: 'created_at' },
]

// 🔹 ЕДИНСТВЕННАЯ функция загрузки
const fetchUris = async () => {
    loading.value = true

    const { data } = await axios.get('/api/uri', {
        params: {
            page: page.value,
            itemsPerPage: itemsPerPage.value,
            search: search.value,
            sortBy: sortBy.value.map(s => s.key),
            sortDesc: sortBy.value.map(s => s.order === 'desc'),
        }
    })

    items.value = data.items
    total.value = data.total
    loading.value = false
}

// 🔹 debounce для поиска
const debouncedFetch = debounce(() => {
    page.value = 1
    fetchUris()
}, 400)

// 🔹 watchers
watch(search, debouncedFetch)
watch([page, itemsPerPage], fetchUris, { immediate: true })
</script>



<template>

<!--    <v-row>-->
<!--        <v-col lg="2">-->
<!--            <v-text-field v-model="searchUris"-->
<!--                          label="Поиск uris"-->
<!--                          variant="solo-filled"-->
<!--                          color="rose"-->
<!--                          density="comfortable"-->
<!--                          hide-details-->
<!--            ></v-text-field>-->
<!--        </v-col>-->
<!--    </v-row>-->
<!--    <v-row>-->
<!--        <v-col>-->
<!--            <v-data-table :items="filteredUris"-->
<!--                          items-per-page="1000"-->
<!--                          :headers="headerUris"-->
<!--                          fixed-header-->
<!--                          height="888px"-->
<!--                          density="compact"-->
<!--                          class="text-xs border rounded border-red-50"-->
<!--                          hover-->
<!--            >-->
<!--                <template v-slot:item.address="{item}">-->
<!--                    <a :href="item.address" target="_blank"-->
<!--                       class="text-xs text-green-400 inline-block"-->
<!--                    >{{item.address}}</a>-->
<!--                </template>-->
<!--                <template v-slot:item.units="{item}">-->
<!--                    <div v-for="unit in item.units">-->
<!--                        <Link :href="route('web.unit.show', unit.id)">{{unit.name}}</Link>-->
<!--                    </div>-->
<!--                </template>-->
<!--                <template v-slot:item.created_at="{item}">-->
<!--                    <span class="text-xs font-sans">{{date.format(item.created_at, 'fullDate')}}</span>-->
<!--                </template>-->
<!--            </v-data-table>-->
<!--        </v-col>-->
<!--    </v-row>-->

    <v-card>
        <v-text-field
            v-model="search"
            label="Поиск"
            clearable
            class="pa-4"
            @update:model-value="fetchUris"
        />

        <v-data-table-server
            :headers="headerUris"
            :items="items"
            :items-length="total"
            :loading="loading"
            fixed-header
            height="888px"
            density="compact"
            hover
            class="text-xs border rounded border-red-50"

            v-model:page="page"
            v-model:items-per-page="itemsPerPage"
            v-model:sort-by="sortBy"
            @update:options="fetchUris"
        >
            <!-- address -->
            <template #item.address="{ item }">
                <a
                    :href="item.address"
                    target="_blank"
                    class="text-xs text-green-400 inline-block"
                >
                    {{ item.address }}
                </a>
            </template>

            <!-- units -->
            <template #item.units="{ item }">
                <div v-for="unit in item.units" :key="unit.id">
                    <Link :href="route('web.unit.show', unit.id)">
                        {{ unit.name }}
                    </Link>
                </div>
            </template>

            <!-- created_at -->
            <template #item.created_at="{ item }">
        <span class="text-xs font-sans">
          {{ date.format(item.created_at, 'fullDate') }}
        </span>
            </template>
        </v-data-table-server>
    </v-card>
</template>


<style scoped>

</style>
