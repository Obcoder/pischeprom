<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {computed, onMounted, reactive, ref} from "vue";
import axios from "axios";
import {useDate} from "vuetify";
import {useForm} from "@inertiajs/vue3";
import {format} from "date-fns";
import {route} from "ziggy-js";
defineOptions({
    layout: VerwalterLayout,
})
const date = useDate()

const tab = ref()

const buildings = ref([])
const cities = ref([])
const entities = ref([])
const entityClassifications = ref([])
const sales = ref([])
const sale = ref()
const telephones = ref([])

const searchEntities = ref('')

const headerEntities = [
    {
        title: 'name',
        key: 'name',
    },
    {
        title: 'classification',
        key: 'classification.name'
    },
    {
        title: 'cities',
        key: 'cities',
        align: 'left',
    },
    {
        title: 'buildings',
        key: 'buildings',
        align: 'left',
    },
    {
        title: 'telephones',
        key: 'telephones',
        align: 'left',
    },
    {
        title: 'unit',
        key: 'units',
    },
]
const headerSales = [
    {
        title: '+good',
        key: 'good',
    },
    {
        title: 'Created',
        key: 'created_at',
    },
    {
        title: 'Дата',
        key: 'date',
    },
    {
        title: 'Entity',
        key: 'entity_id',
    },
    {
        title: 'Сумма',
        key: 'total',
    },
]

//     B U I L D I N G S
function indexBuildings(){
    axios.get(route('buildings.index')).then(function (response){
        buildings.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
//     C I T I E S
function indexCities(){
    axios.get(route('cities.index')).then(function (response){
        cities.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
//     E N T I T I E S
function indexEntities(){
    axios.get(route('entities.index')).then(function (response){
        entities.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
const filteredEntities = computed(()=>{
    let filteredItems = entities.value

    if(searchEntities.value){
        const search = searchEntities.value.toLowerCase()
        filteredItems = filteredItems.filter(item => item.name.toLowerCase().includes(search))
    }

    return filteredItems
})
const showFormEntity = ref(false);
const formEntity = useForm({
    name: null,
    entity_classification_id: null,
    buildings: null,
    telephones: null,
    cities: null,
})
function storeEntity(){
    formEntity.post(route('web.entity.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formEntity.reset()
            indexEntities()
        },
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
//     G O O D S
const goods = ref([])
function indexGoods(){
    axios.get(route('goods.index')).then(function (response){
        goods.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
const good = reactive({})
function showGood(id){
    axios.get(route('goods.show', id)).then(function (response){
        good.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
//     S A L E S
function indexSales(){
    axios.get(route('sales.index')).then(function (response){
        sales.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
function showSale(id){
    axios.get(route('sales.show', id)).then(function (response){
        sale.value = response.data
    }).catch(function (error){
        console.log(error)
    });
}
let formSale = useForm({
    date: null,
    entity_id: null,
    total: null,
})
function storeSale(){
    formSale.date = format(new Date(formSale.date), 'yyyy-MM-dd HH:mm:ss');
    formSale.post(route('sales.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formSale.reset()
            indexSales()
        },
    })
}
//     T E L E P H O N E S
function indexTelephones(){
    axios.get(route('telephones.index')).then(function (response){
        telephones.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}

let showFormAttachGood = ref(false)
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
    showFormAttachGood.value = true;
}
function attachGood(){
    loadingAttach.value = true;
    formAttachGood.post(route('goodsales.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            snackbar.value = {
                show: true,
                text: 'Товар успешно привязан!',
            };
            showFormAttachGood.value = false; // Закрыть диалог
            formAttachGood.reset()
            indexSales()
        },
    })
}
const measures = ref([])
function indexMeasures(){
    axios.get(route('measures.index')).then(function (response){
        measures.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}

onMounted(()=>{
    indexBuildings()
    indexCities()
    indexEntities()
    indexEntityClassifications()
    indexGoods()
    indexMeasures()
    indexSales()
    indexTelephones()
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col>
                <v-card>
                    <v-tabs v-model="tab"
                            align-tabs="center"
                            color="deep-purple-accent-4"
                    >
                        <v-tab value="sales">Продажи</v-tab>
                        <v-tab value="entities">Entities</v-tab>
                    </v-tabs>
                    <v-tabs-window v-model="tab">
                        <v-tabs-window-item value="sales">
                            <v-container fluid>
                                <v-row>
                                    <v-col cols="1">
                                        <v-dialog width="1000">
                                            <template v-slot:activator="{ props: activatorProps }">
                                                <v-btn v-bind="activatorProps"
                                                       text="Новая продажа"
                                                ></v-btn>
                                            </template>
                                            <template v-slot:default="{isActive}">
                                                <v-card>
                                                    <v-card-title>Form Sale</v-card-title>
                                                    <v-card-text>
                                                        <v-form @submit.prevent>
                                                            <v-row>
                                                                <v-col>
                                                                    <v-autocomplete :items="entities"
                                                                                    :item-value="'id'"
                                                                                    :item-title="'name'"
                                                                                    v-model="formSale.entity_id"
                                                                                    density="compact"
                                                                                    variant="outlined"
                                                                                    chips
                                                                    ></v-autocomplete>
                                                                </v-col>
                                                            </v-row>
                                                            <v-row>
                                                                <v-col>
                                                                    <v-date-picker v-model="formSale.date"></v-date-picker>
                                                                </v-col>
                                                                <v-col>
                                                                    <v-text-field v-model="formSale.total"
                                                                                  label="Total"
                                                                                  placeholder="Сумма продажи"
                                                                                  density="comfortable"
                                                                                  variant="solo"
                                                                    ></v-text-field>
                                                                </v-col>
                                                            </v-row>
                                                        </v-form>
                                                    </v-card-text>
                                                    <v-card-actions>
                                                        <v-btn text="store"
                                                               @click="storeSale"
                                                               variant="elevated"
                                                               density="comfortable"
                                                               color="purple"
                                                        ></v-btn>
                                                    </v-card-actions>
                                                </v-card>
                                            </template>
                                        </v-dialog>
                                    </v-col>
                                </v-row>
                                <v-row>
                                    <v-col cols="5">
                                        <v-data-table :items="sales"
                                                      items-per-page="250"
                                                      :headers="headerSales"
                                                      fixed-header
                                                      height="1000px"
                                                      density="compact"
                                                      hover
                                        >
                                            <template v-slot:item.good="{item}">
                                                <v-btn text="+"
                                                       @click="openAttachDialog(item)"
                                                       variant="elevated"
                                                       color="grey"
                                                       density="compact"
                                                       size="20"
                                                ></v-btn>
                                            </template>
                                            <template v-slot:item.created_at="{item}">
                                                <span class="text-[8px] font-sans">{{date.format(item.created_at, 'fullDate')}}</span>
                                            </template>
                                            <template v-slot:item.date="{item}">
                                                <span class="text-xs font-sans">{{date.format(item.date, 'fullDateWithWeekday')}}</span>
                                            </template>
                                            <template v-slot:item.entity_id="{item}">
                                                <span class="text-sm font-RobotoRegular">{{item.entity.name}}</span>
                                            </template>
                                            <template v-slot:item.total="{item}">
                                                <span @click="showSale(item.id)"
                                                      class="cursor-pointer"
                                                >{{item.total}}</span>
                                            </template>
                                        </v-data-table>
                                        <v-dialog v-model="showFormAttachGood"
                                                  transition="dialog-bottom-transition"
                                                  width="1000"
                                        >
                                            <template v-slot:default="{isActive}">
                                                <v-card theme="dark">
                                                    <v-card-title>Form Attach Good</v-card-title>
                                                    <v-card-text class="text-red-700">
                                                        <v-row>
                                                            <v-col cols="7">
                                                                <v-form @submit.prevent>
                                                                    <v-row>
                                                                        <v-col>
                                                                            <v-autocomplete :items="goods"
                                                                                            :item-value="'id'"
                                                                                            :item-title="'name'"
                                                                                            v-model="formAttachGood.good_id"
                                                                                            label="Good"
                                                                                            variant="outlined"
                                                                                            @change="showGood(formAttachGood.good_id)"
                                                                            ></v-autocomplete>
                                                                        </v-col>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-col cols="3">
                                                                            <v-text-field v-model="formAttachGood.quantity"
                                                                                          label="Количество"
                                                                                          variant="outlined"
                                                                                          density="compact"
                                                                                          theme="dark"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                        <v-col cols="3">
                                                                            <v-select :items="measures"
                                                                                      :item-value="'id'"
                                                                                      :item-title="'name'"
                                                                                      v-model="formAttachGood.measure_id"
                                                                                      label="Measures"
                                                                                      variant="outlined"
                                                                                      density="compact"
                                                                            ></v-select>
                                                                        </v-col>
                                                                        <v-col cols="6">
                                                                            <v-text-field v-model="formAttachGood.price"
                                                                                          label="Price"
                                                                                          variant="outlined"
                                                                                          density="comfortable"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                    </v-row>
                                                                </v-form>
                                                            </v-col>
                                                            <v-col cols="5">
                                                                <v-sheet>
                                                                    <v-row>
                                                                        <v-col>
                                                                            <div><label>Total</label>{{sale.total}}</div>
                                                                        </v-col>
                                                                        <v-col>
                                                                            <label>Position Sum</label><span>{{formAttachGood.quantity * formAttachGood.price}}</span>
                                                                        </v-col>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-col>
                                                                            <div class="text-[9px]">{{good}}</div>
                                                                        </v-col>
                                                                    </v-row>
                                                                </v-sheet>
                                                            </v-col>
                                                        </v-row>
                                                    </v-card-text>
                                                    <v-card-actions>
                                                        <v-spacer />
                                                        <v-btn text="attach"
                                                               @click="attachGood"
                                                               variant="text"
                                                               density="compact"
                                                               color="teal-lighten-2"
                                                        ></v-btn>
                                                    </v-card-actions>
                                                </v-card>
                                            </template>
                                        </v-dialog>
                                        <!-- ✅ Уведомление об успехе -->
                                        <v-snackbar v-model="snackbar.show" :timeout="2000" color="green">
                                            {{ snackbar.text }}
                                        </v-snackbar>
                                    </v-col>
                                    <v-col>
                                        <v-list density="compact"
                                        >
                                            <v-list-item v-for="good in sale?.goods ?? []">
                                                <v-row>
                                                    <v-col cols="8">
                                                        <span class="cursor-pointer text-sm font-sans">{{good.name}}</span>
                                                    </v-col>
                                                    <v-col cols="1">
                                                        <span class="text-[11px]">{{good.pivot.price}}</span>
                                                    </v-col>
                                                    <v-col cols="1">
                                <span class="text-[10px]">
                                    {{good.pivot.quantity}}
                                </span>
                                                    </v-col>
                                                    <v-col>
                                <span class="text-sm">
                                    {{good.pivot.total}}
                                </span>
                                                    </v-col>
                                                </v-row>
                                            </v-list-item>
                                        </v-list>
                                    </v-col>
                                </v-row>
                            </v-container>
                        </v-tabs-window-item>
                        <v-tabs-window-item value="entities">
                            <v-container>
                                <v-row>
                                    <v-col lg="3">
                                        <v-text-field v-model="searchEntities"
                                                      label="Search for entities"
                                                      variant="solo"
                                                      density="compact"
                                                      hide-details></v-text-field>
                                    </v-col>
                                    <v-col lg="2">
                                        <v-btn text="+"
                                               @click="showFormEntity = !showFormEntity"
                                               variant="elevated"
                                               color="purple-darken-4"
                                        ></v-btn>
                                        <v-dialog v-model="showFormEntity"
                                                  width="990"
                                                  transition="dialog-top-transition"
                                        >
                                            <v-card>
                                                <v-card-title>Form Entity</v-card-title>
                                                <v-card-text>
                                                    <v-form @submit.prevent>
                                                        <v-row>
                                                            <v-text-field v-model="formEntity.name"
                                                                          label="Name"
                                                                          variant="outlined"
                                                            ></v-text-field>
                                                        </v-row>
                                                        <v-row>
                                                            <v-col>
                                                                <v-select :items="entityClassifications"
                                                                          :item-value="'id'"
                                                                          :item-title="'name'"
                                                                          v-model="formEntity.entity_classification_id"
                                                                          variant="outlined"
                                                                          label="Вид"
                                                                ></v-select>
                                                            </v-col>
                                                            <v-col>
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
                                                            </v-col>
                                                        </v-row>
                                                        <v-row>
                                                            <v-col>
                                                                <v-autocomplete :items="cities"
                                                                                :item-value="'id'"
                                                                                :item-title="'name'"
                                                                                v-model="formEntity.cities"
                                                                                label="Cities"
                                                                                placeholder="Населенный пункт"
                                                                                variant="solo"
                                                                                density="comfortable"
                                                                                color="grey"
                                                                                multiple
                                                                ></v-autocomplete>
                                                            </v-col>
                                                            <v-col>
                                                                <v-autocomplete :items="buildings"
                                                                                :item-value="'id'"
                                                                                :item-title="'address'"
                                                                                v-model="formEntity.buildings"
                                                                                multiple
                                                                                label="Buildings"
                                                                                placeholder="Адрес здания"
                                                                                variant="outlined"
                                                                                density="comfortable"
                                                                                color="teal"
                                                                ></v-autocomplete>
                                                            </v-col>
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
                                        </v-dialog>
                                    </v-col>
                                </v-row>
                                <v-row>
                                    <v-col cols="1"></v-col>
                                    <v-col>
                                        <v-data-table :items="filteredEntities"
                                                      items-per-page="125"
                                                      :headers="headerEntities"
                                                      fixed-header
                                                      density="compact"
                                                      hover
                                                      height="1000px"
                                        >
                                            <template v-slot:item.telephones="{item}">
                                                <div v-for="telephone in item.telephones">{{telephone.number}}</div>
                                            </template>
                                            <template v-slot:item.units="{item}">
                                                <div v-for="unit in item.units"
                                                     class="font-RobotoRegular text-sm"
                                                >{{unit.name}}</div>
                                            </template>
                                            <template v-slot:item.cities="{item}">
                                                <div v-for="city in item.cities"
                                                     class="text-sm"
                                                >{{city.name}}</div>
                                            </template>
                                            <template v-slot:item.buildings="{item}">
                                                <div v-for="building in item.buildings"
                                                     class="font-Typingrad text-[10px]">
                                                    <div>{{building.city.name}}</div>
                                                    <div>{{building.address}}</div>
                                                </div>
                                            </template>
                                        </v-data-table>
                                    </v-col>
                                    <v-col cols="1"></v-col>
                                </v-row>
                            </v-container>
                        </v-tabs-window-item>
                    </v-tabs-window>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
