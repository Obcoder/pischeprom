<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {useHead} from "@vueuse/head";
import {computed, onMounted, ref} from "vue";
import axios from "axios";
import {route} from "ziggy-js";
import {useForm, Link} from "@inertiajs/vue3";

defineOptions({
    layout: VerwalterLayout,
})
const props = defineProps({
    title: String,
    entities: Object,
})

const fields = ref([])
const goods = ref([])
const sales = ref([])

//      E N T I T I E S
const filteredEntities = computed(()=>{
    const searchLike = searchEntity.value.toLowerCase()
    return props.entities.filter((entity) => entity.name.toLowerCase().includes(searchLike))
})
// E N D  E N T I T I E S



//     F I E L D S
function indexFields(){
    axios.get(route('fields.index')).then(function (response){
        fields.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
// E N D  F I E L D S



//      G O O D S
function indexGoods(){
    axios.get(route('goods.index')).then(function (response){
        goods.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
const headerGoods = ref([
    {
        key: 'name',
        title: 'Name',
        align: 'start',
        sortable: true,
    },
])
const searchEntity = ref('')
// E N D  G O O D S



//      S A L E S
function indexSales(){
    axios.get(route('sales.index')).then(function (response){
        sales.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
// E N D  S A L E S


onMounted(()=>{
    indexFields()
    indexGoods()
    indexSales()
})

useHead({
    title: `Управление торговлей`,
    meta: [
        {
            name: 'description',
            content: `Управление торговлей`,
        }
    ]
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col cols="1">
                <v-row>
                    <v-col cols="9"><span class="text-xs">Товаров</span></v-col>
                    <v-col cols="3">{{goods.length}}</v-col>
                </v-row>
            </v-col>
            <v-col cols="1">
                <v-row>
                    <v-col cols="9"><span class="text-xs">Продаж</span></v-col>
                    <v-col cols="3"><v-chip size="x-small">{{sales.length}}</v-chip></v-col>
                </v-row>
            </v-col>
            <v-col cols="12" md="3" lg="2">
                <Link :href="route('Ameise.gis')" class="gis-admin-entry">
                    <span>GIS CRM</span>
                    <strong>Карты и координаты</strong>
                    <small>2ГИС · Яндекс · routes</small>
                </Link>
            </v-col>
        </v-row>
        <v-row>
            <v-col cols="3">
                <v-row>
                    <v-col>
                        <v-text-field v-model="searchEntity"
                                      label="search"
                                      variant="solo"
                                      density="compact"
                                      hide-details></v-text-field>
                    </v-col>
                </v-row>
                <v-row>
                    <v-col>
                        <v-data-table :items="filteredEntities"
                                      items-per-page="100"
                                      :headers="headerGoods"
                                      fixed-header
                                      height="367px"
                                      density="compact"
                                      class="border rounded"
                                      hover
                        ></v-data-table>
                    </v-col>
                </v-row>
            </v-col>
            <v-col cols="9">
                <div class="flex flex-row">
                    <div v-for="field in fields"
                         class="p-6 border border-slate-800 rounded text-center"
                    >
                        <div class="border-b"><span>{{field.title}}</span></div>
                        <div v-for="unit in field.units"
                             class="text-xs"
                        >
                            <Link :href="route('web.unit.show', unit.id)">{{unit.name}}</Link>
                        </div>
                    </div>
                </div>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
.gis-admin-entry {
    display: grid;
    gap: 3px;
    min-height: 74px;
    padding: 12px 14px;
    border: 1px solid rgba(127, 29, 29, 0.16);
    border-radius: 18px;
    background:
        radial-gradient(circle at 86% 12%, rgba(14, 165, 233, 0.18), transparent 30%),
        linear-gradient(135deg, #fff7ed, #fef2f2);
    color: #450a0a;
    text-decoration: none;
    box-shadow: 0 12px 28px rgba(69, 10, 10, 0.08);
}

.gis-admin-entry span {
    color: #991b1b;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.gis-admin-entry strong {
    font-size: 15px;
    font-weight: 950;
    letter-spacing: -0.03em;
}

.gis-admin-entry small {
    color: #7f1d1d;
    font-size: 11px;
}
</style>
