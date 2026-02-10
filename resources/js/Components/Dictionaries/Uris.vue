<script setup>
import {onMounted, ref, watch} from 'vue'
import axios from 'axios'
import debounce from 'lodash/debounce';
import {useDate} from 'vuetify';
import {useForm, Link} from "@inertiajs/vue3";
import {route} from "ziggy-js";

const date = useDate()

const items = ref([])
const search = ref('')
const loading = ref(false)

const headerUris = [
    { title: 'ID', key: 'id' },
    { title: 'Address', key: 'address' },
    { title: 'Units', key: 'units' },
    { title: 'Valid', key: 'is_valid' },
    { title: 'Follow', key: 'follow' },
    { title: 'Created', key: 'created_at' },
]

const fetchUris = async () => {
    loading.value = true

    const { data } = await axios.get(route('uris.index'), {
        params: {
            search: search.value,
        }
    })

    items.value = data
    loading.value = false
}

// 🔹 загрузка при старте
onMounted(()=>{
    fetchUris()
})

// 🔹 простой debounce
let timeout = null
watch(search, () => {
    clearTimeout(timeout)
    timeout = setTimeout(fetchUris, 400)
})

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

    <v-row>
        <v-col lg="2">
            <v-text-field
                v-model="search"
                label="Поиск uris"
                variant="solo-filled"
                color="rose"
                density="comfortable"
                hide-details
                clearable
            />
        </v-col>
    </v-row>

    <v-row>
        <v-col>
            <v-data-table
                :headers="headerUris"
                :items="items"
                items-per-page="1000"
                fixed-header
                height="888px"
                density="compact"
                class="text-xs border rounded border-red-50"
                hover
            >
<!--                <template #item.address="{ item }">-->
<!--                    <a-->
<!--                        :href="item.address"-->
<!--                        target="_blank"-->
<!--                        class="text-xs text-green-400 inline-block"-->
<!--                    >-->
<!--                        {{ item.address }}-->
<!--                    </a>-->
<!--                </template>-->

<!--                <template #item.units="{ item }">-->
<!--                    <div v-for="unit in item.units" :key="unit.id">-->
<!--                        <Link :href="route('web.unit.show', unit.id)">-->
<!--                            {{ unit.name }}-->
<!--                        </Link>-->
<!--                    </div>-->
<!--                </template>-->

<!--                <template #item.created_at="{ item }">-->
<!--                        <span class="text-xs font-sans">-->
<!--                          {{ date.format(item.created_at, 'fullDate') }}-->
<!--                        </span>-->
<!--                    </template>-->
            </v-data-table>
        </v-col>
    </v-row>
</template>

<style scoped>

</style>
