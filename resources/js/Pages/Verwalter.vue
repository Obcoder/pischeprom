<script setup>
import {onMounted, ref} from "vue";
import {Head, useForm, Link, router} from "@inertiajs/vue3";
import axios from "axios";
import { mergeProps } from 'vue'
import NavLink from "@/Components/NavLink.vue";
const props = defineProps({
    title: String,
    goods: Object,
    uris: Object,
})
onMounted(()=>{
    headersGoods.value = [
        {
            title: 'name',
            key: 'name',
        },
    ];
    headersProducts.value = [
        {
            title: 'rus',
            key: 'rus',
        },
    ]

    getManufacturers();
    getProducts();
    getUnits();
    getUris();
    getCategories();
})
let manufacturers = ref();
let listProducts = ref();
let listUnits = ref();
let listUris = ref();
let listCategories = ref();
let headersGoods = ref([]);
let headersProducts = ref([]);
let searchUnits = ref('');
let searchProducts = ref('');
let tab = ref();
let selectedUris = ref([]);
let dialogUri = ref(false);
let dialogFormProduct = ref(false);

const formUnit = useForm({
    name: null,
    uris: null,
});
const formUri = useForm({
    address: null,
})
const formProduct = useForm({
    rus: null,
    eng: null,
    zh: null,
})

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
function getUris(){
    axios.get(route('api.uris')).then(function (response) {
        // handle success
        listUris.value = response.data;
    })
        .catch(function (error) {
            // handle error
            console.log(error);
        })
        .finally(function () {
            // always executed
        });
}
function getCategories(){
    axios.get(route('api.categories')).then(function (response) {
        // handle success
        listCategories.value = response.data;
    })
        .catch(function (error) {
            // handle error
            console.log(error);
        })
        .finally(function () {
            // always executed
        });
}

function storeUnit(){
    formUnit.uris = selectedUris.value;
    formUnit.post(route('api.units.store'), {
        replace: false,
        preserveState: false,
        preserveScroll: false,
        onSuccess: ()=> {
            formUnit.reset();
        },
    });
}
function storeUri(){
    formUri.post(route('api.uri.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formUri.reset();
        },
    });
}
function storeProduct(){
    formProduct.post(route('api.product.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formProduct.reset();
        },
    });
}
</script>

<template>
    <Head>
        <title>УТ</title>
    </Head>

    <v-container>
        <v-row>
            <v-col cols="2"></v-col>
            <v-col cols="8">
                <v-card>
                    <v-tabs
                        v-model="tab"
                        bg-color="primary"
                    >
                        <v-tab value="four">
                            Units
                        </v-tab>
                        <v-tab value="one">
                            Goods
                        </v-tab>
                        <v-tab value="two">
                            Manufacturers
                        </v-tab>
                        <v-tab value="three">
                            Products
                        </v-tab>
                        <v-tab value="five">
                            Uris
                        </v-tab>
                        <v-tab value="six">
                            Categories
                        </v-tab>
                    </v-tabs>

                    <v-card-text>
                        <v-tabs-window v-model="tab">
                            <!--     U N I T S     -->
                            <v-tabs-window-item value="four">
                                <v-row>
                                    <v-col cols="9">
                                        <v-text-field v-model="searchUnits"
                                                      label="Искать по Units"
                                                      variant="outlined"
                                                      class="mt-1 py-1 ring-0 focus:outline-none"
                                        ></v-text-field>
                                    </v-col>
                                    <v-col cols="3">
                                        <v-dialog
                                            transition="dialog-bottom-transition"
                                            width="auto"
                                        >
                                            <template v-slot:activator="{ props: activatorProps }">
                                                <v-btn
                                                    v-bind="activatorProps"
                                                    text="Новый Unit"
                                                    block
                                                ></v-btn>
                                            </template>

                                            <template v-slot:default="{ isActive }">
                                                <v-card min-width="600px">
                                                    <v-toolbar title="Unit form"></v-toolbar>

                                                    <v-card-text class="text-h2 pa-12">
                                                        <form @submit.prevent>
                                                            <v-container>
                                                                <v-row>
                                                                    <v-text-field v-model="formUnit.name"
                                                                                  label="Name"
                                                                                  variant="outlined"
                                                                    ></v-text-field>
                                                                </v-row>
                                                                <v-row>
                                                                    <v-col cols="9">
                                                                        <v-autocomplete
                                                                            v-model="selectedUris"
                                                                            :items="props.uris"
                                                                            :item-value="'id'"
                                                                            :item-title="'address'"
                                                                            label="Uris selected"
                                                                            chips
                                                                            multiple
                                                                        ></v-autocomplete>
                                                                    </v-col>
                                                                    <v-col cols="3">
                                                                        <v-btn
                                                                            class="my-2"
                                                                            text="+ uri"
                                                                            @click="dialogUri = true"
                                                                        ></v-btn>

                                                                        <v-dialog
                                                                            v-model="dialogUri"
                                                                            width="301"
                                                                        >
                                                                            <v-card>
                                                                                <v-toolbar title="FORM: Uri"></v-toolbar>

                                                                                <v-card-text>
                                                                                    <v-form @submit.prevent>
                                                                                        <v-row>
                                                                                            <v-text-field v-model="formUri.address"
                                                                                                          label="Uri address"
                                                                                                          variant="outlined"
                                                                                            ></v-text-field>
                                                                                        </v-row>
                                                                                        <v-row>
                                                                                            <v-col cols="4">
                                                                                                <v-btn
                                                                                                    text="store"
                                                                                                    block
                                                                                                    @click="storeUri"
                                                                                                ></v-btn>
                                                                                            </v-col>
                                                                                        </v-row>
                                                                                    </v-form>
                                                                                </v-card-text>
                                                                            </v-card>
                                                                        </v-dialog>
                                                                    </v-col>
                                                                </v-row>
                                                            </v-container>
                                                        </form>
                                                    </v-card-text>

                                                    <v-card-actions class="justify-end">
                                                        <v-btn
                                                            text="Close"
                                                            @click="isActive.value = false"
                                                        ></v-btn>
                                                        <v-btn text="Сохранить"
                                                               @click="storeUnit"
                                                        ></v-btn>
                                                    </v-card-actions>
                                                </v-card>
                                            </template>
                                        </v-dialog>
                                    </v-col>
                                </v-row>

                                <v-data-table :items="listUnits"
                                              :search="searchUnits"
                                              items-per-page="12"
                                              density="compact"
                                              hover="hover"
                                >
                                    <template v-slot:item.name="{ item }">
                                        <Link :href="route('unit.show', 'id')">
                                            {{ item.name }}
                                        </Link>
                                    </template>
                                </v-data-table>
                            </v-tabs-window-item>
                            <!--     E N D  U N I T S     -->

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

                            <!--          P R O D U C T S          -->
                            <v-tabs-window-item value="three">

                                <v-card
                                    flat
                                    color="grey"
                                >
                                    <v-data-table
                                        :headers="headersProducts"
                                        :items="listProducts"
                                        :search="searchProducts"
                                    >
                                        <template v-slot:top>
                                            <v-row>
                                                <v-col cols="9">
                                                    <v-text-field
                                                        class="py-2"
                                                        v-model="searchProducts"
                                                        label="Filter products"
                                                        prepend-inner-icon="mdi-magnify"
                                                        variant="outlined"
                                                    ></v-text-field>
                                                </v-col>
                                                <v-col cols="3">
                                                    <v-dialog
                                                        v-model="dialogFormProduct"
                                                        max-width="500px"
                                                    >
                                                        <template v-slot:activator="{ props }">
                                                            <v-btn
                                                                class="mb-2"
                                                                color="primary"
                                                                dark
                                                                v-bind="props"
                                                            >
                                                                New Item
                                                            </v-btn>
                                                        </template>

                                                        <v-card>
                                                            <v-card-text>
                                                                <v-container>
                                                                    <v-row>
                                                                        <v-text-field
                                                                            v-model="formProduct.rus"
                                                                            label="Product"
                                                                        ></v-text-field>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-text-field
                                                                            v-model="formProduct.eng"
                                                                            label="Product eng"
                                                                        ></v-text-field>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-text-field
                                                                            v-model="formProduct.zh"
                                                                            label="Product zh"
                                                                        ></v-text-field>
                                                                    </v-row>
                                                                </v-container>
                                                            </v-card-text>

                                                            <v-card-actions>
                                                                <v-spacer></v-spacer>
                                                                <v-btn
                                                                    color="blue-darken-1"
                                                                    variant="text"
                                                                    @click="close"
                                                                >
                                                                    Cancel
                                                                </v-btn>

                                                                <v-divider></v-divider>

                                                                <v-btn
                                                                    color="blue-darken-1"
                                                                    variant="text"
                                                                    @click="storeProduct"
                                                                >
                                                                    Store
                                                                </v-btn>
                                                            </v-card-actions>
                                                        </v-card>
                                                    </v-dialog>
                                                </v-col>
                                            </v-row>
                                        </template>
                                    </v-data-table>
                                </v-card>

                            </v-tabs-window-item>

                            <v-tabs-window-item value="five">
                                <v-data-table :items="listUris"
                                              density="compact"
                                              hover="hover"
                                ></v-data-table>
                            </v-tabs-window-item>

                            <v-tabs-window-item value="six">
                                <v-data-table :items="listCategories"
                                              density="compact"
                                              hover="hover"
                                ></v-data-table>
                            </v-tabs-window-item>

                        </v-tabs-window>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="2"></v-col>
        </v-row>
    </v-container>
</template>
