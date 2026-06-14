<script setup>
import {useHead} from "@vueuse/head";
import {computed, onMounted, ref, watch} from "vue";
import {useForm, Link} from "@inertiajs/vue3";
import axios from "axios";
import {route} from "ziggy-js";
import {useDate} from 'vuetify';
import {logo} from "@/Pages/Helpers/consts.js";
import {format} from "date-fns";
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
defineOptions({
    layout: VerwalterLayout,
})

import CommoditiesPage from '@/Components/Dictionaries/Commodities/CommoditiesPage.vue';
import Categories from '@/Components/Dictionaries/Categories.vue';
import CitiesPage from '@/Components/Geography/Cities/CitiesPage.vue';
import EmailsPage from '@/Components/Contacts/Emails/EmailsPage.vue';
import Entities from "@/Components/Dictionaries/Entities/Entities.vue";
import Goods from "@/Components/Dictionaries/Goods.vue";
import GrossbuchSales from '@/Components/Grossbuch/GrossbuchSales.vue';
import Industries from "@/Components/Dictionaries/Industries.vue";
import MailMessagesPage from '@/Components/Contacts/Emails/MailMessagesPage.vue';
import Products from '@/Components/Dictionaries/Products.vue';
import Purchases from "@/Pages/Purchases/Purchases.vue";
import TelephonePage from "@/Components/Dictionaries/telephones/TelephonePage.vue";
import Units from '@/Components/Dictionaries/Units.vue';
import Uris from '@/Components/Dictionaries/Uris.vue';

import { useUnits } from '@/Composables/useUnits.js'
const {
    units,
    loadingUnits,
    indexUnits,
} = useUnits()

const date = useDate()

const GROSSBUCH_TAB_KEY = 'ameise:grossbuch:tab'
const GROSSBUCH_CONTACTS_TAB_KEY = 'ameise:grossbuch:contacts-tab'
const GROSSBUCH_GEOGRAPHY_TAB_KEY = 'ameise:grossbuch:geography-tab'
const GROSSBUCH_PRODUCTS_TAB_KEY = 'ameise:grossbuch:products-tab'
const GROSSBUCH_SEGMENTS_TAB_KEY = 'ameise:grossbuch:segments-tab'
const GROSSBUCH_UNITS_TAB_KEY = 'ameise:grossbuch:units-tab'
const allowedTabs = ['units', 'contacts', 'products', 'segments', 'geography', 'purchases', 'sales']

function storedTab(key, fallback, allowed = null) {
    if (typeof window === 'undefined') {
        return fallback
    }

    const value = window.localStorage.getItem(key)

    if (!value) {
        return fallback
    }

    return allowed && !allowed.includes(value) ? fallback : value
}

function rememberTab(key, value) {
    if (typeof window === 'undefined' || !value) {
        return
    }

    window.localStorage.setItem(key, value)
}

function loadStoredTabs() {
    tab.value = storedTab(GROSSBUCH_TAB_KEY, 'units', allowedTabs)
    tabsContacts.value = storedTab(GROSSBUCH_CONTACTS_TAB_KEY, 'telephones')
    tabsGeography.value = storedTab(GROSSBUCH_GEOGRAPHY_TAB_KEY, 'cities')
    tabsProducts.value = storedTab(GROSSBUCH_PRODUCTS_TAB_KEY, 'categories')
    tabsSegments.value = storedTab(GROSSBUCH_SEGMENTS_TAB_KEY, 'industries')
    tabsUnits.value = storedTab(GROSSBUCH_UNITS_TAB_KEY, 'units_sub')
}

const tab = ref('units')
const tabsContacts = ref('telephones')
const tabsGeography = ref('cities')
const tabsProducts = ref('categories')
const tabsSegments = ref('industries')
const tabsUnits = ref('units_sub')

const brands = ref([])
const buildings = ref([])
const catalogs = ref([])
const categories = ref([])
const checks = ref([])
const cities = ref([])
const commodities = ref([])
const components = ref([])
const countries = ref([])
const emails = ref([])
const entityClassifications = ref([])
const fields = ref([])
const good = ref(null)
const goods = ref([])
const labels = ref([])
const measures = ref([])
const products = ref([])
const purchases = ref([])
const regions = ref([])
const sale = ref()
const sales = ref([])
const segments = ref([])

let manufacturers = ref();

let searchComponents = ref('');

const dialogFormBuilding = ref(false)
const dialogFormCheck = ref(false)

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
const headerTelephones = ref([
    {
        title: 'Number',
        key: 'number',
        align: 'start',
    },
    {
        title: 'Created',
        key: 'created_at',
        align: 'start',
    },
    {
        title: 'Owners',
        key: 'entities',
        align: 'start',
    },
])

//   B R A N D S
function indexBrands(){
    axios.get(route('brands.index')).then(function (response){
        brands.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
const searchBrands = ref('')
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
const searchBuildings = ref('')
const filteredBuildings = computed(()=>{
    const search = searchBuildings.value.toLowerCase();
    return buildings.value.filter(i => i.address.toLowerCase().includes(search))
})
const headerBuildings = ref([
    {
        title: 'Карта',
        key: 'city.yandexmapsgeo',
        align: 'center',
    },
    {
        title: 'Город',
        key: 'city.name',
        align: 'start',
    },
    {
        title: 'Адрес',
        key: 'address',
        align: 'start',
    },
    {
        title: 'Индекс',
        key: 'postcode',
        align: 'start',
    },
    {
        title: 'Units',
        key: 'units',
        align: 'start',
    },
])
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
// E N D  B U I L D I N G S



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



//     C O M P O N E N T S
function indexComponents(){
    axios.get(route('components.index')).then(function (response){
        components.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
const headerComponents = ref([
    {
        key: 'name',
        title: 'name',
        align: 'start',
        sortable: true,
    },
])
const formComponent = useForm({
    name: null,
})
function storeComponent(){
    formComponent.post(route('api.components.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formComponent.reset();
            indexComponents()
        },
    });
}
// E N D  C O M P O N E N T S



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
// E N D  C O U N T R I E S



//     E N T I T Y  C L A S S I F I C A T I O N S
function indexEntityClassifications(){
    axios.get(route('entities-classification.index')).then(function (response){
        entityClassifications.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
// E N D  E N T I T Y  C L A S S I F I C A T I O N S



//      F I E L D S
function indexFields(){
    axios.get(route('fields.index')).then(function (response){
        fields.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
const headerFields = ref([
    {
        key: 'title',
        title: 'title',
        align: 'start',
        sortable: true,
    },
])
// E N D  F I E L D S



//   L A B E L S
function indexLabels(){
    axios.get(route('labels.index')).then(function (response) {
        labels.value = response.data
    }).catch(function (error) {
        console.log(error);
    });
}
//     M E A S U R E S
function indexMeasures(){
    axios.get(route('measures.index')).then(function (response){
        measures.value = response.data
    }).catch(function (error){
        console.error(error)
    })
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
//     S A L E S
function indexSales(){
    axios.get(route('sales.index')).then(function (response){
        sales.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
const headerSales = ref([
    {
        title: '+good',
        key: 'good',
        align: 'center',
        width: '33px',
    },
    {
        key: 'entity.name',
        title: 'Entity',
        sortable: true,
        align: 'start',
        class: 'text-primary',
        headerClass: 'bg-grey-lighten-3',
        width: '47%',
    },
    {
        key: 'date',
        title: 'Дата',
        sortable: true,
        align: 'start',
        class: 'text-rose-700',
        headerClass: 'bg-grey-lighten-3',
    },
    {
        key: 'total',
        title: 'Total',
        sortable: true,
        align: 'start',
        class: 'text-primary',
        headerClass: 'bg-grey-lighten-3',
    },
])
function showSale(id){
    axios.get(route('sales.show', id)).then(function (response){
        sale.value = response.data
    }).catch(function (error){
        console.log(error)
    });
}
const dialogFormSale = ref(false)
const formSale = useForm({
    date: null,
    entity_id: null,
    total: null,
})
function storeSale(){
    formSale.date = format(new Date(formSale.date), 'yyyy-MM-dd HH:mm:ss');
    formSale.post(route('web.sale.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formSale.reset()
                },
    })
}
const dialogFormAttachGood = ref(false)
const loadingAttach = ref(false)
const snackbar = ref({
    show: false,
    text: '',
});
const formAttachGood = useForm({
    good_id: null,
    sale_id: null,
    quantity: null,
    measure_id: null,
    price: null,
})
function openAttachDialog(sale) {
    showSale(sale.id)
    formAttachGood.sale_id = sale.id
    dialogFormAttachGood.value = true;
}
function attachGood(){
    loadingAttach.value = true;
    formAttachGood.post(route('web.goodsale.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            snackbar.value = {
                show: true,
                text: 'Товар успешно привязан!',
            };
            dialogFormAttachGood.value = false; // Закрыть диалог
            formAttachGood.reset()
                },
    })
}

let totalInKg = ref()
let quantity = ref()
// E N D  S A L E S



//   S E G M E N T S
function indexSegments(){
    axios.get(route('segments.index')).then(function (response){
        segments.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
// E N D  S E G M E N T S


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
    loadStoredTabs()
    indexBrands()
    indexBuildings()
    indexCatalogs()
    indexCategories()
    indexChecks()
    indexComponents()
    indexCountries()
    indexEntityClassifications()
    indexFields()
    indexLabels()
    indexMeasures()
    indexProducts()
    indexPurchases()
    indexRegions()
    indexSegments()
    indexUnits()

    getManufacturers();
})

watch(tab, (value) => rememberTab(GROSSBUCH_TAB_KEY, value))
watch(tabsContacts, (value) => rememberTab(GROSSBUCH_CONTACTS_TAB_KEY, value))
watch(tabsGeography, (value) => rememberTab(GROSSBUCH_GEOGRAPHY_TAB_KEY, value))
watch(tabsProducts, (value) => rememberTab(GROSSBUCH_PRODUCTS_TAB_KEY, value))
watch(tabsSegments, (value) => rememberTab(GROSSBUCH_SEGMENTS_TAB_KEY, value))
watch(tabsUnits, (value) => rememberTab(GROSSBUCH_UNITS_TAB_KEY, value))

useHead({
    title: `Управление торговлей`,
    meta: [
        {
            name: 'description',
            content: `Управление торговлей`,
        }
    ]
})

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

// Функция для корректного отображения "Город - Адрес"
const formatBuildingTitle = (building) => {
    if (!building) return '';
    return `${building.city?.name || ' - '} , ${building.address}`;
};
</script>

<template>
    <v-theme-provider theme="dark">
        <v-container fluid>
            <v-row>
                <v-col>
                    <v-card>
                        <v-tabs v-model="tab">
                            <v-tab value="units">Объекты</v-tab>
                            <v-tab value="contacts">Контакты</v-tab>
                            <v-tab value="products">Products</v-tab>
                            <v-tab value="segments">Классификаторы</v-tab>
                            <v-tab value="geography">География</v-tab>
                            <v-tab value="purchases">Закупки</v-tab>
                            <v-tab value="sales">Продажи</v-tab>
                        </v-tabs>

                        <v-card-text>
                            <v-tabs-window v-model="tab">

                                <!--   О Б Ъ Е К Т Ы   -->
                                <v-tabs-window-item value="units">
                                    <v-tabs v-model="tabsUnits">
                                        <v-tab value="units_sub">Units</v-tab>
                                        <v-tab value="entities">Entities</v-tab>
                                    </v-tabs>
                                    <v-tabs-window v-model="tabsUnits">
                                        <v-tabs-window-item value="units_sub">
                                            <Units />
                                        </v-tabs-window-item>
                                        <v-tabs-window-item value="entities">
                                            <Entities />
                                        </v-tabs-window-item>
                                    </v-tabs-window>
                                </v-tabs-window-item>
                                <!--           E N D  U N I T S           -->


                                <!--           К О Н Т А К Т Ы           -->
                                <v-tabs-window-item value="contacts">
                                    <v-row>
                                        <v-col>
                                            <v-tabs v-model="tabsContacts">
                                                <v-tab value="telephones">Телефоны</v-tab>
                                                <v-tab value="uris">Uris</v-tab>
                                                <v-tab value="emails">Emails</v-tab>
                                                <v-tab value="mail_messages">Письма</v-tab>
                                            </v-tabs>

                                            <v-tabs-window v-model="tabsContacts">
                                                <v-tabs-window-item value="uris">
                                                    <Uris />
                                                </v-tabs-window-item>

                                                <v-tabs-window-item value="emails">
                                                    <EmailsPage />
                                                </v-tabs-window-item>

                                                <v-tabs-window-item value="telephones">
                                                    <TelephonePage />
                                                </v-tabs-window-item>

                                                <v-tabs-window-item value="mail_messages">
                                                    <MailMessagesPage />
                                                </v-tabs-window-item>
                                            </v-tabs-window>
                                        </v-col>
                                    </v-row>
                                </v-tabs-window-item>
                                <!--        К О Н Е Ц  К О Н Т А К Т Ы         -->


                                <!--   P R O D U C T S   -->
                                <v-tabs-window-item value="products">
                                    <v-tabs v-model="tabsProducts">
                                        <v-tab value="categories">Categories</v-tab>
                                        <v-tab value="categories_products">Products</v-tab>
                                        <v-tab value="goods">Goods</v-tab>
                                        <v-tab value="components">Components</v-tab>
                                        <v-tab value="commodities">Commodities</v-tab>
                                    </v-tabs>
                                    <v-tabs-window v-model="tabsProducts">
                                        <v-tabs-window-item value="categories">
                                            <v-container fluid class="pa-0">
                                                <v-row no-gutters>
                                                    <v-col cols="12">
                                                        <Categories />
                                                    </v-col>
                                                </v-row>
                                            </v-container>
                                        </v-tabs-window-item>

                                        <v-tabs-window-item value="categories_products">
                                            <Products />
                                        </v-tabs-window-item>

                                        <v-tabs-window-item value="goods">
                                            <Goods />
                                        </v-tabs-window-item>

                                        <v-tabs-window-item value="components">
                                            <v-container fluid>
                                                <v-row>
                                                    <v-col>
                                                        <v-text-field v-model="searchComponents"
                                                                      label="search components"
                                                                      variant="solo"
                                                                      density="comfortable"
                                                                      clearable
                                                                      hide-details
                                                                      class="border rounded"
                                                        ></v-text-field>
                                                    </v-col>
                                                </v-row>
                                                <v-row>
                                                    <v-col>
                                                        <v-data-table :items="components"
                                                                      items-per-page="150"
                                                                      :headers="headerComponents"
                                                                      fixed-header
                                                                      height="810px"
                                                                      density="compact"
                                                                      hover
                                                                      class="border rounded border-lime-300"
                                                        ></v-data-table>
                                                    </v-col>
                                                </v-row>
                                            </v-container>
                                        </v-tabs-window-item>

                                        <v-tabs-window-item value="commodities">
                                            <CommoditiesPage />
                                        </v-tabs-window-item>
                                    </v-tabs-window>
                                </v-tabs-window-item>
                                <!--  E N D  P R O D U C T S  -->


                                <!--           S A L E S           -->
                                <v-tabs-window-item value="sales" class="grossbuch-sales-tab">
                                    <GrossbuchSales />
                                </v-tabs-window-item>
                                <!--      E N D  S A L E S      -->







                                <!--           G E O G R A P H Y           -->
                                <v-tabs-window-item value="geography">
                                    <v-tabs v-model="tabsGeography">
                                        <v-tab value="cities">Cities</v-tab>
                                        <v-tab value="buildings">Buildings</v-tab>
                                        <v-tab value="regions">Regions</v-tab>
                                        <v-tab value="countries">Countries</v-tab>
                                    </v-tabs>
                                    <v-tabs-window v-model="tabsGeography">

                                        <v-tabs-window-item value="cities">
                                            <CitiesPage />
                                        </v-tabs-window-item>

                                        <v-tabs-window-item value="buildings">
                                            <v-row>
                                                <v-col cols="1">
                                                    <div class="flex flex-row justify-center font-sans text-sm">{{ filteredBuildings.length }}</div>
                                                </v-col>
                                                <v-col lg="3">
                                                    <v-text-field v-model="searchBuildings"
                                                                  label="Искать по адресам"
                                                                  variant="solo"
                                                                  density="compact"
                                                                  hide-details
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
                                                                                          label="Адрес здания"
                                                                                          variant="outlined"
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
                                                                                          variant="solo"
                                                                                          density="comfortable"
                                                                                          color="grey"
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
                                                    <v-data-table :items="filteredBuildings"
                                                                  items-per-page="100"
                                                                  :headers="headerBuildings"
                                                                  fixed-header
                                                                  height="900px"
                                                                  density="compact"
                                                                  hover>
                                                        <template v-slot:item.city.yandexmapsgeo="{item}">
                                                            <a :href="item.city.yandexmapsgeo" target="_blank"
                                                               class="inline-flex items-center justify-center mr-1 bg-teal-500 text-white rounded-full text-[6px] font-bold w-3 h-3"
                                                            >Y</a>
                                                        </template>
                                                        <template v-slot:item.postcode="{item}">
                                                            <span class="font-Screpka text-xl">{{item.postcode}}</span>
                                                        </template>
                                                        <template v-slot:item.units="{item}">
                                                            <div v-for="unit in item.units"
                                                                 class="text-xs"
                                                            >
                                                                {{unit.name}}
                                                            </div>
                                                        </template>
                                                    </v-data-table>
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
                                <!--      E N D  G E O G R A P H Y      -->


                                <v-tabs-window-item value="purchases">
                                    <Purchases />
                                </v-tabs-window-item>


                                <!--        К Л А С С И Ф И К А Т О Р Ы        -->
                                <v-tabs-window-item value="segments">
                                    <v-container fluid>
                                        <v-tabs v-model="tabsSegments">
                                            <v-tab value="industries">Industries</v-tab>
                                            <v-tab value="catalogs">Catalogs</v-tab>
                                            <v-tab value="fields">Fields</v-tab>
                                            <v-tab value="segments_tab">Segments</v-tab>
                                        </v-tabs>
                                        <v-tabs-window v-model="tabsSegments">
                                            <v-tabs-window-item value="industries">
                                                <v-container>
                                                    <v-row>
                                                        <v-col cols="9">
                                                            <Industries />
                                                        </v-col>
                                                    </v-row>
                                                </v-container>
                                            </v-tabs-window-item>
                                            <v-tabs-window-item value="catalogs">
                                                <v-container fluid>
                                                    <v-row>
                                                        <v-col>
                                                            <v-data-table :items="catalogs"
                                                                          items-per-page="25"
                                                                          :headers="headersCatalogs"
                                                                          density="compact"
                                                                          hover
                                                                          class="border rounded"
                                                            >
                                                                <template v-slot:item.uri="{item}">
                                                                    <a :href="item.uri" target="_blank">
                                                                        {{item.uri}}
                                                                    </a>
                                                                </template>
                                                            </v-data-table>
                                                        </v-col>
                                                    </v-row>
                                                </v-container>
                                            </v-tabs-window-item>
                                            <v-tabs-window-item value="fields">
                                                <v-container fluid>
                                                    <v-row>
                                                        <v-col cols="2">
                                                            <v-text-field label="Search"
                                                                          variant="solo-inverted"
                                                                          density="compact"
                                                                          hide-details
                                                            ></v-text-field>
                                                        </v-col>
                                                    </v-row>
                                                    <v-row>
                                                        <v-col>
                                                            <v-data-table :items="fields"
                                                                          items-per-page="100"
                                                                          :headers="headerFields"
                                                                          fixed-header
                                                                          height="500px"
                                                                          density="compact"
                                                                          hover
                                                                          class="border rounded"
                                                            ></v-data-table>
                                                        </v-col>
                                                    </v-row>
                                                </v-container>
                                            </v-tabs-window-item>
                                            <v-tabs-window-item value="segments_tab">
                                                <v-container fluid>
                                                    <v-row>
                                                        <v-col>
                                                            <v-data-table :items="segments"
                                                            ></v-data-table>
                                                        </v-col>
                                                    </v-row>
                                                </v-container>
                                            </v-tabs-window-item>
                                        </v-tabs-window>
                                    </v-container>
                                </v-tabs-window-item>
                                <!--           К О Н Е Ц  К Л А С С И Ф И К А Т О Р Ы           -->



                                <!--                        З А К У П К А                  -->
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
                                            <v-list-item v-for="purchase in purchases.slice().sort((a, b) => new Date(b.date) - new Date(a.date))"
                                                         :key="purchase.id"
                                                         class="text-[11px] hover:text-orange-600 hover:bg-zinc-700"
                                            >
                                                <v-row>
                                                    <v-col>
                                                        <span>{{ date.format(purchase.date, 'fullDate') }}</span>
                                                    </v-col>
                                                    <v-col>
                                                        <span>{{ purchase.amount }}</span>
                                                    </v-col>
                                                    <v-col>
                                                        <span>{{ purchase.entity.name }}</span>
                                                    </v-col>
                                                </v-row>
                                            </v-list-item>
                                        </v-col>
                                    </v-row>
                                </v-tabs-window-item>
                                <!--           К О Н Е Ц  З А К У П К А          -->




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
                                <!--  E N D  M A I L    -->



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
