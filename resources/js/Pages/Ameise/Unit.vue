<script setup>
import { useDate } from 'vuetify'
import {onMounted, ref} from "vue";
import axios from "axios";
import {useForm, Link} from "@inertiajs/vue3";
import { useHead } from '@vueuse/head'
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {route} from "ziggy-js";
defineOptions({
    layout: VerwalterLayout,
})
const props = defineProps({
    unit: Object,
    products: Object,
})
const date = useDate()

const unit = ref(props.unit)

async function fetchUnit(id) {
    try {
        const { data } = await axios.get(route('api.units.show', id))
        unit.value = data
    } catch (e) {
        console.error(e)
    }
}

const dict = ref({
    buildings: [],
    emails: [],
    entities: [],
    entityClassifications: [],
    labels: [],
    measures: [],
    products: [],
    telephones: [],
    uris: [],
})

const loading = ref({
    dict: false,
    unit: false,
    files: false,
})

async function loadDictionaries() {
    loading.value.dict = true
    try {
        const [
            buildingsRes,
            emailsRes,
            entitiesRes,
            classificationsRes,
            labelsRes,
            measuresRes,
            productsRes,
            telephonesRes,
            urisRes,
        ] = await Promise.all([
            axios.get(route('buildings.index')),
            axios.get(route('emails.index')),
            axios.get(route('entities.index')),
            axios.get(route('entities-classification.index')),
            axios.get(route('labels.index')),
            axios.get(route('measures.index')),
            axios.get(route('products.index')),
            axios.get(route('telephones.index')),
            axios.get(route('uris.index')),
        ])

        dict.value.buildings = buildingsRes.data
        dict.value.emails = emailsRes.data
        dict.value.entities = entitiesRes.data
        dict.value.entityClassifications = classificationsRes.data
        dict.value.labels = labelsRes.data
        dict.value.measures = measuresRes.data
        dict.value.products = (productsRes.data || []).sort((a,b)=>a.rus.localeCompare(b.rus))
        dict.value.telephones = telephonesRes.data
        dict.value.uris = urisRes.data
        dict.value.entityClassifications = classificationsRes.data
    } catch (e) {
        console.error(e)
    } finally {
        loading.value.dict = false
    }
}

const dialogFormAddEmail = ref(false)
const dialogFormAddUri = ref(false)
const dialogFormAttachEmail = ref(false)
const dialogFormAttachLabel = ref(false)
const dialogFormAttachUri = ref(false)
const dialogFormSendEmail = ref(false)
const showFormBuilding = ref(false)
const showFormConsumption = ref(false)
const showFormAttachManufacturer = ref(false)
let showFormAttachEntity = ref(false)

const formAddEmail = useForm({
    address: null,
})
const formAddUri = useForm({
    address: null,
})
const formAttachEntity = useForm({
    entity_id: null,
    unit_id: props.unit.id,
})
const formAttachManufacturer = useForm({
    product_id: null,
    unit_id: props.unit.id,
})
const formAttachUri = useForm({
    unit_id: props.unit.id,
    uri_id: null,
})
const formBuildingUnit = useForm({
    building_id: null,
    unit_id: props.unit.id,
    location_id: null,
})
const formConsumption = useForm({
    unit_id: props.unit.id,
    product_id: null,
    quantity: null,
    measure_id: null,
})

const headerConsumptions = ref([
    {
        title: 'created',
        key: 'created_at',
    },
    {
        title: 'Product',
        key: 'product_id',
    },
    {
        title: 'Quantity',
        key: 'quantity',
    },
    {
        title: 'Measure',
        key: 'measure_id',
    },
])

const headersEntities = ref([
    {
        title: 'Вид',
        key: 'classification.name',
    },
    {
        title: 'name',
        key: 'name',
    },
    {
        title: 'Телефоны',
        key: 'telephones',
    },
])

const headerFields = [
    {key: 'title', title: 'Field',}
]

const headerQuotations = [
    { key: 'good.name', title: 'Good', sortable: true },
    { key: 'price', title: 'Price', sortable: true },
    { key: 'measure.name', title: 'Measure', sortable: true },
]


//     P R O D U C T S - M A N U F A C T U R E S
function indexProducts(){
    axios.get(route('products.index')).then(function (response){
        dict.value.products = response.data.sort((a, b) => {
            return a.rus.localeCompare(b.rus) // сортировка по полю 'rus'
        })
    }).catch(function (error){
        console.log(error);
    })
}
const headerManufactures = ref([
    {key: 'rus', title: 'Title', align: 'start', sortable: true,}
])
// E N D  P R O D U C T S

function storeBuildingUnit(){
    formBuildingUnit.post(route('api.building_unit.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formBuildingUnit.reset();
        },
    })
}
function storeConsumption(){
    formConsumption.post(route('api.consumption.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formConsumption.reset();
            showFormConsumption.value = false;
        },
    });
}
function storeEmail(){
    formAddEmail.post(route('web.email.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: async ()=> {
            formAddEmail.reset()
            await fetchUnit()
        },
    })
}

function storeEntity(){
    formEntity.post(route('api.entity.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: async () => {
            formEntity.reset()
            await fetchUnit()                // ✅ обновит unit.entities
        },
    })
}

function attachEntity(){
    formAttachEntity.post(route('api.entity_unit.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formAttachEntity.reset();
        },
    })
}
function attachManufacturer(){
    formAttachManufacturer.post(route('web.manufacturer.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formAttachManufacturer.reset()
            fetchUnit(props.unit.id)
        },
    })
}

let showFormCreateEntity = ref(false)
let formEntity = useForm({
    name: null,
    entity_classification_id: null,
    telephones: null,
})

let files = ref();
function fetchFiles(name){
    axios.get(`/api/units/${name}/files`).then(function (response){
        files.value = response.data;
    }).catch (function  (error) {
        console.error('Ошибка загрузки файлов:', error);
    })
}


const formAttachEmail = useForm({
    email_id: null,
    unit_id: props.unit.id,
})
function attachEmail(){
    formAttachEmail.post(route('emailgood.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: async ()=> {
            formAttachEmail.reset()
            await fetchUnit()
        },
    })
}
const formAttachLabel = useForm({
    label_id: null,
    unit_id: props.unit.id,
})
function attachLabel(){
    formAttachLabel.post(route('web.labelunit.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formAttachLabel.reset()
            fetchUnit(props.unit.value.id)
        },
    })
}
function attachUri(){
    formAttachUri.post(route('web.unituri.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formAttachUri.reset()
            fetchUnit(props.unit.id)
        },
    })
}
function storeUri(){
    formAddUri.post(route('web.uri.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formAddUri.reset()

            dialogFormAddUri.value = false
        },
    })
}

//       S E N D  E M A I L
const headerSelectProductsForSending = ref([
    {
        title: 'Name',
        align: 'start',
        key: 'rus',
    },
    {
        key: 'goods',
        title: 'Товары',
        align: 'start',
        sortable: true,
    },
    {
        key: 'price',
        title: 'Цена',
        align: 'start',
        sortable: false,
        width: '16%',
    },
])
const sendEmail = async (email) => {
    try {
        const response = await axios.post(route('api.mail'), {
            email: email,
            products: selectedProducts.value,
        })
        console.log(response);
        await fetchUnit()
    } catch (error) {
        console.error('Ошибка при отправке:', error);
    }
}
const selectedProducts = ref([])
const headerSelectedProducts = ref([
    {
        key: 'rus',
        title: 'Наименование',
        sortable: true,
        align: 'start',
        width: '60%',
    },
    {
        key: 'price',
        title: 'Цена',
        sortable: true,
        align: 'start',
        class: 'text-primary text-[9px]',
        headerClass: 'bg-grey-lighten-3',
    },
])
const onSelectedProductsUpdate = (newSelected) => {
    // Добавим поле price, если его нет
    selectedProducts.value = newSelected.map(product => ({
        ...product,
        price: product.price ?? ''
    }));
}
// E N D  S E N D  E M A I L

const formatBuildingTitle = (building) => {
    if (!building) return '';
    return `${building.city?.name || ' - '}: ${building.address}`;
}

function timeDiff(time){
    let d = new Date();
    let diffMilliseconds = d - Date.parse(time);
    let denominator = 1000 * 3600 * 24;
    return Math.round(diffMilliseconds/denominator);
}

const tabUnitCard = ref('info') // ✅ по умолчанию Info

onMounted(()=> {
    loadDictionaries()
    indexProducts()
    fetchFiles(props.unit.name)
})

useHead({
    title: `Unit: ${props.unit.name}`,
    meta: [
        {
            name: 'description',
            content: `Информация о блоке ${props.unit.name}`
        }
    ]
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col cols="4">
                <v-card>
                    <v-card-title class="bg-rose-800 text-stone-100"
                    >
                        {{unit.name}}</v-card-title>
                    <v-card-subtitle>
                        <v-btn text="+email"
                               @click="dialogFormAttachEmail = !dialogFormAttachEmail"
                               variant="flat"
                               density="compact"
                               class="text-xs"
                        ></v-btn>
                        <v-dialog v-model="dialogFormAttachEmail"
                                  width="900"
                                  >
                            <template v-slot:default="{isActive}">
                                <v-card>
                                    <v-card-title>Form Attach Email</v-card-title>
                                    <v-card-text>
                                        <v-form @submit.prevent>
                                            <v-row>
                                                <v-col>
                                                    <v-autocomplete :items="dict.emails"
                                                                    :item-value="'id'"
                                                                    :item-title="'address'"
                                                                    v-model="formAttachEmail.email_id"
                                                                    bg-color="amber-lighten-5"
                                                                    color="deep-orange-darken-4"
                                                                    density="comfortable"
                                                                    item-color="deep-orange"
                                                                    label="Emails"
                                                                    variant="outlined"
                                                    ></v-autocomplete>
                                                </v-col>
                                            </v-row>
                                            <v-row>
                                                <v-col cols="3">
                                                    <v-btn text="новый email"
                                                           @click="dialogFormAddEmail = !dialogFormAddEmail"
                                                           variant="flat"
                                                           density="compact"
                                                           ></v-btn>
                                                    <v-dialog v-model="dialogFormAddEmail"
                                                              width="850"
                                                    >
                                                        <template v-slot:default="{isActive}">
                                                            <v-card>
                                                                <v-card-title>Form Adding new Email</v-card-title>
                                                                <v-card-text>
                                                                    <v-form @submit.prevent>
                                                                        <v-row>
                                                                            <v-col>
                                                                                <v-text-field v-model="formAddEmail.address"
                                                                                              label="Email address"
                                                                                              variant="outlined"
                                                                                              density="comfortable"
                                                                                              ></v-text-field>
                                                                            </v-col>
                                                                        </v-row>
                                                                    </v-form>
                                                                </v-card-text>
                                                                <v-card-actions>
                                                                    <v-divider vertical
                                                                               color="grey"
                                                                               opacity="0.88"
                                                                               ></v-divider>
                                                                    <v-btn text="store"
                                                                           @click="storeEmail"
                                                                           variant="flat"
                                                                           flat
                                                                           density="comfortable"></v-btn>
                                                                </v-card-actions>
                                                            </v-card>
                                                        </template>
                                                    </v-dialog>
                                                </v-col>
                                            </v-row>
                                        </v-form>
                                    </v-card-text>
                                    <v-card-actions>
                                        <v-divider vertical
                                                   opacity="0.85"
                                                   color="amber"></v-divider>

                                        <v-btn text="attach"
                                               @click="attachEmail"
                                               variant="flat"
                                               density="comfortable"></v-btn>
                                    </v-card-actions>
                                </v-card>
                            </template>
                        </v-dialog>
                        <v-btn text="+uri"
                               @click="dialogFormAttachUri = !dialogFormAttachUri"
                               variant="flat"
                               density="compact"
                               class="text-xs"
                        ></v-btn>
                        <v-dialog v-model="dialogFormAttachUri"
                                  width="900">
                            <template v-slot:default="{isActive}">
                                <v-card>
                                    <v-card-title>Form attach Uri</v-card-title>
                                    <v-card-text>
                                        <v-form @submit.prevent>
                                            <v-row>
                                                <v-col>
                                                    <v-autocomplete :items="dict.uris"
                                                                    :item-value="'id'"
                                                                    :item-title="'address'"
                                                                    v-model="formAttachUri.uri_id"
                                                                    label="Uri"
                                                                    placeholder="select Uri"
                                                                    variant="outlined"
                                                                    density="comfortable"
                                                                    item-color="red-darken-2"
                                                    ></v-autocomplete>
                                                </v-col>
                                            </v-row>
                                        </v-form>
                                        <v-divider color="purple"
                                                   opacity="0.9"></v-divider>

                                        <v-btn text="new uri"
                                               @click="dialogFormAddUri = !dialogFormAddUri"
                                               flat
                                               density="comfortable"
                                               ></v-btn>
                                        <v-dialog v-model="dialogFormAddUri"
                                                  width="900">
                                            <template v-slot:default="{isActive}">
                                                <v-card>
                                                    <v-card-title>Form adding new Uri</v-card-title>
                                                    <v-card-text>
                                                        <v-form @submit.prevent>
                                                            <v-row>
                                                                <v-col>
                                                                    <v-text-field v-model="formAddUri.address"
                                                                                  label="Uri address"
                                                                                  variant="outlined"
                                                                                  density="comfortable"
                                                                    ></v-text-field>
                                                                </v-col>
                                                            </v-row>
                                                        </v-form>
                                                    </v-card-text>
                                                    <v-card-actions>
                                                        <v-divider vertical
                                                                   color="black"
                                                                   opacity="0.89"></v-divider>
                                                        <v-btn text="store"
                                                               @click="storeUri"
                                                               flat
                                                               density="comfortable"
                                                               color="pink-accent-4"></v-btn>
                                                    </v-card-actions>
                                                </v-card>
                                            </template>
                                        </v-dialog>
                                    </v-card-text>
                                    <v-card-actions>
                                        <v-divider vertical
                                                   color="red"
                                                   opacity="0.88"></v-divider>
                                        <v-btn text="attach"
                                               @click="attachUri"
                                               variant="flat"
                                               density="comfortable"
                                               color="red-darken-1"></v-btn>
                                    </v-card-actions>
                                </v-card>
                            </template>
                        </v-dialog>
                    </v-card-subtitle>
                    <v-card-text>
                        <v-row>
                            <v-col>
                                <v-tabs v-model="tabUnitCard">
                                    <v-tab value="info">Info</v-tab>
                                    <v-tab value="buildings">Buildings</v-tab>
                                </v-tabs>
                                <v-tabs-window v-model="tabUnitCard">
                                    <v-tabs-window-item value="info">
                                        <v-container fluid>
                                            <v-row>
                                                <v-col>
                                                    <v-list>
                                                        <v-list-item v-for="file in files" :key="file.name"
                                                                     density="compact"
                                                        >
                                                            <a :href="file.url" target="_blank">{{ file.name }}</a>
                                                        </v-list-item>
                                                    </v-list>
                                                </v-col>
                                                <v-col>
                                                    <v-list density="compact">
                                                        <v-list-item v-for="uri in (unit.uris || [])" :key="uri.id">
                                                            <a :href="uri.address"
                                                               target="_blank"
                                                            >
                                                                {{uri.address}}
                                                            </a>
                                                        </v-list-item>
                                                    </v-list>
                                                    <v-list>
                                                        <v-list-item v-for="telephone in (unit.telephones || [])"
                                                                     :key="telephone.id"
                                                                     class="text-slate-800"
                                                        >
                                                            {{telephone.number}}
                                                        </v-list-item>
                                                    </v-list>
                                                    <v-list>
                                                        <v-list-item v-for="email in (unit.emails || [])"
                                                                     :key="email.id"
                                                                     base-color="teal-darken-4"
                                                                     border
                                                                     color="teal-lighten-5"
                                                                     density="compact"
                                                                     elevation="1"
                                                                     slim
                                                                     rounded
                                                        >
                                                            <v-row>
                                                                <v-col cols="9">
                                                                    {{email.address}}
                                                                </v-col>
                                                                <v-col cols="3" class="d-flex justify-center align-center">
                                                                    <v-btn text=">>>"
                                                                           @click="dialogFormSendEmail = !dialogFormSendEmail"
                                                                           size="small"
                                                                           flat
                                                                           density="comfortable"
                                                                           color="cyan-darken-3"
                                                                    ></v-btn>
                                                                    <v-dialog v-model="dialogFormSendEmail"
                                                                              width="1500"
                                                                    >
                                                                        <template v-slot:default="{isActive}">
                                                                            <v-card>
                                                                                <v-card-title>Form Send Email</v-card-title>
                                                                                <v-card-text>
                                                                                    <v-container fluid>
                                                                                        <v-row>
                                                                                            <v-col cols="8">
                                                                                                <v-data-table :items="products"
                                                                                                              items-per-page="130"
                                                                                                              v-model="selectedProducts"
                                                                                                              :headers="headerSelectProductsForSending"
                                                                                                              fixed-header
                                                                                                              height="868px"
                                                                                                              @update:model-value="onSelectedProductsUpdate"
                                                                                                              show-select
                                                                                                              return-object
                                                                                                              density="compact"
                                                                                                              hover
                                                                                                              class="border rounded"
                                                                                                >
                                                                                                    <template v-slot:item.price="{item}">
                                                                                                        <v-text-field v-model="item.price"
                                                                                                                      label="Цена"
                                                                                                                      variant="outlined"
                                                                                                                      density="comfortable"
                                                                                                                      hide-details
                                                                                                        ></v-text-field>
                                                                                                    </template>
                                                                                                    <template v-slot:item.goods="{item}">
                                                                                                        <div v-for="good in item.goods"
                                                                                                             class="text-[9px] font-sans"
                                                                                                        >
                                                                                                            <div>{{good.name}}</div>
                                                                                                            <div><span v-for="quotation in good.quotations"
                                                                                                                       class="text-[8px] font-sans mx-2"
                                                                                                            >{{quotation.price}}</span></div>
                                                                                                        </div>
                                                                                                    </template>
                                                                                                </v-data-table>
                                                                                            </v-col>
                                                                                            <v-col cols="4">
                                                                                                <v-data-table :items="selectedProducts"
                                                                                                              items-per-page="101"
                                                                                                              :headers="headerSelectedProducts"
                                                                                                              density="compact"
                                                                                                ></v-data-table>
                                                                                            </v-col>
                                                                                        </v-row>
                                                                                    </v-container>
                                                                                    <!--                                                                <pre>{{selectedProducts}}</pre>-->
                                                                                </v-card-text>
                                                                                <v-card-actions>
                                                                                    <v-divider vertical
                                                                                               color="orange"
                                                                                               opacity="0.9"></v-divider>
                                                                                    <v-btn text="send"
                                                                                           @click="sendEmail(email.address)"
                                                                                           flat
                                                                                           density="comfortable"
                                                                                           color="orange"></v-btn>
                                                                                </v-card-actions>
                                                                            </v-card>
                                                                        </template>
                                                                    </v-dialog>
                                                                </v-col>
                                                            </v-row>
                                                        </v-list-item>
                                                    </v-list>
                                                </v-col>
                                            </v-row>
                                        </v-container>
                                    </v-tabs-window-item>
                                    <v-tabs-window-item value="buildings">
                                        <v-card>
                                            <v-card-subtitle>
                                                <v-btn @click="showFormBuilding = !showFormBuilding"
                                                       text="+ Building"
                                                       variant="elevated"
                                                       density="compact"
                                                       color="deep-orange"
                                                ></v-btn>
                                                <v-dialog v-model="showFormBuilding"
                                                          width="815"
                                                >
                                                    <template v-slot:default="{isActive}">
                                                        <v-card color="yellow-lighten-3">
                                                            <v-card-title>Form Building</v-card-title>
                                                            <v-card-text>
                                                                <v-form @submit.prevent>
                                                                    <v-row>
                                                                        <v-col>
                                                                            <v-autocomplete :items="dict.buildings"
                                                                                            :item-title="formatBuildingTitle"
                                                                                            :item-value="'id'"
                                                                                            v-model="formBuildingUnit.building_id"
                                                                                            variant="outlined"
                                                                                            density="comfortable"
                                                                                            color="blue-grey"
                                                                            ></v-autocomplete>
                                                                        </v-col>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-col></v-col>
                                                                        <v-col></v-col>
                                                                        <v-col></v-col>
                                                                        <v-col>
                                                                            <v-btn @click="storeBuildingUnit"
                                                                                   text="store"
                                                                                   density="comfortable"
                                                                                   variant="outlined"
                                                                                   color="black"
                                                                            ></v-btn>
                                                                        </v-col>
                                                                    </v-row>
                                                                </v-form>
                                                            </v-card-text>
                                                        </v-card>
                                                    </template>
                                                </v-dialog>
                                            </v-card-subtitle>
                                            <v-card-text>
                                                <v-list>
                                                    <v-list-item v-for="building in (unit.buildings || [])" :key="building.id"
                                                    >
                                                        <span class="block mr-2">{{building.address}}</span>
                                                        <span class="block mr-2">{{building.city.name}}</span>
                                                    </v-list-item>
                                                </v-list>
                                            </v-card-text>
                                        </v-card>
                                    </v-tabs-window-item>
                                </v-tabs-window>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="2">
                <v-data-table :items="unit.fields"
                              :headers="headerFields"
                              items-per-page="5"
                              hover
                              ></v-data-table>
            </v-col>
            <v-col cols="2">
                <v-card>
                    <v-card-title>Labels</v-card-title>
                    <v-card-text>
                        <v-list>
                            <v-list-item v-for="label in unit.labels">
                                <span>{{label.name}}</span>
                                <v-divider thickness="1"
                                           opacity="100"
                                ></v-divider>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                    <v-card-actions>
                        <v-btn text="добавить"
                               @click="dialogFormAttachLabel = !dialogFormAttachLabel"
                               variant="flat"
                               density="comfortable"
                        ></v-btn>
                        <v-dialog v-model="dialogFormAttachLabel"
                                  width="900"
                                  >
                            <template v-slot:default="{isActive}">
                                <v-card>
                                    <v-card-title>Form attach Label</v-card-title>
                                    <v-card-text>
                                        <v-form @submit.prevent>
                                            <v-row>
                                                <v-col>
                                                    <v-autocomplete :items="dict.labels"
                                                                    :item-value="'id'"
                                                                    :item-title="'name'"
                                                                    v-model="formAttachLabel.label_id"
                                                                    label="Labels"
                                                                    variant="outlined"
                                                                    density="comfortable"
                                                    ></v-autocomplete>
                                                </v-col>
                                            </v-row>
                                        </v-form>
                                    </v-card-text>
                                    <v-card-actions>
                                        <v-divider vertical
                                                   opacity="0.85"
                                                   thickness="1"
                                                   color="puple"></v-divider>
                                        <v-btn text="attach"
                                               @click="attachLabel"
                                               variant="flat"
                                               density="comfortable"></v-btn>
                                    </v-card-actions>
                                </v-card>
                            </template>
                        </v-dialog>
                    </v-card-actions>
                </v-card>
            </v-col>
            <v-col cols="4">
                <v-card>
                    <v-card-title class="bg-orange-200 text-slate-800">Entities</v-card-title>
                    <v-card-subtitle>
                        <v-btn text="Прикрепить"
                               @click="showFormAttachEntity = !showFormAttachEntity"
                               density="compact"
                               variant="text"
                               class="text-xs"
                        ></v-btn>
                        <v-dialog v-model="showFormAttachEntity"
                                  width="880"
                        >
                            <template v-slot:default="{isActive}">
                                <v-card>
                                    <v-card-title>Attach Entity</v-card-title>
                                    <v-card-text>
                                        <v-form @submit.prevent>
                                            <v-row>
                                                <v-autocomplete :items="dict.entities"
                                                                :item-title="'name'"
                                                                :item-value="'id'"
                                                                v-model="formAttachEntity.entity_id"
                                                                label="Entity"
                                                                density="comfortable"
                                                ></v-autocomplete>
                                            </v-row>
                                        </v-form>
                                    </v-card-text>
                                    <v-card-actions>
                                        <v-btn text="store"
                                               @click="attachEntity"
                                               variant="outlined"
                                               color="pink-darken-4"
                                        ></v-btn>
                                    </v-card-actions>
                                </v-card>
                            </template>
                        </v-dialog>
                        <v-btn text="Создать"
                               @click="showFormCreateEntity = !showFormCreateEntity"
                               density="compact"
                               variant="text"
                               class="text-xs"
                        ></v-btn>
                        <v-dialog v-model="showFormCreateEntity"
                                  width="990"
                                  transition="dialog-top-transition"
                        >
                            <template v-slot:default="{ isActive }">
                                <v-card>
                                    <v-card-title>Form Entity</v-card-title>
                                    <v-card-text>
                                        <v-form @submit.prevent>
                                            <v-row>
                                                <v-select :items="dict.entityClassifications"
                                                          :item-value="'id'"
                                                          :item-title="'name'"
                                                          v-model="formEntity.entity_classification_id"
                                                          variant="outlined"
                                                          label="Вид"
                                                ></v-select>
                                            </v-row>
                                            <v-row>
                                                <v-text-field v-model="formEntity.name"
                                                              label="Name"
                                                              variant="outlined"
                                                ></v-text-field>
                                            </v-row>
                                            <v-row>
                                                <v-autocomplete :items="dict.telephones"
                                                                :item-value="'id'"
                                                                :item-title="'number'"
                                                                v-model="formEntity.telephones"
                                                                label="Telephones"
                                                                placeholder="number without +7"
                                                                variant="outlined"
                                                                density="comfortable"
                                                                color="purple-darken-4"
                                                                multiple
                                                                chips
                                                ></v-autocomplete>
                                            </v-row>
                                        </v-form>
                                    </v-card-text>
                                    <v-card-actions>
                                        <v-btn @click="storeEntity"
                                               text="сохранить"
                                               variant="flat"
                                        ></v-btn>
                                    </v-card-actions>
                                </v-card>
                            </template>
                        </v-dialog>
                    </v-card-subtitle>
                    <v-card-text>
                        <v-data-table :items="unit.entities"
                                      :headers="headersEntities"
                                      items-per-page="3"
                                      density="compact"
                                      hover
                                      class="border rounded"
                        >
                            <template v-slot:item.telephones="{item}">
                                <v-sheet>
                                    <div v-for="telephone in item.telephones">
                                        {{telephone.number}}
                                    </div>
                                </v-sheet>
                            </template>
                        </v-data-table>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
        <v-row>
            <v-col cols="3">
                <v-card elevation="3"
                        border
                >
                    <v-card-title class="bg-green-800 text-lime-200">Manufactures</v-card-title>
                    <v-card-subtitle>
                        <v-btn @click="showFormAttachManufacturer = !showFormAttachManufacturer"
                               variant="outlined"
                               density="compact"
                        >
                            <v-icon icon="mdi-new-box" size="large"></v-icon>
                        </v-btn>
                    </v-card-subtitle>
                    <v-card-text>
                        <v-data-table :items="unit.manufactures"
                                      items-per-page="33"
                                      :headers="headerManufactures"
                                      fixed-header
                                      height="215px"
                                      density="compact"
                                      hover
                                      class="border rounded"
                        >
                            <template v-slot:item.rus="{item}">
                                <Link :href="route('product.show', item.id)"
                                      class="text-xs font-sans font-semibold"
                                >
                                    {{item.rus}}
                                </Link>
                            </template>
                        </v-data-table>
                        <v-form v-if="showFormAttachManufacturer"
                                @submit.prevent
                                class="bg-amber-400 border rounded border-amber-700"
                        >
                            <v-row>
                                <v-col>
                                    <v-autocomplete :items="products"
                                                    :item-value="'id'"
                                                    :item-title="'rus'"
                                                    v-model="formAttachManufacturer.product_id"
                                                    variant="solo"
                                                    density="compact"
                                                    color="black"
                                                    hide-details
                                    ></v-autocomplete>
                                </v-col>
                            </v-row>
                            <v-row>
                                <v-col cols="8"></v-col>
                                <v-col cols="4">
                                    <v-btn text="store"
                                           @click="attachManufacturer"
                                           variant="elevated"
                                           density="compact"
                                    ></v-btn>
                                </v-col>
                            </v-row>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col lg="4">
                <v-card border
                        elevation="2"
                >
                    <v-card-title>Quotations</v-card-title>
                    <v-card-text>
                        <v-data-table :items="unit.quotations"
                                      :headers="headerQuotations"
                                      items-per-page="12"
                                      fixed-header
                                      height="300px"
                                      density="compact"
                                      hover
                                      class="border rounded">
                        </v-data-table>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
        <v-row>
            <v-col cols="8">
                <v-card elevation="2"
                >
                    <v-card-title>
                        Consumptions
                    </v-card-title>
                    <v-card-subtitle>
                        <v-btn @click="showFormConsumption = !showFormConsumption"
                               text="добавить"
                               variant="text"
                        ></v-btn>
                    </v-card-subtitle>
                    <v-card-text>
                        <v-data-table :items="unit.consumptions"
                                      :headers="headerConsumptions"
                                      density="comfortable"
                                      hover="hover"
                                      class="text-sm border rounded"
                        >
                            <template v-slot:top>
                                <v-form @submit.prevent
                                        v-if="showFormConsumption"
                                        style="border: 2px solid red; padding: 20px;">
                                    <v-row>
                                        <v-col cols="7">
                                            <v-autocomplete :items="products"
                                                            :item-title="'rus'"
                                                            :item-value="'id'"
                                                            v-model="formConsumption.product_id"
                                                            variant="outlined"
                                                            density="compact"
                                            ></v-autocomplete>
                                        </v-col>
                                        <v-col cols="3">
                                            <v-text-field v-model="formConsumption.quantity"
                                                          label="quantity"
                                                          variant="solo"
                                                          density="compact"
                                            ></v-text-field>
                                        </v-col>
                                        <v-col cols="2">
                                            <v-select :items="dict.measures"
                                                      :item-value="'id'"
                                                      :item-title="'name'"
                                                      v-model="formConsumption.measure_id"
                                                      variant="outlined"
                                                      density="compact"
                                                      color="blue-grey-darken-1"
                                            ></v-select>
                                        </v-col>
                                    </v-row>
                                    <v-row>
                                        <v-col></v-col>
                                        <v-col></v-col>
                                        <v-col>
                                            <v-btn @click="storeConsumption"
                                                   text="store"
                                                   variant="outlined"
                                            ></v-btn>
                                        </v-col>
                                    </v-row>
                                </v-form>
                            </template>
                            <template v-slot:item.product_id="{item}">
                                <Link :href="route('product.show', item.product_id)">
                                    {{item.product.rus}}
                                </Link>
                            </template>
                            <template v-slot:item.created_at="{item}">
                                {{date.format(item.created_at, 'fullDate')}}
                            </template>
                            <template v-slot:item.measure_id="{item}">
                                {{item.measure.name}}
                            </template>
                        </v-data-table>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="4">
                <v-card>
                    <v-card-title class="bg-gray-100">
                        Stages
                    </v-card-title>
                    <v-card-text class="text-amber">
                        <v-row v-for="stage in unit.stages">
                            <v-col>
                                <v-chip >
                                    {{stage.name}}
                                </v-chip>
                            </v-col>
                            <v-col>
                                <v-card title="Timing">
                                    <v-card-subtitle>
                                        <v-sheet class="font-sans text-sm">
                                            {{date.format(stage.pivot.startDate, 'normalDate')}}
                                        </v-sheet>
                                        <v-sheet>
                                            {{date.format(stage.pivot.startDate, 'year')}}
                                        </v-sheet>
                                    </v-card-subtitle>
                                    <v-card-text>
                                        Дней прошло:
                                        <v-chip>
                                            {{timeDiff(stage.pivot.startDate)}}
                                        </v-chip>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
        <v-row>
            <v-col>
                <v-card>
                    <v-card-title class="bg-fuchsia-950 text-fuchsia-100"
                    >
                        Sendings
                    </v-card-title>
                    <v-card-text>
                        <v-list density="compact"
                                variant="outlined"
                        >
                            <v-list-item v-for="email in unit.emails">
                                <v-row>
                                    <v-col>
                                        <span v-if="email.sendings.length > 0"
                                              class="p-1 text-sm bg-fuchsia-200 text-fuchsia-950"
                                        >
                                            {{email.address}}
                                        </span>
                                    </v-col>
                                </v-row>
                                <v-row>
                                    <v-col>
                                        <v-list density="compact">
                                            <v-list-item v-for="sending in email.sendings">
                                                <v-row>
                                                    <v-col>
                                                        <span class="text-sm">{{date.format(sending.created_at, 'fullDate')}}</span>
                                                    </v-col>
                                                    <v-col class="text-xs">
                                                        <span>{{sending.subject}}</span>
                                                    </v-col>
                                                </v-row>
                                            </v-list-item>
                                        </v-list>
                                    </v-col>
                                </v-row>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col>
                <v-list>
                    <v-list-item v-for="entity in unit.entities">
                        <v-list-item-subtitle v-if="entity.sales.length > 0">{{entity.name}}</v-list-item-subtitle>
                        <v-list>
                            <v-list-item v-for="sale in entity.sales">
                                <v-row>
                                    <v-col>
                                        {{date.format(sale.date, 'fullDate')}}
                                    </v-col>
                                    <v-col>
                                        {{sale.total}}
                                    </v-col>
                                </v-row>
                            </v-list-item>
                        </v-list>
                    </v-list-item>
                </v-list>
            </v-col>
            <v-col>

            </v-col>
        </v-row>
    </v-container>
</template>
