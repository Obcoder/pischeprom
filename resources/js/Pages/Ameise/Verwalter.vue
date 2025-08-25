<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {useHead} from "@vueuse/head";
import {computed, onMounted, ref} from "vue";
import axios from "axios";
import {route} from "ziggy-js";

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
                <v-sheet class="flex flex-row flex-wrap justify-start">
                    <div v-for="field in fields"
                         class="w-4 h-4 border border-slate-800 rounded text-center flex flex-column justify-center"
                    >
                        <span>{{field.title}}</span>
                        <div v-for="unit in field.units"
                             class="text-xs"
                        >
                            {{unit.name}}</div>
                    </div>
                </v-sheet>
            </v-col>
        </v-row>
    </v-container>
</template>
