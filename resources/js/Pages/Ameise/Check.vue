<script setup>
import {useDate} from "vuetify";
import {Link} from "@inertiajs/vue3";
import {onBeforeUnmount, onMounted, reactive, ref, watch} from "vue";
import {loadCheckDictionary} from "@/Composables/checks/checkDictionaries.js";
import axios from "axios";
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {route} from "ziggy-js";
defineOptions({
    layout: VerwalterLayout,
})
const props = defineProps({
    check: Object,
})
const date = useDate()

const commodities = ref([])
const commoditiesInCheck = ref([])
const dictionaryError = ref('')
const dictionaryLoading = ref(false)
let dictionaryController
let dictionaryRequest = 0
watch(() => props.check.commodities, items => { commoditiesInCheck.value = items || [] }, { immediate: true })

//      C O M M O D I T I E S
async function loadDictionaries() {
    const request = ++dictionaryRequest
    dictionaryController?.abort()
    dictionaryController = new AbortController()
    dictionaryLoading.value = true
    dictionaryError.value = ''
    const results = await Promise.allSettled([
        loadCheckDictionary((...args) => axios.get(...args), route('commodities.index'), { per_page: 500, sort_by: 'name', sort_desc: false }, dictionaryController.signal),
        loadCheckDictionary((...args) => axios.get(...args), route('measures.index'), {}, dictionaryController.signal),
    ])
    if (request !== dictionaryRequest) return
    if (results[0].status === 'fulfilled') commodities.value = results[0].value
    if (results[1].status === 'fulfilled') measures.value = results[1].value
    if (results.some(result => result.status === 'rejected')) dictionaryError.value = 'Не удалось загрузить справочники.'
    dictionaryLoading.value = false
}
let dialogAddCommodity = ref(false)
const headersCommodities = [
    {
        title: 'name',
        key: 'name',
        width: '60%',
    },
    {
        title: 'quantity',
        key: 'pivot.quantity',
    },
    {
        title: 'measure',
        key: 'pivot.measure_id',
    },
    {
        title: 'price',
        key: 'pivot.price',
    },
    {
        title: 'total_price',
        key: 'pivot.total_price',
        class: 'bg-green-300',
    },
]
const savingCommodity = ref(false)
const commodityError = ref('')
const formCommodityCheck = reactive({
    check_id: null,
    commodity_id: null,
    quantity: null,
    measure_id: null,
    price: null,
})
async function storeCommodityToCheck() {
    if (savingCommodity.value) return
    const checkId = props.check.id
    savingCommodity.value = true
    commodityError.value = ''
    try {
        const response = await axios.post(route('checks.commodities.store', checkId), {
            ...formCommodityCheck,
            check_id: checkId,
        })
        if (props.check.id !== checkId) return
        const item = response.data.data || response.data
        commoditiesInCheck.value = [...commoditiesInCheck.value, { ...item.commodity, pivot: item }]
        Object.assign(formCommodityCheck, { check_id: null, commodity_id: null, quantity: null, measure_id: null, price: null })
        dialogAddCommodity.value = false
    } catch (error) {
        if (props.check.id === checkId) commodityError.value = error.response?.data?.message || 'Не удалось сохранить строку чека.'
    } finally {
        savingCommodity.value = false
    }
}

const measures = ref([])
onMounted(loadDictionaries)
onBeforeUnmount(() => {
    dictionaryRequest += 1
    dictionaryController?.abort()
})
</script>

<template>
    <v-container class="border border-1 rounded">
        <v-alert v-if="dictionaryError" type="error">
            {{ dictionaryError }}
            <v-btn variant="text" :loading="dictionaryLoading" @click="loadDictionaries">Повторить</v-btn>
        </v-alert>
        <v-row class="rounded border border-black-1 mx-10">
            <v-col>
                {{date.format(check.date, 'fullDate')}}
            </v-col>
            <v-col>
                <Link :href="route('entities.show', check.entity.id)"
                      class="text-xl font-RubikMedium"
                >
                    {{check.entity.name}}
                </Link>
            </v-col>
            <v-col>
                {{check.amount}}
            </v-col>
            <v-col lg="1">
                <v-btn text="+ commodity"
                       @click="dialogAddCommodity =! dialogAddCommodity"
                ></v-btn>
            </v-col>
        </v-row>
        <v-row>
            <v-col></v-col>
            <v-col lg="6">
                <v-data-table :items="commoditiesInCheck"
                              :headers="headersCommodities"
                              density="comfortable"
                              hover
                >
                    <template v-slot:top>
                        <v-form v-if="dialogAddCommodity"
                                @submit.prevent
                                class="bg-slate-600 p-2 border border-1 border-lime-950 rounded"
                        >
                            <v-alert v-if="commodityError" type="error" :text="commodityError" />
                            <v-row>
                                <v-col>
                                    <v-autocomplete :items="commodities"
                                                    :loading="dictionaryLoading"
                                                    :item-value="'id'"
                                                    :item-title="'name'"
                                                    v-model="formCommodityCheck.commodity_id"
                                                    label="Commodities"
                                                    variant="solo"
                                                    density="comfortable"
                                                    hide-details
                                    ></v-autocomplete>
                                </v-col>
                            </v-row>
                            <v-row>
                                <v-col>
                                    <v-text-field v-model="formCommodityCheck.quantity"
                                                  label="Кол-во"
                                                  variant="solo-filled"
                                                  density="comfortable"
                                                  hide-details
                                    ></v-text-field>
                                </v-col>
                                <v-col>
                                    <v-select :items="measures"
                                              :item-value="'id'"
                                              :item-title="'name'"
                                              v-model="formCommodityCheck.measure_id"
                                              variant="solo"
                                              density="comfortable"
                                              hide-details
                                    ></v-select>
                                </v-col>
                                <v-col>
                                    <v-text-field v-model="formCommodityCheck.price"
                                                  label="Цена"
                                                  variant="solo-filled"
                                                  density="comfortable"
                                                  class="bg-cyan-700"
                                                  hide-details
                                    ></v-text-field>
                                </v-col>
                            </v-row>
                            <v-row>
                                <v-col></v-col>
                                <v-col></v-col>
                                <v-col>
                                    <v-btn @click="storeCommodityToCheck"
                                           :loading="savingCommodity"
                                           text="store"
                                           variant="outlined"
                                           density="compact"
                                    ></v-btn>
                                </v-col>
                            </v-row>
                        </v-form>
                    </template>
                    <template v-slot:item.name="{item}">
                        <span class="text-sm font-sans text-zinc-800">{{item.name}}</span>
                    </template>
                </v-data-table>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
