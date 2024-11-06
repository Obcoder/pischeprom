<script setup>
import {onMounted, ref} from "vue";
import {Head} from "@inertiajs/vue3";
import axios from "axios";
const props = defineProps({
    title: String,
    goods: Object,
})
onMounted(()=>{
    headersGoods.value = [
        {
            title: 'name',
            key: 'name',
        },
    ];

    getManufacturers();
    getProducts();
    getUnits();
})
let manufacturers = ref();
let listProducts = ref();
let listUnits = ref();
let headersGoods = ref([]);
let searchUnits = ref('');
let tab = ref();

function getManufacturers(){
    axios.get(route('api.manufacturers')).then(function (response) {
        // handle success
        manufacturers.value = response.data;
    })
        .catch(function (error) {
            // handle error
            console.log(error);
        })
        .finally(function () {
            // always executed
        });
}
function getProducts(){
    axios.get(route('api.products')).then(function (response) {
        // handle success
        listProducts.value = response.data;
    })
        .catch(function (error) {
            // handle error
            console.log(error);
        })
        .finally(function () {
            // always executed
        });
}
function getUnits(){
    axios.get(route('api.units')).then(function (response) {
        // handle success
        listUnits.value = response.data;
    })
        .catch(function (error) {
            // handle error
            console.log(error);
        })
        .finally(function () {
            // always executed
        });
}
</script>

<template>
    <Head>
        <title>УТ</title>
    </Head>

    <v-container>
        <v-row>
            <v-col>

                <v-card>
                    <v-tabs
                        v-model="tab"
                        bg-color="primary"
                    >
                        <v-tab value="one">
                            Goods
                        </v-tab>
                        <v-tab value="two">
                            Manufacturers
                        </v-tab>
                        <v-tab value="three">
                            Products
                        </v-tab>
                        <v-tab value="four">
                            Units
                        </v-tab>
                    </v-tabs>

                    <v-card-text>
                        <v-tabs-window v-model="tab">
                            <v-tabs-window-item value="one">
                                <v-data-table :items="goods"
                                              :headers="headersGoods"
                                              density="compact"
                                              hover="hover"
                                >
                                </v-data-table>
                            </v-tabs-window-item>

                            <v-tabs-window-item value="two">
                                <v-data-table :items="manufacturers"
                                              density="compact"
                                              hover="hover"
                                ></v-data-table>
                            </v-tabs-window-item>

                            <v-tabs-window-item value="three">
                                <v-data-table :items="listProducts"
                                              ></v-data-table>
                            </v-tabs-window-item>

                            <v-tabs-window-item value="four">
                                <v-text-field v-model="searchUnits"
                                ></v-text-field>
                                <v-data-table :items="listUnits"
                                              :search="searchUnits"
                                              density="compact"
                                              hover="hover"
                                ></v-data-table>
                            </v-tabs-window-item>

                        </v-tabs-window>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col></v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
