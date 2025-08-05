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

const buildings = ref([])
const emails = ref([])
const entities = ref()
const labels = ref([])
const measures = ref()
const products = ref([])
const telephones = ref()
const uris = ref([])

const dialogFormAddEmail = ref(false)
const dialogFormAddUri = ref(false)
const dialogFormAttachEmail = ref(false)
const dialogFormAttachLabel = ref(false)
const dialogFormAttachUri = ref(false)
const dialogFormSendEmail = ref(false)
const showFormBuilding = ref(false)
const showFormConsumption = ref(false)
const showFormAttachManufacturer = ref(false)

const selectedProducts = ref([])

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
const headerSelectProductsForSending = ref(
    [
        {
            title: 'Name',
            align: 'start',
            key: 'rus',
        },
        {
            title: 'Цена',
            align: 'start',
            key: 'price',
        },
    ]
)

const formatBuildingTitle = (building) => {
    if (!building) return '';
    return `${building.city?.name || ' - '}: ${building.address}`;
};

function fetchUnit(id){
    axios.get(route('units.show'), id).then(function (response){
        props.unit.value = response.data
    }).catch(function (error){
        console.log()
    })
}

function indexEmails(){
    axios.get(route('emails.index')).then(function (response){
        emails.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
function indexEntities(like){
    axios.get(route('entities.index'), {
        params: {
            search: like,
        }
    }).then(function (response){
        entities.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}
function indexLabels(){
    axios.get(route('labels.index')).then(function (response){
        labels.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
function indexMeasures(){
    axios.get(route('measures.index')).then(function (response){
        measures.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}
function indexProducts(){
    axios.get(route('products.index')).then(function (response){
        products.value = response.data.sort((a, b) => {
            return a.rus.localeCompare(b.rus) // сортировка по полю 'rus'
        })
    }).catch(function (error){
        console.log(error);
    })
}
function indexTelephones(){
    axios.get(route('telephones.index')).then(function (response){
        telephones.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}
function indexUris(){
    axios.get(route('uris.index')).then(function (response){
        uris.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}

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
        onSuccess: ()=> {
            formAddEmail.reset();
            indexEmails()
        },
    })
}
function storeEntity(){
    formEntity.post(route('api.entity.store'), {
        replace: false,
        preserveState: false,
        preserveScroll: false,
        onSuccess: ()=> {
            formEntity.reset()
            indexEntities()
        },
    })
}
function apiIndexBuildings(like){
    axios.get(route('buildings.index'), {
        params: {
            search: like,
        }
    }).then(function (response){
        buildings.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

let showFormAttachEntity = ref(false);

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

let entityClassifications = ref();
function apiIndexEntityClassifications(){
    axios.get(route('api.entitiesclassifications')).then(function (response){
        entityClassifications.value = response.data;
    }).catch(function (error){
        console.log(error);
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

let da = new Date();
function timeDiff(time){
    let d = new Date();
    let diffMilliseconds = d - Date.parse(time);
    let denominator = 1000 * 3600 * 24;
    return Math.round(diffMilliseconds/denominator);
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
        onSuccess: ()=> {
            formAttachEmail.reset()
            fetchUnit(props.unit.value.id)
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
            indexUris()
            dialogFormAddUri.value = false
        },
    })
}

//       S E N D  E M A I L
const sendEmail = async (email) => {
    try {
        const response = await axios.post(route('api.mail'), {
            email: email,
            products: selectedProducts.value,
        })
        console.log(response);
        fetchUnit(props.unit.id)
        indexEmails()
    } catch (error) {
        console.error('Ошибка при отправке:', error);
    }
}

const onSelectedProductsUpdate = (newSelected) => {
    // Добавим поле price, если его нет
    selectedProducts.value = newSelected.map(product => ({
        ...product,
        price: product.price ?? ''
    }));
};

onMounted(()=> {
    indexEmails()
    indexEntities()
    indexLabels()
    indexMeasures()
    indexProducts()
    indexTelephones()
    indexUris()
    apiIndexBuildings();
    apiIndexEntityClassifications();
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
                                                    <v-autocomplete :items="emails"
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
                                                    <v-autocomplete :items="uris"
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
                                <v-list>
                                    <v-list-item v-for="file in files">
                                        <a :href="file.url" target="_blank">{{ file.name }}</a>
                                    </v-list-item>
                                </v-list>
                            </v-col>
                            <v-col>
                                <v-list density="compact">
                                    <v-list-item v-for="uri in unit.uris">
                                        <a :href="uri.address"
                                           target="_blank"
                                        >
                                            {{uri.address}}
                                        </a>
                                    </v-list-item>
                                </v-list>
                                <v-list>
                                    <v-list-item v-for="telephone in unit.telephones"
                                                 class="text-slate-800"
                                    >
                                        {{telephone.number}}
                                    </v-list-item>
                                </v-list>
                                <v-list>
                                    <v-list-item v-for="email in unit.emails"
                                                 base-color="teal-darken-4"
                                                 border
                                                 color="teal-lighten-5"
                                                 density="comfortable"
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
                                                          width="1000"
                                                >
                                                    <template v-slot:default="{isActive}">
                                                        <v-card>
                                                            <v-card-title>Form Send Email</v-card-title>
                                                            <v-card-text>
                                                                <v-data-table :items="products"
                                                                              v-model="selectedProducts"
                                                                              :headers="headerSelectProductsForSending"
                                                                              items-per-page="100"
                                                                              @update:model-value="onSelectedProductsUpdate"
                                                                              show-select
                                                                              return-object
                                                                              density="compact"
                                                                              hover
                                                                >
                                                                    <template v-slot:item.price="{item}">
                                                                        <v-text-field v-model="item.price"
                                                                                      label="Цена"
                                                                                      variant="outlined"
                                                                                      density="comfortable"
                                                                                      hide-details
                                                                        ></v-text-field>
                                                                    </template>
                                                                </v-data-table>
                                                                <pre>{{selectedProducts}}</pre>
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
                    </v-card-text>
                </v-card>
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
                                                    <v-autocomplete :items="labels"
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
            <v-col>
                <v-card>
                    <v-card-title class="bg-orange-200 text-slate-800">Entities</v-card-title>
                    <v-card-subtitle>
                        <v-btn text="Прикрепить"
                               @click="showFormAttachEntity = !showFormAttachEntity"
                               density="comfortable"
                               variant="text"
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
                                                <v-autocomplete :items="entities"
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
                               density="comfortable"
                               variant="text"
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
                                                <v-select :items="entityClassifications"
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
                                                <v-autocomplete :items="telephones"
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
                    <v-card-title class="bg-green-800 text-lime-200"
                    >
                        manufactures
                    </v-card-title>
                    <v-card-subtitle>
                        <v-btn text="att prod"
                               @click="showFormAttachManufacturer = !showFormAttachManufacturer"
                               variant="text"
                        ></v-btn>
                    </v-card-subtitle>
                    <v-card-text>
                        <v-list density="compact"
                        >
                            <v-list-item v-for="product in unit.manufactures"
                            >
                                <Link :href="route('product.show', product.id)">
                                    <span class="text-xs">
                                        {{product.rus}}
                                    </span>
                                </Link>
                            </v-list-item>
                        </v-list>
                        <v-sheet v-if="showFormAttachManufacturer">
                            <v-form @submit.prevent>
                                <v-row>
                                    <v-col>
                                        <v-autocomplete :items="products"
                                                        :item-value="'id'"
                                                        :item-title="'rus'"
                                                        v-model="formAttachManufacturer.product_id"
                                                        variant="solo"
                                                        density="compact"
                                                        color="black"></v-autocomplete>
                                    </v-col>
                                </v-row>
                                <v-row>
                                    <v-col></v-col>
                                    <v-col>
                                        <v-btn text="store"
                                               @click="attachManufacturer"
                                               variant="plain"></v-btn>
                                    </v-col>
                                </v-row>
                            </v-form>
                        </v-sheet>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
        <v-row>
            <v-col>
                <v-card>
                    <v-card-title class="bg-cyan-900"
                    >
                        Узлы
                    </v-card-title>
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
                                                    <v-autocomplete :items="buildings"
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
                            <v-list-item v-for="building in unit.buildings"
                            >
                                <span class="block mr-2">{{building.address}}</span>
                                <span class="block mr-2">{{building.city.name}}</span>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="5">
                <v-card>
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
                                      class="text-sm"
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
                                            <v-select :items="measures"
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
