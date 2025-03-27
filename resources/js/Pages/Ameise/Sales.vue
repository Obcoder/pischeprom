<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {useDate} from "vuetify";
import {useForm} from "@inertiajs/vue3";
import {format} from "date-fns";
defineOptions({
    layout: VerwalterLayout,
})

const date = useDate()

let sales = ref();
function indexSales(){
    axios.get(route('api.sales.index')).then(function (response){
        sales.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
let entities = ref()
function indexEntities(){
    axios.get(route('api.entities')).then(function (response){
        entities.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
let formSale = useForm({
    date: null,
    entity_id: null,
    total: null,
})
function storeSale(){
    formSale.date = format(new Date(formSale.date), 'yyyy-MM-dd HH:mm:ss');
    formSale.post(route('api.sales.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formSale.reset()
            indexSales()
        },
    })
}

const headersSales = [
    {
        title: 'Created',
        key: 'created_at',
    },
    {
        title: 'Дата',
        key: 'date',
    },
    {
        title: 'Entity',
        key: 'entity_id',
    },
    {
        title: 'Сумма',
        key: 'total',
    },
]

let sale = ref();
function showGoods(id){
    axios.get(route('api.sales.show', id)).then(function (response){
        sale.value = response.data
    }).catch(function (error){
        console.log(error)
    });
}

onMounted(()=>{
    indexSales()
    indexEntities()
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col cols="1">
                <v-dialog width="1000">
                    <template v-slot:activator="{ props: activatorProps }">
                        <v-btn v-bind="activatorProps"
                               text="Новая продажа"
                        ></v-btn>
                    </template>
                    <template v-slot:default="{isActive}">
                        <v-card>
                            <v-card-title>Form Sale</v-card-title>
                            <v-card-text>
                                <v-form @submit.prevent>
                                    <v-row>
                                        <v-col>
                                            <v-autocomplete :items="entities"
                                                            :item-value="'id'"
                                                            :item-title="'name'"
                                                            v-model="formSale.entity_id"
                                                            density="compact"
                                                            variant="outlined"
                                                            chips
                                            ></v-autocomplete>
                                        </v-col>
                                    </v-row>
                                    <v-row>
                                        <v-col>
                                            <v-date-picker v-model="formSale.date"></v-date-picker>
                                        </v-col>
                                        <v-col>
                                            <v-text-field v-model="formSale.total"
                                                          label="Total"
                                                          placeholder="Сумма продажи"
                                                          density="comfortable"
                                                          variant="solo"
                                            ></v-text-field>
                                        </v-col>
                                    </v-row>
                                </v-form>
                            </v-card-text>
                            <v-card-actions>
                                <v-btn text="store"
                                       @click="storeSale"
                                       variant="elevated"
                                       density="comfortable"
                                       color="purple"
                                ></v-btn>
                            </v-card-actions>
                        </v-card>
                    </template>
                </v-dialog>
            </v-col>
            <v-col cols="6">
                <v-data-table :items="sales"
                              :headers="headersSales"
                              items-per-page="250"
                              density="compact"
                              hover="true"
                >
                    <template v-slot:item.created_at="{item}">
                        <span>{{date.format(item.created_at, 'fullDateWithWeekday')}}</span>
                    </template>
                    <template v-slot:item.entity_id="{item}">
                        {{item.entity.name}}
                    </template>
                    <template v-slot:item.total="{item}">
                        <span @click="showGoods(item.id)"
                              class="cursor-pointer"
                        >
                            {{item.total}}
                        </span>
                    </template>
                </v-data-table>
            </v-col>
            <v-col>
                <v-list>
                    <v-list-item v-for="good in sale.goods">
                        <v-row>
                            <v-col cols="8">
                                <span class="cursor-pointer text-sm font-sans">
                                    {{good.name}}
                                </span>
                            </v-col>
                            <v-col cols="1">
                                <span class="text-[11px]">
                                    {{good.pivot.price}}
                                </span>
                            </v-col>
                            <v-col cols="1">
                                <span>
                                    {{good.pivot.quantity}}
                                </span>
                            </v-col>
                            <v-col>
                                <span>
                                    {{good.pivot.total}}
                                </span>
                            </v-col>
                        </v-row>
                    </v-list-item>
                </v-list>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
