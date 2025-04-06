<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, reactive, ref} from "vue";
import axios from "axios";
import {useDate} from "vuetify";
import {useForm} from "@inertiajs/vue3";
import {format} from "date-fns";
defineOptions({
    layout: VerwalterLayout,
})
const date = useDate()

let sales = ref();
function indexSales(){
    axios.get(route('sales.index')).then(function (response){
        sales.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
let entities = ref()
function indexEntities(){
    axios.get(route('entities.index')).then(function (response){
        entities.value = response.data
    }).catch(function (error){
        console.log(error)
    })
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

const headersSales = [
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

let sale = ref()
function showSale(id){
    axios.get(route('sales.show', id)).then(function (response){
        sale.value = response.data
    }).catch(function (error){
        console.log(error)
    });
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

onMounted(()=>{
    indexSales()
    indexEntities()
    indexGoods()
    indexMeasures()
})
</script>

<template>
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
            <v-col cols="6">
                <v-data-table :items="sales"
                              :headers="headersSales"
                              items-per-page="250"
                              density="compact"
                              hover="true"
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
                        <span class="text-sm">{{date.format(item.created_at, 'fullDateWithWeekday')}}</span>
                    </template>
                    <template v-slot:item.date="{item}">
                        <span class="text-sm">{{date.format(item.date, 'fullDate')}}</span>
                    </template>
                    <template v-slot:item.entity_id="{item}">
                        <span class="text-sm">{{item.entity.name}}</span>
                    </template>
                    <template v-slot:item.total="{item}">
                        <span @click="showSale(item.id)"
                              class="cursor-pointer"
                        >
                            {{item.total}}
                        </span>
                    </template>
                </v-data-table>
                <v-dialog v-model="showFormAttachGood"
                          transition="dialog-bottom-transition"
                          width="900"
                >
                    <template v-slot:default="{isActive}">
                        <v-card>
                            <v-card-title>Form Attach Good</v-card-title>
                            <v-card-text>
                                <v-form @submit.prevent>
                                    <v-row>
                                        <v-col cols="1">
                                            <span>{{sale.total}}</span>
                                        </v-col>
                                        <v-col>
                                            <v-autocomplete :items="goods"
                                                            :item-value="'id'"
                                                            :item-title="'name'"
                                                            v-model="formAttachGood.good_id"
                                                            label="Good"
                                                            variant="outlined"
                                                            @change="showGood(formAttachGood.good_id)"
                                            ></v-autocomplete>
                                            <div>{{good}}</div>
                                        </v-col>
                                    </v-row>
                                    <v-row>
                                        <v-col cols="3">
                                            <v-text-field v-model="formAttachGood.quantity"
                                                          label="Количество"
                                                          variant="outlined"
                                                          density="compact"
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
                                    <v-row>
                                        <v-col>{{formAttachGood.quantity * formAttachGood.price}}</v-col>
                                    </v-row>
                                </v-form>
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
                        lines="one"
                >
                    <v-list-item v-for="good in sale.goods"
                                 density="compact"
                    >
                        <v-row>
                            <v-col cols="8">
                                <span class="cursor-pointer text-sm font-sans">
                                    {{good.name}}
                                </span>
                            </v-col>
                            <v-col cols="1">
                                <span class="text-[11px]">
                                    {{good.pivot.price}}
                                </span>
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
</template>
