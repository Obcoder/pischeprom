<script setup>
import {useHead} from "@vueuse/head";
import {onMounted, ref} from "vue";
import {useForm, Link} from "@inertiajs/vue3";
import axios from "axios";
import {route} from "ziggy-js";
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
defineOptions({
    layout: VerwalterLayout,
})
const props = defineProps({
    title: String,
    goods: Object,
    actions: Object,
})

const categories = ref([])
const labels = ref([])

function indexCategories(){
    axios.get(route('categories.index')).then(function (response) {
        // handle success
        categories.value = response.data
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
        labels.value = response.data
    })
        .catch(function (error) {
            console.log(error);
        });
}
let listUris = ref();
function apiIndexUris(){
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

let listTelephones = ref();
function apiIndexTelephones(like){
    axios.get(route('api.telephones'), {
        params: {
            search: like,
        }
    }).then(function (response) {
        // handle success
        listTelephones.value = response.data;
    })
        .catch(function (error) {
            // handle error
            console.log(error);
        });
}

const headersUnits = ref([
    {
        title: 'name',
        key: 'name',
    },
    {
        title: 'URIs',
        key: 'uris',
    },
    {
        title: 'Labels',
        key: 'labels',
    },
    {
        title: 'Actions',
        key: 'products',
    }
]);
const headersRegions = ref([
    {
        title: 'Регион',
        key: 'name',
    },
    {
        title: 'Страна',
        key: 'country',
    },
    {
        title: 'Площадь',
        key: 'area',
    },
])
const headersCatalogs = ref([
    {
        title: 'name',
        key: 'name',
    },
    {
        title: 'uri',
        key: 'uri',
    },
    {
        title: 'rank',
        key: 'rank',
    },
])

let tab = ref();
let catalogs = ref();
let manufacturers = ref();
let listProducts = ref();
let listUnits = ref();
let listCountries = ref();
let listRegions = ref();
let listEntities = ref();
let listChecks = ref();
let listComponents = ref();
let searchUnits = ref('');
let searchGoods = ref('');
let searchComponents = ref('');
let headersGoods = ref([]);
let headersCountries = ref();
let headersComponents = ref();
let searchProducts = ref('');
let dialogUri = ref(false);
let dialogFormProduct = ref(false);

const formGood = useForm({
    name: null,
    ava_image: null,
})
const formComponent = useForm({
    name: null,
})

function apiIndexUnits(){
    axios.get(route('api.units')).then(function (response) {
        // handle success
        listUnits.value = response.data;
    })
        .catch(function (error) {
            // handle error
            console.log(error);
        });
}
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
function apiIndexRegions(){
    axios.get(route('api.regions')).then(function (response) {
        listRegions.value = response.data;
    }).catch(function (error) {
        console.log(error);
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
    formComponent.post(route('api.components.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formComponent.reset();
            apiIndexComponents();
        },
    });
}
// C A T A L O G S
function apiIndexCatalogs(){
    axios.get(route('api.catalogs')).then(function (response){
        catalogs.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

let email = ref('');
let message = ref('');
let successMessage = ref('');

async function sendMail() {
    try {
        const response = await axios.post('/api/mail', {
            'email': email.value,
            'message': message.value,
        });
        successMessage.value = response.data.message;
    } catch(error){
        console.log(error);
    }
}

onMounted(()=>{
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
    headersComponents.value = [
        {
            title: 'name',
            key: 'name',
        },
    ]

    indexCategories()

    apiIndexCatalogs();
    apiIndexUnits();
    apiIndexUris();
    apiIndexTelephones();
    getManufacturers();
    getProducts();
    getCountries();
    apiIndexLabels();
    apiIndexEntities();
    apiIndexComponents();
    apiIndexRegions();
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
            <v-col cols="2">
                <v-card>
                    <v-card-title>Categories</v-card-title>
                    <v-card-text>
                        <v-list variant="text"
                                density="compact"
                        >
                            <v-list-item v-for="category in categories">
                                <span>{{category.name}}</span>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col>
                <v-card>
                    <v-tabs
                        v-model="tab"
                        bg-color="primary"
                    >
                        <v-tab value="catalogs">
                            Catalogs
                        </v-tab>
                        <v-tab value="components">
                            Components
                        </v-tab>
                        <v-tab value="seven">
                            Countries
                        </v-tab>
                        <v-tab value="five">
                            Uris
                        </v-tab>
                        <v-tab value="two">
                            Manufacturers
                        </v-tab>
                        <v-tab value="eleven">
                            Email work
                        </v-tab>
                    </v-tabs>

                    <v-card-text>
                        <v-tabs-window v-model="tab">
                            <!--
                            + + + + + + +    C A T A L O G S   + + + + + + + +
                              -->
                            <v-tabs-window-item value="catalogs">
                                <v-data-table :items="catalogs"
                                              :headers="headersCatalogs"
                                              density="comfortable"
                                              hover="true"
                                >
                                    <template v-slot:item.uri="{item}">
                                        <a :href="item.uri" target="_blank">
                                            {{item.uri}}
                                        </a>
                                    </template>
                                </v-data-table>
                            </v-tabs-window-item>
                            <!--
                            -------------- E N D   C A T A L O G S ---------------------------
                              -->

                            <!--
                            ___________________________________________________________________________________
                            |
                            |           * * *   C O M P O N E N T S   * * *
                            |
                            -----------------------------------------------------------------------------------
                            -->
                            <v-tabs-window-item value="components">
                                <v-container>
                                    <v-row>
                                        <v-col cols="8">
                                            <v-text-field v-model="searchComponents"
                                                          label="search components"
                                                          variant="solo"
                                                          clearable
                                            ></v-text-field>
                                        </v-col>
                                        <v-col cols="4">
                                            <v-dialog transition="dialog-top-transition"
                                                      width="605"
                                            >
                                                <template v-slot:activator="{ props: activatorProps }">
                                                    <v-btn
                                                        v-bind="activatorProps"
                                                        text="Добавить"
                                                        block
                                                        variant="elevated"
                                                        color="purple"
                                                    ></v-btn>
                                                </template>
                                                <template v-slot:default="{ isActive }">
                                                    <v-card>
                                                        <v-card-title>Form Component</v-card-title>
                                                        <v-card-text>
                                                            <v-form @submit.prevent>
                                                                <v-row>
                                                                    <v-text-field v-model="formComponent.name"
                                                                                  label="Name"
                                                                                  variant="outlined"
                                                                    ></v-text-field>
                                                                </v-row>
                                                                <v-row>
                                                                    <v-row>
                                                                        <v-col></v-col>
                                                                        <v-col>
                                                                            <v-btn text="save"
                                                                                   variant="elevated"
                                                                                   @click="storeComponent"
                                                                                   color="purple-darken-4"
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
                                              items-per-page="27"
                                              density="comfortable"
                                              hover="hover"
                                >
                                </v-data-table>
                            </v-tabs-window-item>
                            <!--      E N D  C O M P O N E N T S      -->


                            <v-tabs-window-item value="two">
                                <v-data-table :items="manufacturers"
                                              density="compact"
                                              hover="hover"
                                ></v-data-table>
                            </v-tabs-window-item>

                            <v-tabs-window-item value="five">
                                <v-data-table :items="listUris"
                                              density="compact"
                                              hover="hover"
                                ></v-data-table>
                            </v-tabs-window-item>

                            <!--
                            _________________________________________________
                            |
                            |       G E O G R A P H Y
                            |________________________________________________
                            -->
                            <v-tabs-window-item value="seven">
                                <v-container>
                                    <v-row>
                                        <v-col>
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
                                        </v-col>
                                        <v-col>
                                            <v-data-table :items="listRegions"
                                                          :headers="headersRegions"
                                                          items-per-page="100"
                                                          density="compact"
                                                          hover="hover"
                                            >
                                                <template v-slot:item.country="{item}">
                                                    {{item.country.name}}
                                                </template>
                                            </v-data-table>
                                        </v-col>
                                    </v-row>
                                </v-container>
                            </v-tabs-window-item>

                            <v-tabs-window-item value="eleven">
                                <div class="max-w-md mx-auto mt-10 p-6 bg-white rounded-lg shadow-md border border-gray-200">
                                    <h1 class="text-2xl font-bold text-center text-gray-800 mb-4">📧 Отправить письмо</h1>
                                    <form @submit.prevent="sendMail" class="space-y-4">
                                        <div>
                                            <label for="email" class="block text-sm font-medium text-gray-700">Email:</label>
                                            <input
                                                type="email"
                                                v-model="email"
                                                id="email"
                                                placeholder="Введите ваш email"
                                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                                required
                                            />
                                        </div>
                                        <v-spacer></v-spacer>
                                        <div>
                                            <label for="message" class="block text-sm font-medium text-gray-700">Сообщение:</label>
                                            <textarea
                                                v-model="message"
                                                id="message"
                                                placeholder="Введите ваше сообщение"
                                                rows="4"
                                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                                required
                                            ></textarea>
                                        </div>
                                        <button
                                            type="submit"
                                            class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                        >
                                            Отправить
                                        </button>
                                    </form>
                                    <p v-if="successMessage" class="mt-4 text-green-600 text-center">{{ successMessage }}</p>
                                </div>
                            </v-tabs-window-item>

                        </v-tabs-window>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="1"></v-col>
        </v-row>
    </v-container>
</template>
