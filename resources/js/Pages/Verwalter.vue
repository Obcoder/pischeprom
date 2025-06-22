<script setup>
import {useHead} from "@vueuse/head";
import {computed, onMounted, ref} from "vue";
import {useForm, Link} from "@inertiajs/vue3";
import axios from "axios";
import {route} from "ziggy-js";
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {useDate} from 'vuetify';
import {logo} from "@/Pages/Helpers/consts.js";
defineOptions({
    layout: VerwalterLayout,
})
const props = defineProps({
    title: String,
    goods: Object,
    actions: Object,
})
const date = useDate()

const tab = ref()
const tabsGeography = ref()
const tabsProducts = ref()

const brands = ref([])
const buildings = ref([])
const catalogs = ref([])
const categories = ref([])
const checks = ref([])
const cities = ref([])
const countries = ref([])
const entities = ref([])
const good = ref(null)
const goods = ref([])
const labels = ref([])
const products = ref([])
const purchases = ref([])
const regions = ref([])
const segments = ref([])
const units = ref([])
const uris = ref([])

let manufacturers = ref();
let listComponents = ref();
let listTelephones = ref();

const searchBrands = ref('')
const searchBuildings = ref('')
const searchCities = ref('')
const searchGoods = ref('')
const searchProducts = ref('')
const searchUnits = ref('')
let searchComponents = ref('');

const dialogFormBuilding = ref(false)
const dialogFormCheck = ref(false)
const dialogFormCity = ref(false)
const dialogFormUnit = ref(false)
const dialogFormUri = ref(false)

const selectedLabelsIDs = ref([])
const selectedCategoriesIDs = ref([])

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
const headerGoods = [
    {
        title: 'Avatar',
        key: 'ava_image',
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
const headerUnits = [
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
const headerUris = [
    {
        title: 'Address',
        key: 'address',
        align: 'start',
    },
    {
        title: 'Valid',
        key: 'is_valid',
        align: 'center',
    },
    {
        title: 'Follow',
        key: 'follow',
        align: 'center',
    },
    {
        title: 'Design',
        key: 'has_brilliant_foremost_design',
        align: 'center',
    },
    {
        title: 'Owners',
        key: 'owners',
        align: 'start',
    },
    {
        title: 'created_at',
        key: 'created_at',
        align: 'start',
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
//   C H E C K S
function indexChecks(){
    axios.get(route('checks.index')).then(function (response){
        checks.value = response.data
    }).catch(function (error){
        console.log(error)
    })
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
//   E N T I T I E S
function indexEntities(){
    axios.get(route('entities.index')).then(function (response){
        entities.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
//   G O O D S
function indexGoods(){
    axios.get(route('goods.index')).then(function (response){
        goods.value = response.data
        // Проверяем, есть ли элементы в goods и берём первый
        if (goods.value && goods.value.length > 0) {
            fetchGood(goods.value[0].id);
        }
    }).catch(function (error){
        console.log(error)
    })
}
function fetchGood(id){
    axios.get(route('good.fetch', id)).then(function (response){
        good.value = response.data
    }).catch(function (error){
        console.error(error)
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
    return products.value.filter(i => i.rus.toLowerCase().includes(search) &&
        (selectedCategoriesIDs.value.length === 0 || selectedCategoriesIDs.value.includes(i.category_id)))
})
// Функция выбора категорий
const toggleCategories = (categoryId) => {
    if (selectedCategoriesIDs.value.includes(categoryId)) {
        selectedCategoriesIDs.value = selectedCategoriesIDs.value.filter((id) => id !== categoryId);
    } else {
        selectedCategoriesIDs.value.push(categoryId);
    }
};
//   P U R C H A S E S
function indexPurchases(){
    axios.get(route('purchases.index')).then(function (response){
        purchases.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
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
// Фильтрация units
const filteredUnits = computed(() => {
    let filtered = units.value;

    // Фильтрация по поиску
    if (searchUnits.value) {
        const search = searchUnits.value.toLowerCase();
        filtered = filtered.filter(item =>
            item.name.toLowerCase().includes(search)
        );
    }

    // Фильтрация по labels
    if (selectedLabelsIDs.value.length > 0) {
        filtered = filtered.filter(unit => {
            // Предполагаем, что у unit есть массив labels с id
            const unitLabelIds = unit.labels?.map(label => label.id) || [];
            return selectedLabelsIDs.value.some(labelId =>
                unitLabelIds.includes(labelId)
            );
        });
    }

    return filtered;
});
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

//    B U I L D I N G  S T O R E
const formBuilding = useForm({
    city_id: null,
    address: null,
    postcode: null,
})
function storeBuilding(){
    formBuilding.post(route('web.building.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formBuilding.reset()
            indexBuildings(searchBuildings.value)
        },
        onError: (errors) => {
            console.error('Form errors:', errors); // Log validation or server errors
        },
    })
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
//    S T O R E  C H E C K
const formCheck = useForm({
    date: null,
    entity_id: null,
    amount: null,
})
function storeCheck(){
    formCheck.post(route('web.check.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formCheck.reset()
            indexChecks()
        },
    });
}
//     G O O D   S T O R E
const formGood = useForm({
    name: null,
    ava_image: null,
})
function storeGood(){
    formGood.post(route('web.good.store'), {
        replace: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formGood.reset()
            indexGoods()
        },
    });
}
//    P R O D U C T  S T O R E
const formProduct = useForm({
    rus: null,
    eng: null,
    zh: null,
    es: null,
    ar: null,
    po: null,
    de: null,
    fr: null,
    hi: null,
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
//    U N I T  S T O R E
const formUnit = useForm({
    name: null,
    uris: null,
    labels: null,
    buildings: null,
});
function storeUnit(){
    formUnit.post(route('web.unit.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formUnit.reset()
            indexUnits()
        },
    });
}
//    U R I  S T O R E
const formUri = useForm({
    address: null,
})
function storeUri(){
    formUri.post(route('web.uri.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formUri.reset();
            indexUris()
        },
    });
}


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
function apiIndexComponents(){
    axios.get(route('api.components')).then(function (response) {
        listComponents.value = response.data;
    }).catch(function (error) {
        console.log(error);
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
    indexChecks()
    indexCities()
    indexCountries()
    indexEntities()
    indexGoods()
    indexLabels()
    indexProducts()
    indexPurchases()
    indexRegions()
    indexSegments()
    indexUnits()
    indexUris()

    apiIndexTelephones();
    getManufacturers();
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

const toggleLabel = (labelId) => {
    if (selectedLabelsIDs.value.includes(labelId)) {
        selectedLabelsIDs.value = selectedLabelsIDs.value.filter((id) => id !== labelId);
    } else {
        selectedLabelsIDs.value.push(labelId)
    }
}

// Функция для генерации slug, если он отсутствует
const generateSlug = (name) => {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/(^-|-$)/g, "");
}

// Стили для активного состояния
const style = `
  .active {
    background-color: #0288d1; /* Цвет фона для активной категории */
    color: white; /* Цвет текста для активной категории */
  }
`;
</script>

<template>
    <v-theme-provider theme="dark">
        <v-container fluid>
            <v-row>
                <v-col lg="1"></v-col>
                <v-col lg="9">
                    <v-card>
                        <v-tabs v-model="tab">
                            <v-tab value="units">Units</v-tab>
                            <v-tab value="products">Products</v-tab>
                            <v-tab value="geography">Geography</v-tab>
                            <v-tab value="catalogs">Catalogs</v-tab>
                            <v-tab value="segments">Segments</v-tab>
                            <v-tab value="purchases">Закупка</v-tab>
                            <v-tab value="components">Components</v-tab>
                            <v-tab value="brands">Brands</v-tab>
                            <v-tab value="uris">Uris</v-tab>

                            <v-tab value="two">
                                Manufacturers
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
                                                                  hide-details
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
                                                                                          color="indigo-lighten-4"
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
                                                        <div v-for="(city, index) in filteredCities"
                                                             :key="city.id"
                                                             class="inline-block mr-1 p-1 rounded text-xs text-teal-200 hover:bg-teal-200 hover:text-black"
                                                        >
                                                            <div>
                                                                <a :href="city.yandexmapsgeo" target="_blank"
                                                                   class="inline-flex items-center justify-center mr-1 bg-teal-500 text-white rounded-full text-[6px] font-bold w-3 h-3"
                                                                >{{ index + 1 }}</a>
                                                                <Link :href="route('city.show', city.id)">
                                                                    {{city.name}}
                                                                </Link>
                                                            </div>
                                                            <div class="text-[7px] text-teal-500">{{city.region.name}}</div>
                                                        </div>
                                                    </v-sheet>
                                                </v-col>
                                            </v-row>
                                        </v-tabs-window-item>
                                        <v-tabs-window-item value="buildings">
                                            <v-row>
                                                <v-col cols="1">
                                                    <div class="flex flex-row justify-center">{{ filteredBuildings.length }}</div>
                                                </v-col>
                                                <v-col lg="3">
                                                    <v-text-field v-model="searchBuildings"
                                                                  label="Искать по адресам"
                                                                  variant="solo"
                                                                  density="compact"
                                                    ></v-text-field>
                                                </v-col>
                                                <v-col cols="1">
                                                    <v-btn text="+ 🛣️"
                                                           @click="dialogFormBuilding = !dialogFormBuilding"
                                                           variant="tonal"
                                                           density="compact"></v-btn>
                                                    <v-dialog v-model="dialogFormBuilding"
                                                              width="750"
                                                              >
                                                        <v-card>
                                                            <v-card-title>Form Building</v-card-title>
                                                            <v-card-text>
                                                                <v-form @submit.prevent>
                                                                    <v-row>
                                                                        <v-col>
                                                                            <v-text-field v-model="formBuilding.address"
                                                                                          label="Название"
                                                                                          variant="solo-filled"
                                                                                          density="comfortable"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-col>
                                                                            <v-autocomplete :items="cities"
                                                                                            :item-value="'id'"
                                                                                            :item-title="'name'"
                                                                                            v-model="formBuilding.city_id"
                                                                                            label="Населенный пункт"
                                                                                            variant="solo"
                                                                                            density="comfortable"
                                                                            ></v-autocomplete>
                                                                        </v-col>
                                                                        <v-col>
                                                                            <v-text-field v-model="formBuilding.postcode"
                                                                                          label="Postcode"
                                                                                          variant="solo-inverted"
                                                                                          density="compact"
                                                                                          color="lime"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                    </v-row>
                                                                </v-form>
                                                            </v-card-text>
                                                            <v-card-actions>
                                                                <v-divider vertical
                                                                           thickness="1"
                                                                           opacity="1"
                                                                ></v-divider>
                                                                <v-btn text="store"
                                                                       @click="storeBuilding"
                                                                       variant="elevated"
                                                                       density="compact"></v-btn>
                                                            </v-card-actions>
                                                        </v-card>
                                                    </v-dialog>
                                                </v-col>
                                            </v-row>
                                            <v-row>
                                                <v-col>
                                                    <v-sheet>
                                                        <div v-for="building in filteredBuildings"
                                                             class="hover:bg-red-950 hover:text-red-100"
                                                        >
                                                            <v-row>
                                                                <v-col lg="5">
                                                                    {{building.city.name}}
                                                                </v-col>
                                                                <v-col lg="7">
                                                                    {{building.address}}
                                                                </v-col>
                                                            </v-row>
                                                        </div>
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
                                    <v-tabs v-model="tabsProducts">
                                        <v-tab value="categories_products">Products</v-tab>
                                        <v-tab value="goods">Goods</v-tab>
                                    </v-tabs>
                                    <v-tabs-window v-model="tabsProducts">
                                        <v-tabs-window-item value="categories_products">
                                            <v-row>
                                                <v-col lg="3">
                                                    <v-list variant="outlined"
                                                            density="compact"
                                                            rounded
                                                    >
                                                        <v-list-item v-for="category in categories"
                                                                     :key="category.id"
                                                                     :class="{ 'active': selectedCategoriesIDs.includes(category.id) }"
                                                                     @click="toggleCategories(category.id)"
                                                                     size="x-small"
                                                                     class="ma-1 hover:bg-sky-700 hover:text-indigo-400"
                                                        >
                                                            <span>{{category.name}}</span>
                                                        </v-list-item>
                                                    </v-list>
                                                </v-col>
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
                                                                            <v-dialog width="750"
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
                                                                                                                  label="Продукт"
                                                                                                                  variant="outlined"
                                                                                                                  class="bg-pink-800"
                                                                                                                  hide-details
                                                                                                    ></v-text-field>
                                                                                                </v-col>
                                                                                                <v-col cols="5">
                                                                                                    <v-autocomplete :items="categories"
                                                                                                                    :item-value="'id'"
                                                                                                                    :item-title="'name'"
                                                                                                                    v-model="formProduct.category_id"
                                                                                                                    label="Category"
                                                                                                                    variant="outlined"
                                                                                                                    density="comfortable"
                                                                                                                    color="indigo"></v-autocomplete>
                                                                                                </v-col>
                                                                                            </v-row>
                                                                                            <v-row>
                                                                                                <v-col>
                                                                                                    <v-text-field v-model="formProduct.eng"
                                                                                                                  label="Product eng"
                                                                                                    ></v-text-field>
                                                                                                </v-col>
                                                                                            </v-row>
                                                                                            <v-row>
                                                                                                <v-col>
                                                                                                    <v-text-field v-model="formProduct.zh"
                                                                                                                  label="Product zh"
                                                                                                    ></v-text-field>
                                                                                                </v-col>
                                                                                            </v-row>
                                                                                            <v-row>
                                                                                                <v-col>
                                                                                                    <v-text-field v-model="formProduct.es"
                                                                                                                  label="Product es"
                                                                                                    ></v-text-field>
                                                                                                </v-col>
                                                                                            </v-row>
                                                                                            <v-row>
                                                                                                <v-col>
                                                                                                    <v-text-field v-model="formProduct.ar"
                                                                                                                  label="Product ar"
                                                                                                                  variant="solo-filled"
                                                                                                                  density="comfortable"
                                                                                                                  color="red"></v-text-field>
                                                                                                </v-col>
                                                                                            </v-row>
                                                                                            <v-row>
                                                                                                <v-col>
                                                                                                    <v-text-field v-model="formProduct.po"
                                                                                                                  label="Product po"
                                                                                                                  variant="solo-filled"
                                                                                                                  density="comfortable"
                                                                                                                  color="red"></v-text-field>
                                                                                                </v-col>
                                                                                            </v-row>
                                                                                            <v-row>
                                                                                                <v-col>
                                                                                                    <v-text-field v-model="formProduct.de"
                                                                                                                  label="Product de"
                                                                                                                  variant="solo"
                                                                                                                  density="comfortable"
                                                                                                                  color="cyan-lighten-3"></v-text-field>
                                                                                                </v-col>
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
                                        <v-tabs-window-item value="goods">
                                            <v-row>
                                                <v-col>
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
                                                                      width="900"
                                                            >
                                                                <template v-slot:activator="{ props: activatorProps }">
                                                                    <v-btn
                                                                        v-bind="activatorProps"
                                                                        text="Новый товар"
                                                                        block
                                                                    ></v-btn>
                                                                </template>
                                                                <template v-slot:default="{ isActive }">
                                                                    <v-card>
                                                                        <v-card-title>Form Good</v-card-title>
                                                                        <v-card-text>
                                                                            <v-form @submit.prevent>
                                                                                <v-row>
                                                                                    <v-col cols="12"
                                                                                    >
                                                                                        <v-autocomplete :items="products"
                                                                                                        :item-title="'rus'"
                                                                                                        :item-value="'id'"
                                                                                                        v-model="formGood.products"
                                                                                                        label="Products"
                                                                                                        placeholder="Выбери Product"
                                                                                                        density="compact"
                                                                                                        variant="outlined"
                                                                                                        color="red"
                                                                                                        multiple
                                                                                        ></v-autocomplete>
                                                                                    </v-col>
                                                                                </v-row>
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
                                                    <v-row>
                                                        <v-data-table :items="goods"
                                                                      :headers="headerGoods"
                                                                      :search="searchGoods"
                                                                      items-per-page="90"
                                                                      density="compact"
                                                                      hover="hover"
                                                        >
                                                            <template v-slot:item.ava_image="{item}">
                                                                <v-badge :content="item.id"
                                                                         color="teal"
                                                                >
                                                                    <v-img :src="item.ava_image || logo"
                                                                           @click="fetchGood(item.id)"
                                                                           alt="Avatar"
                                                                           width="50"
                                                                           height="50"
                                                                           cover
                                                                           class="rounded"
                                                                    ></v-img>
                                                                </v-badge>
                                                            </template>
                                                            <template v-slot:item.name="{ item }">
                                                                <Link :href="route('Ameise.good.show', { id: item.id, slug: item.slug || generateSlug(item.name) })"
                                                                      class="text-decoration-none"
                                                                >
                                                                    {{ item.name }}
                                                                </Link>
                                                            </template>
                                                        </v-data-table>
                                                    </v-row>
                                                </v-col>
                                                <v-col lg="3">
                                                    <v-sheet>
                                                        <v-row>
                                                            <v-col>
                                                                <span v-if="good && good.name">{{ good.name }}</span>
                                                                <span v-else>Загрузка...</span>
                                                            </v-col>
                                                        </v-row>
                                                    </v-sheet>
                                                </v-col>
                                            </v-row>
                                        </v-tabs-window-item>
                                    </v-tabs-window>
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
                                                          density="compact"
                                                          color="deep-orange-accent-3"
                                                          hide-details
                                            ></v-text-field>
                                        </v-col>
                                        <v-col lg="2">
                                            <v-btn text="+ Unit"
                                                   @click="dialogFormUnit = !dialogFormUnit"
                                                   variant="tonal"
                                                   density="compact"
                                                   color="deep-purple-darken-1"
                                            ></v-btn>
                                            <v-dialog v-model="dialogFormUnit"
                                                      width="900">
                                                <v-card>
                                                    <v-card-title>Form Unit</v-card-title>
                                                    <v-card-text>
                                                        <v-form @submit.prevent>
                                                            <v-container>
                                                                <v-row>
                                                                    <v-text-field v-model="formUnit.name"
                                                                                  label="Name"
                                                                                  variant="outlined"
                                                                                  density="comfortable"
                                                                    ></v-text-field>
                                                                </v-row>
                                                                <v-row>
                                                                    <v-col cols="9">
                                                                        <v-autocomplete v-model="formUnit.uris"
                                                                                        :items="uris"
                                                                                        :item-value="'id'"
                                                                                        :item-title="'address'"
                                                                                        label="Uris selected"
                                                                                        chips
                                                                                        multiple
                                                                        ></v-autocomplete>
                                                                    </v-col>
                                                                    <v-col cols="3">
                                                                        <v-btn text="+ uri"
                                                                               @click="dialogFormUri = !dialogFormUri"
                                                                        ></v-btn>
                                                                        <v-dialog v-model="dialogFormUri"
                                                                                  width="800"
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
                                                                                  :items="labels"
                                                                                  :item-value="'id'"
                                                                                  :item-title="'name'"
                                                                                  label="Labels"
                                                                                  multiple
                                                                        ></v-select>
                                                                    </v-col>
                                                                </v-row>
                                                                <v-row>
                                                                    <v-col>
                                                                        <v-autocomplete v-model="formUnit.buildings"
                                                                                        :items="listBuildings"
                                                                                        :item-title="formatBuildingTitle"
                                                                                        :item-value="'id'"
                                                                                        label="Buildings"
                                                                                        color="blue"
                                                                                        multiple
                                                                                        chips
                                                                        ></v-autocomplete>
                                                                    </v-col>
                                                                </v-row>
                                                            </v-container>
                                                        </v-form>
                                                    </v-card-text>
                                                    <v-card-actions class="justify-start">
                                                        <v-btn
                                                            text="Close"
                                                            @click="isActive.value = false"
                                                        ></v-btn>
                                                        <v-btn text="Сохранить"
                                                               @click="storeUnit"
                                                        ></v-btn>
                                                    </v-card-actions>
                                                </v-card>
                                            </v-dialog>
                                        </v-col>
                                        <v-sheet class="flex flex-row justify-normal flex-wrap">
                                            <v-btn v-for="label in labels"
                                                   :key="label.id"
                                                   v-model="selectedLabelsIDs"
                                                   :color="selectedLabelsIDs.includes(label.id) ? 'cyan' : 'blue-grey-darken-2'"
                                                   :class="{ 'active-btn': selectedLabelsIDs.includes(label.id) }"
                                                   @click="toggleLabel(label.id)"
                                                   size="x-small"
                                                   class="ma-1"
                                            >{{label.name}}</v-btn>
                                        </v-sheet>
                                    </v-row>
                                    <v-row>
                                        <v-card>
                                            <v-card-text>
                                                <v-data-table :items="filteredUnits"
                                                              :headers="headerUnits"
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
                                            <v-data-table :items="uris"
                                                          :headers="headerUris"
                                                          items-per-page="1000"
                                                          density="compact"
                                                          class="text-xs"
                                                          hover
                                            >
                                                <template v-slot:item.address="{item}">
                                                    <a :href="item.address" target="_blank"
                                                       class="text-xs text-green-400 inline-block"
                                                    >{{item.address}}</a>
                                                </template>
                                                <template v-slot:item.owners="{item}">
                                                    <div v-for="unit in item.owners">
                                                        <Link :href="route('web.unit.show', unit.id)">{{unit.name}}</Link>
                                                    </div>
                                                </template>
                                                <template v-slot:item.created_at="{item}">
                                                    <span class="text-xs font-sans">{{date.format(item.created_at, 'fullDate')}}</span>
                                                </template>
                                            </v-data-table>
                                        </v-col>
                                    </v-row>
                                </v-tabs-window-item>

<!--                                M A I L (не работает)!!!-->
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
<!--                                З А К У П К А-->
                                <v-tabs-window-item value="purchases">
                                    <v-row>
                                        <v-col></v-col>
                                        <v-col></v-col>
                                        <v-col lg="2">
                                            <v-btn text="+ check"
                                                   @click="dialogFormCheck = !dialogFormCheck"
                                                   variant="tonal"
                                                   density="compact"
                                                   color="deep-orange"
                                            ></v-btn>
                                            <v-dialog v-model="dialogFormCheck"
                                                      width="1001"
                                                      >
                                                <v-card>
                                                    <v-card-title>Form Check</v-card-title>
                                                    <v-card-text>
                                                        <v-form @submit.prevent>
                                                            <v-row>
                                                                <v-col lg="4">
                                                                    <input type="date"
                                                                           v-model="formCheck.date"></input>
                                                                </v-col>
                                                                <v-col lg="8">
                                                                    <v-autocomplete :items="entities"
                                                                                    :item-value="'id'"
                                                                                    :item-title="'name'"
                                                                                    v-model="formCheck.entity_id"
                                                                                    variant="solo"
                                                                                    density="comfortable"></v-autocomplete>
                                                                </v-col>
                                                            </v-row>
                                                            <v-row>
                                                                <v-col lg="4">
                                                                    <v-text-field v-model="formCheck.amount"
                                                                                  label="Amount"
                                                                                  variant="underlined"
                                                                                  density="comfortable"
                                                                                  color="deep-orange"></v-text-field>
                                                                </v-col>
                                                            </v-row>
                                                        </v-form>
                                                    </v-card-text>
                                                    <v-card-actions>
                                                        <v-divider vertical
                                                                   thickness="1"
                                                                   opacity="0.8"></v-divider>
                                                        <v-btn text="store"
                                                               @click="storeCheck"
                                                               variant="tonal"
                                                               density="compact"
                                                               color="blue-grey"></v-btn>
                                                    </v-card-actions>
                                                </v-card>
                                            </v-dialog>
                                        </v-col>
                                    </v-row>
                                    <v-row>
                                        <v-col>
                                            <v-list variant="tonal"
                                                    density="compact"
                                            >
                                                <v-list-item v-for="check in checks"
                                                             class="hover:text-orange-600 hover:bg-zinc-700"
                                                >
                                                    <Link :href="route('checks.show', check.id)">
                                                        <v-row>
                                                            <v-col>
                                                                {{check.date}}
                                                            </v-col>
                                                            <v-col lg="6">
                                                                <div>{{check.entity.name}}</div>
                                                                <div>{{check.entity.classification.name}}</div>
                                                            </v-col>
                                                            <v-col>
                                                                {{check.amount}}
                                                            </v-col>
                                                        </v-row>
                                                    </Link>
                                                </v-list-item>
                                            </v-list>
                                        </v-col>
                                        <v-col>
                                            <v-list variant="plain"
                                                    density="compact">
                                                <v-list-item v-for="purchase in purchases"
                                                             class="hover:text-orange-600 hover:bg-zinc-700"
                                                >
                                                    <v-row>
                                                        <v-col>
                                                            <span>{{date.format(purchase.date, 'fullDate')}}</span>
                                                        </v-col>
                                                        <v-col>
                                                            <span>{{purchase.amount}}</span>
                                                        </v-col>
                                                        <v-col>
                                                            <span>{{purchase.entity.name}}</span>
                                                        </v-col>
                                                    </v-row>
                                                </v-list-item>
                                            </v-list>
                                        </v-col>
                                    </v-row>
                                </v-tabs-window-item>
                            </v-tabs-window>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </v-theme-provider>
</template>

<style scoped>
.rounded-full {
    border-radius: 50%;
}
</style>
