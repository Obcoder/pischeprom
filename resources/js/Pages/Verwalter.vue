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
    headersUnits.value = [
        {
            title: 'name',
            key: 'name',
        },
        {
            title: 'labels',
            key: 'labels',
        },
    ];
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
    ];
    headersCountries.value = [
        {
            title: 'flag',
            key: 'flag',
        },
        {
            title: 'name',
            key: 'name',
        },
        {
            title: 'сodeTelefon',
            key: 'сodeTelefon',
        },
        {
            title: 'сodeISO',
            key: 'сodeISO',
        },
    ];
    headersEntities.value = [
        {
            title: 'name',
            key: 'name',
        },
        {
            title: 'entity',
            key: 'entityClass',
        },
    ]
    headersChecks.value = [
        {
            title: 'date',
            key: 'date',
        },
        {
            title: 'Entity',
            key: 'entity_id',
        },
        {
            title: 'Amount',
            key: 'amount',
        },
    ]
    headersComponents.value = [
        {
            title: 'name',
            key: 'name',
        },
    ]

    getManufacturers();
    getProducts();
    getUnits();
    getUris();
    getCategories();
    getCountries();
    apiIndexLabels();
    apiIndexEntities();
    apiIndexChecks();
    apiIndexComponents();
})
let tab = ref();
let manufacturers = ref();
let listProducts = ref();
let listUnits = ref();
let listUris = ref();
let listCategories = ref();
let listCountries = ref();
let listLabels = ref();
let listEntities = ref();
let listChecks = ref();
let listComponents = ref();
let searchUnits = ref('');
let searchGoods = ref('');
let searchEntities = ref('');
let searchComponents = ref('');
let headersUnits = ref('');
let headersGoods = ref([]);
let headersProducts = ref([]);
let headersCountries = ref();
let headersEntities = ref();
let headersChecks = ref();
let headersComponents = ref();
let searchProducts = ref('');
let selectedUris = ref([]);
let dialogUri = ref(false);
let dialogFormProduct = ref(false);

const formUnit = useForm({
    name: null,
    uris: null,
    labels: null,
});
const formUri = useForm({
    address: null,
})
const formProduct = useForm({
    rus: null,
    eng: null,
    zh: null,
    es: null,
})
const formGood = useForm({
    name: null,
    ava_image: null,
})
const formComponent = useForm({
    name: null,
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
function getCountries(){
    axios.get(route('api.countries')).then(function (response) {
        // handle success
        listCountries.value = response.data;
    })
        .catch(function (error) {
            // handle error
            console.log(error);
        })
        .finally(function () {
            // always executed
        });
}
function apiIndexLabels(){
    axios.get(route('api.labels')).then(function (response) {
        listLabels.value = response.data;
    })
        .catch(function (error) {
            console.log(error);
        });
}
function apiIndexEntities(like){
    axios.get(route('api.entities'), {
        params: {
            search: like,
        }
    }).then(function (response) {
        listEntities.value = response.data;
    })
        .catch(function (error) {
            console.log(error);
        });
}
function apiIndexChecks(){
    axios.get(route('api.checks')).then(function (response) {
        listChecks.value = response.data;
    })
        .catch(function (error) {
            console.log(error);
        });
}
function apiIndexComponents(){
    axios.get(route('api.components')).then(function (response) {
        listComponents.value = response.data;
    }).catch(function (error) {
            console.log(error);
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
            getProducts();
        },
    });
}
function storeGood(){
    formGood.post(route('api.good.store'), {
        replace: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formGood.reset();
        },
    });
}
function storeComponent(){
    formGood.post(route('api.component.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formComponent.reset();
            apiIndexComponents();
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
            <v-col cols="1"></v-col>
            <v-col cols="8">
                <v-card>
                    <v-tabs
                        v-model="tab"
                        bg-color="primary"
                    >
                        <v-tab value="four">
                            Units
                        </v-tab>
                        <v-tab value="three">
                            Products
                        </v-tab>
                        <v-tab value="one">
                            Goods
                        </v-tab>
                        <v-tab value="two">
                            Manufacturers
                        </v-tab>
                        <v-tab value="six">
                            Categories
                        </v-tab>
                        <v-tab value="seven">
                            Countries
                        </v-tab>
                        <v-tab value="five">
                            Uris
                        </v-tab>
                        <v-tab value="eight">
                            Entities
                        </v-tab>
                        <v-tab value="nine">
                            Checks
                        </v-tab>
                        <v-tab value="ten">
                            Components
                        </v-tab>
                    </v-tabs>

                    <v-card-text>
                        <v-tabs-window v-model="tab">

                            <!--          G O O D S          -->
                            <v-tabs-window-item value="one">
                                <v-row>
                                    <v-col cols="9">
                                        <v-text-field label="Поиск: Товары"
                                                      v-model="searchGoods"
                                                      variant="outlined"
                                                      class="mt-1 py-1"
                                        ></v-text-field>
                                    </v-col>
                                    <v-col cols="3">
                                        <v-dialog transition="dialog-top-transition"
                                                  width="auto"
                                        >
                                            <template v-slot:activator="{ props: activatorProps }">
                                                <v-btn
                                                    v-bind="activatorProps"
                                                    text="Новый товар"
                                                    block
                                                ></v-btn>
                                            </template>

                                            <template v-slot:default="{ isActive }">
                                                <v-card width="567"
                                                >
                                                    <v-card-title>Form Good</v-card-title>
                                                    <v-card-text>
                                                        <v-form @submit.prevent>
                                                            <v-row>
                                                                <v-text-field v-model="formGood.name"
                                                                              label="Good name"
                                                                              variant="outlined"
                                                                ></v-text-field>
                                                            </v-row>
                                                            <v-row>
                                                                <v-file-input v-model="formGood.ava_image"
                                                                              label="Good's avatar"
                                                                              chips
                                                                ></v-file-input>
                                                            </v-row>
                                                        </v-form>
                                                    </v-card-text>
                                                    <v-card-actions>
                                                        <v-spacer></v-spacer>
                                                        <v-divider></v-divider>

                                                        <v-btn text="save"
                                                               @click="storeGood"
                                                               variant="plain"
                                                               color="indigo-darken-4"
                                                        ></v-btn>
                                                    </v-card-actions>
                                                </v-card>
                                            </template>
                                        </v-dialog>
                                    </v-col>
                                </v-row>
                                <v-data-table :items="goods"
                                              :headers="headersGoods"
                                              :search="searchGoods"
                                              items-per-page="16"
                                              density="compact"
                                              hover="hover"
                                >
                                </v-data-table>
                            </v-tabs-window-item>
                            <!--          E N D  G O O D S          -->

                            <!--          U N I T S          -->
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
                                                <v-card min-width="700px">
                                                    <v-toolbar title="Unit form"></v-toolbar>

                                                    <v-card-text class="text-h2 pa-12">
                                                        <v-form @submit.prevent>
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
                                                                <v-row>
                                                                    <v-col cols="4">
                                                                        <v-select v-model="formUnit.labels"
                                                                                  :items="listLabels"
                                                                                  :item-value="'id'"
                                                                                  :item-title="'name'"
                                                                                  label="Labels"
                                                                                  multiple
                                                                        ></v-select>
                                                                    </v-col>
                                                                </v-row>
                                                            </v-container>
                                                        </v-form>
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
                                              :headers="headersUnits"
                                              :search="searchUnits"
                                              items-per-page="21"
                                              density="compact"
                                              hover="hover"
                                >
                                    <template v-slot:item.name="{ item }">
                                        <Link :href="route('unit.show', item.id)">
                                            {{ item.name }}
                                        </Link>
                                    </template>
                                    <template v-slot:item.labels="{item}">
                                        <v-chip v-for="(label, index) in item.labels">
                                            {{label.name}}
                                        </v-chip>
                                    </template>
                                </v-data-table>
                            </v-tabs-window-item>
                            <!--     E N D  U N I T S     -->

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
                                    <v-data-table :headers="headersProducts"
                                                  :items="listProducts"
                                                  :search="searchProducts"
                                                  items-per-page="24"
                                                  density="compact"
                                                  hover="hover"
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
                                                        clearable
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
                                                                <v-form @submit.prevent>
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
                                                                    <v-row>
                                                                        <v-text-field
                                                                            v-model="formProduct.es"
                                                                            label="Product es"
                                                                        ></v-text-field>
                                                                    </v-row>
                                                                </v-form>
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

                            <v-tabs-window-item value="seven">
                                <v-data-table :items="listCountries"
                                              :headers="headersCountries"
                                              items-per-page="20"
                                              density="compact"
                                              hover="hover"
                                >
                                    <template v-slot:item.flag="{item}">
                                        <v-img :src="item.flag"
                                               width="50"
                                               class="border border-1 border-gray-200"
                                        ></v-img>
                                    </template>
                                </v-data-table>
                            </v-tabs-window-item>

                            <v-tabs-window-item value="eight">
                                <v-data-table :items="listEntities"
                                              :headers="headersEntities"
                                              density="compact"
                                              hover="hover"
                                >
                                    <template v-slot:top>
                                        <v-row>
                                            <v-col cols="9">
                                                <v-text-field v-model="searchEntities"
                                                              @input="apiIndexEntities(searchEntities)"
                                                              placeholder="search"
                                                ></v-text-field>
                                            </v-col>
                                            <v-col cols="3"></v-col>
                                        </v-row>
                                    </template>
                                </v-data-table>
                            </v-tabs-window-item>

                            <!--          C H E C K S          -->
                            <v-tabs-window-item value="nine">
                                <v-data-table :items="listChecks"
                                              :headers="headersChecks"
                                              items-per-page="25"
                                              density="compact"
                                              hover="hover"
                                >
                                    <template v-slot:item.entity_id="{item}">
                                        {{item.entity.name}}
                                    </template>
                                </v-data-table>
                            </v-tabs-window-item>
                            <!--          E N D  C H E C K S          -->

                            <!--          C O M P O N E N T S          -->
                            <v-tabs-window-item value="ten">
                                <v-container>
                                    <v-row>
                                        <v-col>
                                            <v-text-field v-model="searchComponents"
                                                          label="search components"
                                                          variant="solo"
                                            ></v-text-field>
                                        </v-col>
                                        <v-col>
                                            <v-dialog transition="dialog-top-transition"
                                                      width="301"
                                            >
                                                <template v-slot:activator="{ props: activatorProps }">
                                                    <v-btn
                                                        v-bind="activatorProps"
                                                        text="Добавить"
                                                        block
                                                    ></v-btn>
                                                </template>

                                                <template v-slot:default="{ isActive }">
                                                    <v-card>
                                                        <v-card-title>Component Form</v-card-title>
                                                        <v-card-text>
                                                            <v-form @submit.prevent>
                                                                <v-row>
                                                                    <v-text-field v-model="formComponent.name"
                                                                                  label="Name"
                                                                    ></v-text-field>
                                                                </v-row>
                                                                <v-row>
                                                                    <v-row>
                                                                        <v-col></v-col>
                                                                        <v-col>
                                                                            <v-btn text="save"
                                                                                   variant="outlined"
                                                                                   @click="storeComponent"
                                                                            ></v-btn>
                                                                        </v-col>
                                                                        <v-col></v-col>
                                                                    </v-row>
                                                                </v-row>
                                                            </v-form>
                                                        </v-card-text>
                                                    </v-card>
                                                </template>
                                            </v-dialog>
                                        </v-col>
                                    </v-row>
                                </v-container>
                                <v-data-table :items="listComponents"
                                              :headers="headersComponents"
                                              :search="searchComponents"
                                              items-per-page="25"
                                              density="compact"
                                              hover="hover"
                                >
                                </v-data-table>
                            </v-tabs-window-item>

                        </v-tabs-window>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="1"></v-col>
        </v-row>
    </v-container>
</template>
