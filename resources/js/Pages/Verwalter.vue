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

let tab = ref();

const brands = ref([])
const buildings = ref([])
const catalogs = ref([])
const categories = ref([])
const labels = ref([])
const products = ref([])
const units = ref([])
const uris = ref([])

let manufacturers = ref();
let listCountries = ref();
let listRegions = ref();
let listEntities = ref();
let listChecks = ref();
let listComponents = ref();

const searchBrands = ref('')
const searchBuildings = ref('')
const searchProducts = ref('')
const searchUnits = ref('')
let searchGoods = ref('');
let searchComponents = ref('');
let headersCountries = ref();
let headersComponents = ref();
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
const headersUnits = [
    {
        title: 'name',
        key: 'name',
    },
    {
        title: 'Uris',
        key: 'uris',
    },
]

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
function indexLabels(){
    axios.get(route('labels.index')).then(function (response) {
        labels.value = response.data
    }).catch(function (error) {
            console.log(error);
        });
}
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
    indexBrands()
    indexBuildings()
    indexCategories()
    indexLabels()
    indexProducts()
    indexUnits()

    apiIndexCatalogs();
    apiIndexUris();
    apiIndexTelephones();
    getManufacturers();
    getCountries();
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
    <v-theme-provider theme="dark">
        <v-container fluid>
            <v-row>
                <v-col cols="3"></v-col>
                <v-col lg="6">
                    <v-card>
                        <v-tabs v-model="tab">
                            <v-tab value="brands">Brands</v-tab>
                            <v-tab value="buildings">Buildings</v-tab>
                            <v-tab value="catalogs">Catalogs</v-tab>
                            <v-tab value="categories">Categories</v-tab>
                            <v-tab value="components">Components</v-tab>
                            <v-tab value="products">Products</v-tab>
                            <v-tab value="units">Units</v-tab>
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
                                <!--_________________________________________-->

                                <!--   B U I L D I N G S   -->
                                <v-tabs-window-item value="buildings">
                                    <v-text-field v-model="searchBuildings"
                                                  label="Искать по адресам"
                                                  variant="solo"
                                                  density="compact"
                                    ></v-text-field>
                                    <v-sheet>
                                        <div v-for="building in filteredBuildings">{{building.address}}</div>
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

                                <!--   P R O D U C T S   -->
                                <v-tabs-window-item value="products">
                                    <v-row>
                                        <v-col>
                                            <v-card flat
                                                    color="indigo"
                                            >
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
                                                    </v-data-table>
                                                </v-card-text>
                                            </v-card>
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
                                                              items-per-page="30"
                                                              density="compact"
                                                              class="text-xs"
                                                >
                                                    <template v-slot:item.uris="{item}">
                                                        <span v-for="uri in item.uris"
                                                              class="text-xs inline-block text-yellow-200">{{uri.address}}</span>
                                                    </template>
                                                </v-data-table>
                                            </v-card-text>
                                        </v-card>
                                    </v-row>
                                </v-tabs-window-item>

                                <!--   U R I S  -->
                                <v-tabs-window-item value="uris">
                                    <v-row>
                                        <v-sheet>
                                            <a v-for="uri in uris"
                                               :href="uri.address" target="_blank"
                                               class="text-xs text-stone-900 inline-block"
                                            >{{uri.address}}</a>
                                        </v-sheet>
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
