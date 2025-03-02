<script setup>
import {onMounted, ref} from "vue";
import {Head, useForm, Link, router} from "@inertiajs/vue3";
import axios from "axios";
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
defineOptions({
    layout: VerwalterLayout,
})
const props = defineProps({
    title: String,
    goods: Object,
    actions: Object,
})

let listLabels = ref();
function apiIndexLabels(){
    axios.get(route('api.labels')).then(function (response) {
        listLabels.value = response.data;
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
let searchTelephones = ref();
let showFormTelephone = ref(false);
const formTelephone = useForm({
    number: null,
})
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
const headersProducts = [
    {
        title: 'rus',
        key: 'rus',
    },
];
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
]);

onMounted(()=>{
    headersGoods.value = [
        {
            title: 'name',
            key: 'name',
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
    headersChecks.value = [
        {
            title: 'id',
            key: 'id',
        },
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

    apiIndexUnits();
    apiIndexUris();
    apiIndexTelephones();

    getManufacturers();
    getProducts();
    getCategories();
    getCountries();
    apiIndexLabels();
    apiIndexEntities();
    apiIndexChecks();
    apiIndexComponents();
    apiIndexRegions();
    apiIndexCities();
})
let tab = ref();
let manufacturers = ref();
let listProducts = ref();
let listUnits = ref();
let listCategories = ref();
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
let headersChecks = ref();
let headersComponents = ref();
let searchProducts = ref('');
let dialogUri = ref(false);
let dialogFormProduct = ref(false);

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
const formCheck = useForm({
    date: null,
    entity_id: null,
    amount: null,
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


function storeCheck(){
    formCheck.post(route('api.checks.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formCheck.reset();
            apiIndexChecks();
        },
    });
}

//   * * *   C I T I E S   * * *
let listCities = ref();
let searchCitiesLike = ref();
const headersCities = [
    {
        title: 'name',
        key: 'name',
    },
    {
        title: 'population',
        key: 'population',
    },
    {
        title: 'wiki',
        key: 'wiki',
    },
    {
        title: 'Регион',
        key: 'region_id',
    },
    {
        title: 'Maps',
        key: 'yandexmapsgeo',
    },
]
let showFormCity = ref(false)
const formCity = useForm({
    name: null,
    population: null,
    wiki: null,
    region_id: null,
    yandexmapsgeo: null,
    twogis: null,
})
function apiIndexCities(like){
    axios.get(route('api.cities'), {
        params: {
            search: like,
        }
    }).then(function (response){
        listCities.value = response.data;
    }).catch(function (error){
        console.log(error);
    });
}
function storeCity(){
    formCity.post(route('api.city.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formCity.reset();
            apiIndexCities();
        },
    })
}

//   E N D  C I T I E S

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

</script>

<template>
    <Head>
        <title>Gb</title>
    </Head>

    <v-container fluid>
        <v-row>
            <v-col cols="1"></v-col>
            <v-col cols="10">
                <v-card>
                    <v-tabs
                        v-model="tab"
                        bg-color="primary"
                    >
                        <v-tab value="four">
                            Units
                        </v-tab>
                        <v-tab value="components">
                            Components
                        </v-tab>
                        <v-tab value="three">
                            Products
                        </v-tab>
                        <v-tab value="six">
                            Categories
                        </v-tab>
                        <v-tab value="goods">
                            Goods
                        </v-tab>
                        <v-tab value="seven">
                            Countries
                        </v-tab>
                        <v-tab value="cities">
                            Cities
                        </v-tab>
                        <v-tab value="five">
                            Uris
                        </v-tab>
                        <v-tab value="telephones">
                            Telephones
                        </v-tab>
                        <v-tab value="nine">
                            Checks
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
                            * * * * * * * * * * * * * * * * * * * * * * * * *
                            _________________________________________________
                            |                                               |
                            |                                               |
                            |                   U N I T S                   |
                            |                                               |
                            |                                               |
                            -------------------------------------------------
                            * * * * * * * * * * * * * * * * * * * * * * * * *
                                      -->
                            <v-tabs-window-item value="four">
                                <v-row>
                                    <v-col cols="9">
                                        <v-text-field v-model="searchUnits"
                                                      label="Искать по Units"
                                                      variant="outlined"
                                                      class="mt-1 py-1 ring-0 focus:outline-none"
                                        ></v-text-field>
                                    </v-col>
                                </v-row>

                                <v-data-table :items="listUnits"
                                              :headers="headersUnits"
                                              :search="searchUnits"
                                              items-per-page="101"
                                              density="comfortable"
                                              hover="hover"
                                >
                                    <template v-slot:item.name="{ item }">
                                        <Link :href="route('unit.show', item.id)"
                                              class="font-Sowjetschablone"
                                        >
                                            {{ item.name }}
                                        </Link>
                                    </template>
                                    <template v-slot:item.labels="{item}">
                                        <v-chip v-for="(label, index) in item.labels"
                                        >
                                            {{label.name}}
                                        </v-chip>
                                    </template>
                                    <template v-slot:item.uris="{item}">
                                        <a v-for="uri in item.uris"
                                           :href="uri.address"
                                           target="_blank"
                                           class="text-sm text-gray-600"
                                           >{{uri.address}}</a>
                                    </template>
                                    <template v-slot:item.products="{item}">
                                        <div v-for="product in item.products" :key="product.id">
                                            <span class="mr-1">
                                                {{product.pivot.action}}
                                            </span>
                                            <span >
                                                {{product.rus}}
                                            </span>
                                        </div>
                                    </template>
                                </v-data-table>
                            </v-tabs-window-item>
                            <!--     E N D  U N I T S     -->

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


                            <!--          G O O D S          -->
                            <v-tabs-window-item value="goods">
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
                                        <template v-slot:item.rus="{item}">
                                            <Link :href="route('product.show', item.id)"
                                                  class="text-blue-950"
                                            >
                                                {{item.rus}}
                                            </Link>
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

                            <!--
                            _____________________________________________________
                            |                                                   |
                            |           T E L E P H O N E S  T A B              |
                            |                                                   |
                            -----------------------------------------------------
                            -->

                            <v-tabs-window-item value="telephones">
                                <v-row>
                                    <v-text-field v-model="searchTelephones"
                                                  @input="apiIndexTelephones(searchTelephones)"
                                                  density="comfortable"
                                                  label="search Telephones"
                                                  variant="solo"
                                    ></v-text-field>
                                </v-row>
                                <v-row>
                                    <v-list>
                                        <v-list-item v-for="telephone in listTelephones">
                                            <span>{{telephone.number}}</span>
                                        </v-list-item>
                                    </v-list>
                                </v-row>
                            </v-tabs-window-item>

                            <!--
                            |----------------------------------------------------|
                            |           E N D  T E L E P H O N E S               |
                            |____________________________________________________|
                            -->

                            <v-tabs-window-item value="six">
                                <v-data-table :items="listCategories"
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

                            <!--
                            * * * * *     C I T I E S     * * * * *
                            -->

                            <v-tabs-window-item value="cities">
                                <v-data-table :items="listCities"
                                              :headers="headersCities"
                                              items-per-page="200"
                                              density="compact"
                                              hover="hover"
                                >
                                    <template v-slot:top>
                                        <v-row>
                                            <v-col>
                                                <v-text-field v-model="searchCitiesLike"
                                                              @input="apiIndexCities(searchCitiesLike)"
                                                              label="Поиск городов по like"
                                                              variant="underlined"
                                                              density="compact"
                                                              color="blue"
                                                              class="my-3"
                                                ></v-text-field>
                                            </v-col>
                                            <v-col>
                                                <v-btn text="+ city"
                                                       v-model="showFormCity"
                                                       @click="showFormCity = !showFormCity"
                                                ></v-btn>
                                                <v-dialog v-model="showFormCity"
                                                          width="767"
                                                >
                                                    <template v-slot:default="{ isActive }">
                                                        <v-card>
                                                            <v-card-title>Form City</v-card-title>
                                                            <v-card-text>
                                                                <v-form @submit.prevent>
                                                                    <v-row>
                                                                        <v-text-field v-model="formCity.wiki"
                                                                                      label="Wiki"
                                                                                      variant="outlined"
                                                                                      density="comfortable"
                                                                        ></v-text-field>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-col cols="8">
                                                                            <v-text-field v-model="formCity.name"
                                                                                          label="Название"
                                                                                          variant="outlined"
                                                                                          density="comfortable"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                        <v-col cols="4">
                                                                            <v-text-field v-model="formCity.population"
                                                                                          label="Население"
                                                                                          variant="outlined"
                                                                                          density="comfortable"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-col>
                                                                            <v-autocomplete :items="listRegions"
                                                                                            :item-title="'name'"
                                                                                            :item-value="'id'"
                                                                                            label="Регион"
                                                                                            density="comfortable"
                                                                                            color="purple"
                                                                                            v-model="formCity.region_id"
                                                                            ></v-autocomplete>
                                                                        </v-col>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-col>
                                                                            <v-text-field v-model="formCity.yandexmapsgeo"
                                                                                          label="yandex.ru/maps/geo/"
                                                                                          variant="solo"
                                                                                          density="comfortable"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-col>
                                                                            <v-text-field v-model="formCity.twogis"
                                                                                          label="2GIS"
                                                                                          variant="solo"
                                                                                          density="comfortable"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                    </v-row>
                                                                </v-form>
                                                            </v-card-text>
                                                            <v-card-actions>
                                                                <v-btn @click="storeCity"
                                                                       text="сохранить"
                                                                       variant="elevated"
                                                                       color="green">
                                                                </v-btn>
                                                            </v-card-actions>
                                                        </v-card>
                                                    </template>
                                                </v-dialog>
                                            </v-col>
                                        </v-row>
                                    </template>
                                    <template v-slot:item.name="{item}">
                                        <Link :href="route('city.show', item.id)">
                                            <span>{{item.name}}</span>
                                        </Link>
                                    </template>
                                    <template v-slot:item.yandexmapsgeo="{item}">
                                        <a :href="item.yandexmapsgeo"
                                           target="_blank"
                                           class="mx-3"
                                        >
                                            yandex</a>
                                        <a :href="item.twogis"
                                           target="_blank"
                                           class="mx-3"
                                           >
                                            2gis
                                        </a>
                                    </template>
                                    <template v-slot:item.region_id="{item}">
                                        {{item.region.name}}
                                    </template>
                                </v-data-table>
                            </v-tabs-window-item>

                            <!--
                            * * * * *     E N D  C I T I E S     * * * * *
                            -->

                            <!--          C H E C K S          -->
                            <v-tabs-window-item value="nine">
                                <v-row>
                                    <v-col cols="7">
                                        <v-menu>
                                            <v-date-picker></v-date-picker>
                                        </v-menu>
                                    </v-col>
                                    <v-col cols="5">
                                        <v-dialog transition="dialog-top-transition"
                                                  width="602"
                                        >
                                            <template v-slot:activator="{ props: activatorProps }">
                                                <v-btn
                                                    v-bind="activatorProps"
                                                    text="+ check"
                                                    block
                                                ></v-btn>
                                            </template>

                                            <template v-slot:default="{ isActive }">
                                                <v-card>
                                                    <v-card-title>Check form</v-card-title>
                                                    <v-card-text>
                                                        <v-form @submit.prevent>
                                                            <v-row>
                                                                <v-col>
                                                                    <v-select :items="listEntities"
                                                                              :item-title="'name'"
                                                                              :item-value="'id'"
                                                                              v-model="formCheck.entity_id"
                                                                              label="Сущность"
                                                                              variant="outlined"
                                                                    ></v-select>
                                                                </v-col>
                                                            </v-row>
                                                            <v-row>
                                                                <v-col>
                                                                    <input type="date" v-model="formCheck.date"
                                                                    >
                                                                </v-col>
                                                                <v-col>
                                                                    <v-text-field v-model="formCheck.amount"
                                                                                  label="Amount"
                                                                    ></v-text-field>
                                                                </v-col>
                                                            </v-row>
                                                            <v-row>
                                                                <v-col></v-col>
                                                                <v-col></v-col>
                                                                <v-col>
                                                                    <v-btn text="save"
                                                                           variant="outlined"
                                                                           @click="storeCheck"
                                                                    ></v-btn>
                                                                </v-col>
                                                            </v-row>
                                                        </v-form>
                                                    </v-card-text>
                                                </v-card>
                                            </template>
                                        </v-dialog>
                                    </v-col>
                                </v-row>
                                <v-data-table :items="listChecks"
                                              :headers="headersChecks"
                                              items-per-page="25"
                                              density="compact"
                                              hover="hover"
                                >
                                    <template v-slot:item.entity_id="{item}">
                                        {{item.entity.name}}
                                    </template>
                                    <template v-slot:item.amount="{item}">
                                        <Link :href="route('check.show', item.id)">
                                            {{item.amount}}
                                        </Link>
                                    </template>
                                </v-data-table>
                            </v-tabs-window-item>
                            <!--          E N D  C H E C K S          -->

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
