<script setup>
import {useHead} from "@vueuse/head";
import {computed, onMounted, ref} from "vue";
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

const tab = ref()
const tabsGeography = ref()

const brands = ref([])
const buildings = ref([])
const catalogs = ref([])
const categories = ref([])
const cities = ref([])
const countries = ref([])
const labels = ref([])
const products = ref([])
const regions = ref([])
const segments = ref([])
const units = ref([])
const uris = ref([])

let manufacturers = ref();
let listEntities = ref();
let listChecks = ref();
let listComponents = ref();

const searchBrands = ref('')
const searchBuildings = ref('')
const searchCities = ref('')
const searchProducts = ref('')
const searchUnits = ref('')
let searchGoods = ref('');
let searchComponents = ref('');

const dialogFormCity = ref(false)
let dialogUri = ref(false);
let dialogFormProduct = ref(false);
let listTelephones = ref();

let showFormProduct = ref(false)

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
const headerCountries = [
    {
        title: 'Флаг',
        key: 'flag',
    },
    {
        title: 'name',
        key: 'name',
    },
]
const headersProducts = [
    {
        title: 'rus',
        key: 'rus',
    },
    {
        title: 'eng',
        key: 'eng',
    },
    {
        title: 'Category',
        key: 'category',
    },
]
const headerRegions = ref([
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
const headersUnits = [
    {
        title: 'name',
        key: 'name',
    },
    {
        title: 'Uris',
        key: 'uris',
    },
    {
        title: 'Stages',
        key: 'stages',
    },
]

//   B R A N D S
function indexBrands(){
    axios.get(route('brands.index')).then(function (response){
        brands.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
const filteredBrands = computed(() => {
    const searchRequest = searchBrands.value.toLowerCase()
    return brands.value.filter(item =>
        item.name.toLowerCase().includes(searchRequest)
    )
})
//   B U I L D I N G S
function indexBuildings(){
    axios.get(route('buildings.index')).then(function (response){
        buildings.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
const filteredBuildings = computed(()=>{
    const search = searchBuildings.value.toLowerCase();
    return buildings.value.filter(i => i.address.toLowerCase().includes(search))
})
//   C A T A L O G S
function indexCatalogs(){
    axios.get(route('catalogs.index')).then(function (response){
        catalogs.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
//   C A T E G O R I E S
function indexCategories(){
    axios.get(route('categories.index')).then(function (response) {
        // handle success
        categories.value = response.data
    }).catch(function (error) {
            // handle error
            console.error(error);
        })
        .finally(function () {
            // always executed
        });
}
//   C I T I E S
function indexCities(){
    axios.get(route('cities.index')).then(function (response){
        cities.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
const filteredCities = computed(()=>{
    const string = searchCities.value.toLowerCase()
    return cities.value.filter(i => i.name.toLowerCase().includes(string))
})
//   C O U N T R I E S
function indexCountries(){
    axios.get(route('countries.index')).then(function (response) {
        // handle success
        countries.value = response.data
    }).catch(function (error) {
            // handle error
            console.log(error);
        }).finally(function () {
            // always executed
        })
}
//   L A B E L S
function indexLabels(){
    axios.get(route('labels.index')).then(function (response) {
        labels.value = response.data
    }).catch(function (error) {
            console.log(error);
        });
}
//   P R O D U C T S
function indexProducts(){
    axios.get(route('products.index')).then(function (response) {
        // handle success
        products.value = response.data
    }).catch(function (error) {
            // handle error
            console.error(error);
        }).finally(function () {
            // always executed
        });
}
const filteredProducts = computed(()=>{
    const search = searchProducts.value.toLowerCase()
    return products.value.filter(i => i.rus.toLowerCase().includes(search))
})
//   R E G I O N S
function indexRegions(){
    axios.get(route('regions.index')).then(function (response) {
        regions.value = response.data
    }).catch(function (error) {
        console.log(error);
    })
}
//   S E G M E N T S
function indexSegments(){
    axios.get(route('segments.index')).then(function (response){
        segments.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
//   U N I T S
function indexUnits(){
    axios.get(route('units.index')).then(function (response) {
        // handle success
        units.value = response.data
    })
        .catch(function (error) {
            // handle error
            console.error(error);
        });
}
const filteredUnits = computed(()=>{
    const search = searchUnits.value.toLowerCase();
    return units.value.filter(item => item.name.toLowerCase().includes(search))
})
//   U R I S
function indexUris(){
    axios.get(route('uris.index')).then(function (response) {
        // handle success
        uris.value = response.data
    }).catch(function (error) {
            // handle error
            console.log(error);
        }).finally(function () {
            // always executed
        });
}

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

//    C I T Y  S T O R E
const formCity = useForm({
    name: null,
    population: null,
    wiki: null,
    region_id: null,
    yandexmapsgeo: null,
    twogis: null,
    latitude: null,
    longitude: null,
})
function storeCity(){
    formCity.post(route('web.city.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formCity.reset();
            indexCities(searchCities.value)
        },
    })
}
//    P R O D U C T  S T O R E
const formProduct = useForm({
    rus: null,
    eng: null,
    zh: null,
    es: null,
    category_id: null,
})
function storeProduct(){
    formProduct.post(route('web.product.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formProduct.reset();
            indexProducts(searchProducts);
        },
    })
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
    indexBrands()
    indexBuildings()
    indexCatalogs()
    indexCategories()
    indexCities()
    indexCountries()
    indexLabels()
    indexProducts()
    indexRegions()
    indexSegments()
    indexUnits()
    indexUris()

    apiIndexTelephones();
    getManufacturers();
    apiIndexEntities();
    apiIndexComponents();
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
    <v-theme-provider theme="dark">
        <v-container fluid>
            <v-row>
                <v-col cols="2"></v-col>
                <v-col lg="8">
                    <v-card>
                        <v-tabs v-model="tab">
                            <v-tab value="units">Units</v-tab>
                            <v-tab value="products">Products</v-tab>
                            <v-tab value="categories">Categories</v-tab>
                            <v-tab value="brands">Brands</v-tab>
                            <v-tab value="geography">Geography</v-tab>
                            <v-tab value="catalogs">Catalogs</v-tab>
                            <v-tab value="segments">Segments</v-tab>
                            <v-tab value="components">Components</v-tab>
                            <v-tab value="uris">Uris</v-tab>

                            <v-tab value="two">
                                Manufacturers
                            </v-tab>
                            <v-tab value="eleven">
                                Email work
                            </v-tab>
                        </v-tabs>

                        <v-card-text>
                            <v-tabs-window v-model="tab">
                                <!--               B R A N D S               -->
                                <v-tabs-window-item value="brands">
                                    <v-sheet>
                                        <v-text-field v-model="searchBrands"
                                                      label="Поиск по брендам"
                                                      variant="solo"
                                                      density="compact"
                                                      color="deep-purple"
                                        ></v-text-field>
                                        <div v-for="brand in filteredBrands"
                                             class="text-xs"
                                        >{{brand.name}}</div>
                                    </v-sheet>
                                </v-tabs-window-item>

                                <!-- + + + + + + +    C A T A L O G S   + + + + + + + -->
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
                                <!-- ------------- E N D   C A T A L O G S ---------------  -->

                                <!--###################   C A T E G O R I E S   #####################-->
                                <v-tabs-window-item value="categories">
                                    <v-list variant="outlined"
                                            density="compact"
                                    >
                                        <v-list-item v-for="category in categories">
                                            <span>{{category.name}}</span>
                                        </v-list-item>
                                    </v-list>
                                </v-tabs-window-item>
                                <!-- ############################################################### -->

                                <!--      C O N P O N E N T S       -->
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
                                                        <v-btn v-bind="activatorProps"
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

                                <!--      G E O G R A P H Y      -->
                                <v-tabs-window-item value="geography">
                                    <v-tabs v-model="tabsGeography">
                                        <v-tab value="cities">Cities</v-tab>
                                        <v-tab value="buildings">Buildings</v-tab>
                                        <v-tab value="regions">Regions</v-tab>
                                        <v-tab value="countries">Countries</v-tab>
                                    </v-tabs>
                                    <v-tabs-window v-model="tabsGeography">
                                        <v-tabs-window-item value="cities">
                                            <v-row>
                                                <v-col lg="3">
                                                    <v-text-field v-model="searchCities"
                                                                  label="Поиск по городам"
                                                                  variant="solo"
                                                                  density="compact"
                                                                  color="purple-lighten-4"
                                                    ></v-text-field>
                                                </v-col>
                                                <v-col cols="1">
                                                    <v-btn text="+ 🏰"
                                                           @click="dialogFormCity = !dialogFormCity"
                                                           variant="tonal"
                                                           density="compact"></v-btn>
                                                    <v-dialog v-model="dialogFormCity"
                                                              width="900"
                                                              >
                                                        <v-card>
                                                            <v-card-title>Form City</v-card-title>
                                                            <v-card-text>
                                                                <v-form @submit.prevent>
                                                                    <v-row>
                                                                        <v-col lg="9">
                                                                            <v-text-field v-model="formCity.wiki"
                                                                                          label="Wiki"
                                                                                          variant="outlined"
                                                                                          density="comfortable"
                                                                                          size="small"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                        <v-col lg="3">
                                                                            <v-text-field v-model="formCity.population"
                                                                                          label="Население"
                                                                                          variant="outlined"
                                                                                          density="comfortable"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-col lg="6">
                                                                            <v-text-field v-model="formCity.name"
                                                                                          label="Название"
                                                                                          variant="solo"
                                                                                          density="comfortable"
                                                                                          color="indigo-darken-4"
                                                                                          class="font-UnderdogRegular"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                        <v-col lg="6">
                                                                            <v-autocomplete :items="regions"
                                                                                            :item-title="'name'"
                                                                                            :item-value="'id'"
                                                                                            v-model="formCity.region_id"
                                                                                            label="Регион"
                                                                                            variant="filled"
                                                                                            density="comfortable"
                                                                                            color="purple"
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
                                                                            <v-text-field v-model="formCity.latitude"
                                                                                          label="Широта"
                                                                                          variant="outlined"
                                                                                          density="comfortable"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                        <v-col>
                                                                            <v-text-field v-model="formCity.longitude"
                                                                                          label="Долгота"
                                                                                          variant="outlined"
                                                                                          density="comfortable"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-col>
                                                                            <v-text-field v-model="formCity.twogis"
                                                                                          label="2GIS"
                                                                                          variant="solo"
                                                                                          density="compact"
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
                                                    </v-dialog>
                                                </v-col>
                                            </v-row>
                                            <v-row>
                                                <v-col>
                                                    <v-sheet>
                                                    <Link v-for="city in filteredCities"
                                                          :href="route('city.show', city.id)"
                                                          class="inline-block mr-2 text-xs text-slate-400"
                                                    >
                                                        {{city.name}}
                                                    </Link>
                                                    </v-sheet>
                                                </v-col>
                                            </v-row>
                                        </v-tabs-window-item>
                                        <v-tabs-window-item value="buildings">
                                            <v-row>
                                                <v-col>
                                                    <v-text-field v-model="searchBuildings"
                                                                  label="Искать по адресам"
                                                                  variant="solo"
                                                                  density="compact"
                                                    ></v-text-field>
                                                    <v-sheet>
                                                        <div v-for="building in filteredBuildings">{{building.address}}</div>
                                                    </v-sheet>
                                                </v-col>
                                            </v-row>
                                        </v-tabs-window-item>
                                        <v-tabs-window-item value="regions">
                                            <v-row>
                                                <v-col>
                                                    <v-data-table :items="regions"
                                                                  :headers="headerRegions"
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
                                        </v-tabs-window-item>
                                        <v-tabs-window-item value="countries">
                                            <v-row>
                                                <v-col>
                                                    <v-data-table :items="countries"
                                                                  :headers="headerCountries"
                                                                  items-per-page="47"
                                                                  density="compact"
                                                                  hover="hover"
                                                    >
                                                        <template v-slot:item.flag="{item}">
                                                            <v-img :src="item.flag"
                                                                   width="51"
                                                                   class="border border-1 border-gray-200"
                                                            ></v-img>
                                                        </template>
                                                    </v-data-table>
                                                </v-col>
                                            </v-row>
                                        </v-tabs-window-item>
                                    </v-tabs-window>
                                </v-tabs-window-item>

                                <v-tabs-window-item value="two">
                                    <v-data-table :items="manufacturers"
                                                  density="compact"
                                                  hover="hover"
                                    ></v-data-table>
                                </v-tabs-window-item>

                                <!--   P R O D U C T S   -->
                                <v-tabs-window-item value="products">
                                    <v-row>
                                        <v-col>
                                            <v-card flat>
                                                <v-card-text>
                                                    <v-data-table :items="filteredProducts"
                                                                  :headers="headersProducts"
                                                                  items-per-page="51"
                                                                  density="compact"
                                                                  hover="hover"
                                                    >
                                                        <template v-slot:top>
                                                            <v-row>
                                                                <v-col cols="9">
                                                                    <v-text-field v-model="searchProducts"
                                                                                  label="Filter products"
                                                                                  prepend-inner-icon="mdi-magnify"
                                                                                  variant="outlined"
                                                                                  density="compact"
                                                                                  clearable
                                                                    ></v-text-field>
                                                                </v-col>
                                                                <v-col cols="3">
                                                                    <v-dialog v-model="showFormProduct"
                                                                              width="750"
                                                                    >
                                                                        <template v-slot:activator="{props}">
                                                                            <v-btn text="+ product"
                                                                                   v-bind="props"
                                                                                   variant="tonal"
                                                                                   color="indigo"
                                                                            ></v-btn>
                                                                        </template>
                                                                        <v-card>
                                                                            <v-card-text>
                                                                                <v-form @submit.prevent>
                                                                                    <v-row>
                                                                                        <v-col cols="7">
                                                                                            <v-text-field v-model="formProduct.rus"
                                                                                                          label="Product"
                                                                                            ></v-text-field>
                                                                                        </v-col>
                                                                                        <v-col cols="5">
                                                                                            <v-autocomplete :items="categories"
                                                                                                            :item-value="'id'"
                                                                                                            :item-title="'name'"
                                                                                                            v-model="formProduct.category_id"
                                                                                                            label="Category"
                                                                                                            variant="filled"
                                                                                                            density="comfortable"
                                                                                                            color="indigo"></v-autocomplete>
                                                                                        </v-col>
                                                                                    </v-row>
                                                                                    <v-row>
                                                                                        <v-text-field v-model="formProduct.eng"
                                                                                                      label="Product eng"
                                                                                        ></v-text-field>
                                                                                    </v-row>
                                                                                    <v-row>
                                                                                        <v-text-field v-model="formProduct.zh"
                                                                                                      label="Product zh"
                                                                                        ></v-text-field>
                                                                                    </v-row>
                                                                                    <v-row>
                                                                                        <v-text-field v-model="formProduct.es"
                                                                                                      label="Product es"
                                                                                        ></v-text-field>
                                                                                    </v-row>
                                                                                </v-form>
                                                                            </v-card-text>
                                                                            <v-card-actions>
                                                                                <v-spacer></v-spacer>
                                                                                <v-divider vertical></v-divider>
                                                                                <v-btn
                                                                                    color="blue-darken-1"
                                                                                    variant="text"
                                                                                    @click="close"
                                                                                >
                                                                                    Cancel
                                                                                </v-btn>
                                                                                <v-divider vertical></v-divider>

                                                                                <v-btn text="store"
                                                                                       @click="storeProduct"
                                                                                       color="blue-darken-1"
                                                                                       variant="elevated"
                                                                                ></v-btn>
                                                                            </v-card-actions>
                                                                        </v-card>
                                                                    </v-dialog>
                                                                </v-col>
                                                            </v-row>
                                                        </template>
                                                        <template v-slot:item.rus="{item}">
                                                            <Link :href="route('product.show', item.id)"
                                                                  color="amber-accent-2"
                                                                  class="font-UnderdogRegular text-sm"
                                                            >
                                                                {{item.rus}}
                                                            </Link>
                                                        </template>
                                                        <template v-slot:item.category="{item}">
                                                            <span>{{item.category.name}}</span>
                                                        </template>
                                                    </v-data-table>
                                                </v-card-text>
                                            </v-card>
                                        </v-col>
                                    </v-row>
                                </v-tabs-window-item>

                                <!--      S E G M E N T S      -->
                                <v-tabs-window-item value="segments">
                                    <v-row>
                                        <v-col>
                                            <v-data-table :items="segments"></v-data-table>
                                        </v-col>
                                    </v-row>
                                </v-tabs-window-item>

                                <!--   U N I T S   -->
                                <v-tabs-window-item value="units">
                                    <v-row>
                                        <v-col lg="4">
                                            <v-text-field v-model="searchUnits"
                                                          label="Поиск по юнитам"
                                                          variant="solo"
                                                          density="compact"></v-text-field>
                                        </v-col>
                                    </v-row>
                                    <v-row>
                                        <v-card>
                                            <v-card-text>
                                                <v-data-table :items="filteredUnits"
                                                              :headers="headersUnits"
                                                              items-per-page="100"
                                                              density="compact"
                                                              class="text-xs"
                                                              hover
                                                >
                                                    <template v-slot:item.name="{item}">
                                                        <Link :href="route('web.unit.show', item.id)">
                                                            {{item.name}}
                                                        </Link>
                                                    </template>
                                                    <template v-slot:item.uris="{item}">
                                                        <a v-for="uri in item.uris"
                                                           :href="uri.address" target="_blank"
                                                           class="text-xs inline-block mr-1 text-yellow-200">{{uri.address}}</a>
                                                    </template>
                                                    <template v-slot:item.stages="{item}">
                                                        <v-chip v-for="stage in item.stages"
                                                                size="x-small"
                                                                color="teal-accent-4"
                                                        >{{stage.name}}</v-chip>
                                                    </template>
                                                </v-data-table>
                                            </v-card-text>
                                        </v-card>
                                    </v-row>
                                </v-tabs-window-item>

                                <!--   U R I S  -->
                                <v-tabs-window-item value="uris">
                                    <v-row>
                                        <v-col>
                                            <v-sheet>
                                                <a v-for="uri in uris"
                                                   :href="uri.address" target="_blank"
                                                   class="text-xs text-green-400 inline-block"
                                                >{{uri.address}}</a>
                                            </v-sheet>
                                        </v-col>
                                    </v-row>
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
            </v-row>
        </v-container>
    </v-theme-provider>
</template>
