<script setup>
import { useDate } from 'vuetify'
import {onMounted, ref} from "vue";
import axios from "axios";
import {useForm, Link} from "@inertiajs/vue3";
import { useHead } from '@vueuse/head'
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
defineOptions({
    layout: VerwalterLayout,
})
const date = useDate()
const props = defineProps({
    unit: Object,
    products: Object,
})

const unit = ref()

function fetchUnit(id){
    axios.get(route('units.show'), id).then(function (response){
        unit.value = response.data
    }).catch(function (error){
        console.log()
    })
}
let products = ref();
function indexProducts(){
    axios.get(route('products.index')).then(function (response){
        products.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}
let measures = ref();
function indexMeasures(){
    axios.get(route('measures.index')).then(function (response){
        measures.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

let showFormConsumption = ref(false)
const formConsumption = useForm({
    unit_id: props.unit.id,
    product_id: null,
    quantity: null,
    measure_id: null,
})
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
let showFormBuilding = ref();
let buildings = ref([]);
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
const formatBuildingTitle = (building) => {
    if (!building) return '';
    return `${building.city?.name || ' - '}: ${building.address}`;
};
const formBuildingUnit = useForm({
    building_id: null,
    unit_id: props.unit.id,
    location_id: null,
})
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

let entities = ref();
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
let showFormAttachEntity = ref(false);
const formAttachEntity = useForm({
    entity_id: null,
    unit_id: props.unit.id,
})
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

let telephones = ref();
function indexTelephones(){
    axios.get(route('telephones.index')).then(function (response){
        telephones.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}


const headersConsumptions = ref([
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
const sendEmail = async () => {
    try {
        const response = await axios.post(route('api.mail'));
        // console.log(response);
    } catch (error) {
        console.error('Ошибка при отправке:', error);
    }
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

onMounted(()=> {
    indexEntities()
    indexMeasures()
    indexProducts()
    indexTelephones()
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
                    <v-card-title class="bg-5E35B1"
                    >
                        {{unit.name}}</v-card-title>
                    <v-card-subtitle>
                        <v-btn text="+email"
                               @click="attachEmail"
                               variant="flat"
                               size="30"
                               density="compact"></v-btn>
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
                                <v-list>
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
                                        {{email.address}}
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
                        ></v-btn>
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
                                      hover="true"
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
            <v-col>
                <v-card>
                    <v-card-title class="bg-cyan-900"
                    >
                        Узлы</v-card-title>
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
                                      :headers="headersConsumptions"
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
                <v-btn @click="sendEmail"
                       text="Отправляем email"
                       variant="elevated"
                ></v-btn>
                <v-btn @click="showFormBuilding = !showFormBuilding"
                       text="+ Building"
                       variant="elevated"
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
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
