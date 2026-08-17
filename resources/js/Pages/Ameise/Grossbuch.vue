<script setup>
import {useHead} from "@unhead/vue";
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

import EmailsPage from '@/Components/Contacts/Emails/EmailsPage.vue';
import Entities from "@/Components/Dictionaries/Entities/Entities.vue";
import Fields from "@/Components/Dictionaries/Fields.vue";
import GrossbuchSales from '@/Components/Grossbuch/GrossbuchSales.vue';
import Industries from "@/Components/Dictionaries/Industries.vue";
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
const GROSSBUCH_SEGMENTS_TAB_KEY = 'ameise:grossbuch:segments-tab'
const GROSSBUCH_UNITS_TAB_KEY = 'ameise:grossbuch:units-tab'
const allowedTabs = ['units', 'contacts', 'segments', 'purchases', 'sales']
const allowedContactTabs = ['telephones', 'uris', 'emails']

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
    tabsContacts.value = storedTab(GROSSBUCH_CONTACTS_TAB_KEY, 'telephones', allowedContactTabs)
    tabsSegments.value = storedTab(GROSSBUCH_SEGMENTS_TAB_KEY, 'industries')
    tabsUnits.value = storedTab(GROSSBUCH_UNITS_TAB_KEY, 'units_sub')
}

const tab = ref('units')
const tabsContacts = ref('telephones')
const tabsSegments = ref('industries')
const tabsUnits = ref('units_sub')

const brands = ref([])
const catalogs = ref([])
const checks = ref([])
const emails = ref([])
const entityClassifications = ref([])
const good = ref(null)
const labels = ref([])
const measures = ref([])
const purchases = ref([])
const sale = ref()
const sales = ref([])
const segments = ref([])

let manufacturers = ref();

const dialogFormCheck = ref(false)

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



//   C A T A L O G S
function indexCatalogs(){
    axios.get(route('catalogs.index')).then(function (response){
        catalogs.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
//   C H E C K S
function indexChecks(){
    axios.get(route('checks.index')).then(function (response){
        checks.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}



//     E N T I T Y  C L A S S I F I C A T I O N S
function indexEntityClassifications(){
    axios.get(route('entities-classification.index')).then(function (response){
        entityClassifications.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
// E N D  E N T I T Y  C L A S S I F I C A T I O N S



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
//   P U R C H A S E S
function indexPurchases(){
    axios.get(route('purchases.index')).then(function (response){
        purchases.value = response.data
    }).catch(function (error){
        console.error(error)
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
let mailIdempotencyKey = ref(crypto.randomUUID());
async function sendMail() {
    try {
        const response = await axios.post('/api/mail-messages/send', {
            idempotency_key: mailIdempotencyKey.value,
            to: [email.value],
            subject: 'Сообщение ПИЩЕПРОМ-СЕРВЕР',
            body: message.value,
        });
        successMessage.value = response.data.message;
        mailIdempotencyKey.value = crypto.randomUUID();
    } catch(error){
        console.log(error);
    }
}

onMounted(()=>{
    loadStoredTabs()
    indexBrands()
    indexCatalogs()
    indexChecks()
    indexEntityClassifications()
    indexLabels()
    indexMeasures()
    indexPurchases()
    indexSegments()
    indexUnits()

    getManufacturers();
})

watch(tab, (value) => rememberTab(GROSSBUCH_TAB_KEY, value))
watch(tabsContacts, (value) => rememberTab(GROSSBUCH_CONTACTS_TAB_KEY, value))
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
                            <v-tab value="segments">Классификаторы</v-tab>
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
                                            </v-tabs-window>
                                        </v-col>
                                    </v-row>
                                </v-tabs-window-item>
                                <!--        К О Н Е Ц  К О Н Т А К Т Ы         -->


                                <!--           S A L E S           -->
                                <v-tabs-window-item value="sales" class="grossbuch-sales-tab">
                                    <GrossbuchSales />
                                </v-tabs-window-item>
                                <!--      E N D  S A L E S      -->

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
                                                <Fields />
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
