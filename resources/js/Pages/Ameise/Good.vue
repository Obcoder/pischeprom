<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import { ref, onMounted, computed } from "vue";
import { useRoute } from "vue-router";
import axios from "axios";
import { useHead } from "@vueuse/head"; // Используем @vueuse/head
import {route} from "ziggy-js"
import {useDate} from "vuetify";
import {useForm} from "@inertiajs/vue3";
defineOptions({
    layout: VerwalterLayout,
});
const props = defineProps({
    good: Object,
})
const date = useDate()

const currencies = ref([])
const error = ref(null);
const good = ref(null)
const goods = ref([])
const loading = ref(true)
const measures = ref([])
const quotations = ref([])
const units = ref([])


const headerSales = ref([
    {
        key: 'date',
        title: 'Дата',
        sortable: true,
        align: 'start',
        width: '25%',
    },
    {
        key: 'entity.name',
        title: 'Entity',
        sortable: true,
        align: 'start',
        width: '38%',
    },
    {
        key: 'pivot.quantity',
        title: 'Кол-во',
        sortable: true,
        align: 'center',
        width: '10%',
    },
    {
        key: 'pivot.price',
        title: 'Цена',
        sortable: true,
        align: 'start',
    },
])

const showFormAddPrice = ref(false)


//     C U R R E N C I E S
function indexCurrencies(){
    axios.get(route('currencies.index')).then(function (response){
        currencies.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
// E N D  C U R R E N C I E S



//      G O O D S
function indexGoods(){
    axios.get(route('goods.index')).then(function (response){
        goods.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
// Загрузка данных товара
const fetchGood = async () => {
    try {
        const response = await axios.get(route('good.fetch', props.good.id))
        good.value = response.data;
        loading.value = false;
    } catch (err) {
        error.value = err.message;
        loading.value = false;
    }
}
// E N D  G O O D



//     M E A S U R E S
function indexMeasures(){
    axios.get(route('measures.index')).then(function (response){
        measures.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
// E N D  M E A S U R E S

//     P R I C E
const formAddPrice = useForm({
    good_id: props.good.id,
    price: null,
    currency_id: null,
})
function storePrice(){
    formAddPrice.post(route('web.price.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formAddPrice.reset()
            fetchGood()
        },
    })
}
// E N D  P R I C E



//     Q U O T A T I O N S
function indexQuotations(){
    axios.get(route('quotations.index')).then(function (response){
        quotations.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
const headerQuotations = ref([
    {
        key: 'good.name',
        title: 'Товар',
        align: 'start',
        sortable: true,
    },
    {
        key: 'unit.name',
        title: 'Unit',
        align: 'start',
        sortable: true,
    },
    {
        key: 'price',
        title: 'Цена',
        align: 'start',
        sortable: true,
    },
    {
        key: 'measure.name',
        title: 'measure',
        align: 'start',
        sortable: true,
    },
])
const dialogFormQuotation = ref(false)
const formQuotation = useForm({
    good_id: null,
    unit_id: null,
    price: null,
    measure_id: null,
})
function storeQuotation(){
    formQuotation.post(route('quotations.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: () => {
            formQuotation.reset()
            indexQuotations()
        },
    })
}
// E N D  Q U O T A T I O N S



//     U N I T S
function indexUnits(){
    axios.get(route('units.index')).then(function (response){
        units.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
// E N D  U N I T S


onMounted(() => {
    fetchGood()
    indexGoods()
    indexCurrencies()
    indexMeasures()
    indexQuotations()
    indexUnits()
})

// Динамические метатеги для SEO
useHead({
    title: computed(() => (good.value ? `${good.value.name} - Your Store` : "Loading...")),
    meta: [
        {
            name: "description",
            content: computed(() =>
                good.value ? good.value.description.slice(0, 160) : "Loading product details..."
            ),
        },
        { name: "keywords", content: computed(() => (good.value ? `${good.value.name}, product, store` : "product")) },
        { property: "og:title", content: computed(() => (good.value ? good.value.name : "Loading...")) },
        {
            property: "og:description",
            content: computed(() => (good.value ? good.value.description.slice(0, 160) : "Loading...")),
        },
        { property: "og:image", content: computed(() => (good.value ? good.value.image_url : "/default-image.jpg")) },
        { property: "og:url", content: computed(() => window.location.href) },
    ],
});
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col lg="3">
                <v-btn text="add price"
                       @click="showFormAddPrice = !showFormAddPrice"
                       variant="plain"
                       density="compact"
                       color="green"
                ></v-btn>
            </v-col>
            <v-col cols="2">
                <v-btn text="+Q"
                       @click="dialogFormQuotation = !dialogFormQuotation"
                       variant="elevated"
                       density="compact"
                       color="indigo"></v-btn>
                <v-dialog v-model="dialogFormQuotation"
                          width="771"
                >
                    <v-card>
                        <v-card-title>Form Quotation</v-card-title>
                        <v-card-text>
                            <v-form @submit.prevent>
                                <v-container fluid>
                                    <v-row>
                                        <v-autocomplete :items="goods"
                                                        :item-value="'id'"
                                                        :item-title="'name'"
                                                        v-model="formQuotation.good_id"
                                                        label="Товар"
                                                        variant="solo"
                                                        density="comfortable"
                                                        base-color="yellow"
                                        ></v-autocomplete>
                                    </v-row>
                                    <v-row>
                                        <v-autocomplete :items="units"
                                                        :item-value="'id'"
                                                        :item-title="'name'"
                                                        v-model="formQuotation.unit_id"
                                                        label="Unit"
                                                        variant="solo"
                                                        density="comfortable"
                                                        base-color="yellow"
                                        ></v-autocomplete>
                                    </v-row>
                                    <v-row>
                                        <v-col></v-col>
                                        <v-col>
                                            <v-text-field v-model="formQuotation.price"
                                                          label="Цена"
                                                          variant="solo"
                                                          density="default"
                                                          bg-color="grey"
                                            ></v-text-field>
                                        </v-col>
                                        <v-col>
                                            <v-autocomplete :items="measures"
                                                            :item-value="'id'"
                                                            :item-title="'name'"
                                                            v-model="formQuotation.measure_id"
                                                            label="Measure"
                                                            variant="solo"
                                                            density="compact"
                                                            base-color="grey"
                                            ></v-autocomplete>
                                        </v-col>
                                    </v-row>
                                </v-container>
                            </v-form>
                        </v-card-text>
                        <v-card-actions>
                            <v-divider vertical
                                       color="grey"
                                       opacity="0.9"></v-divider>
                            <v-btn text="store"
                                   @click="storeQuotation"
                                   variant="elevated"
                                   density="comfortable"
                                   color="grey"></v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>
            </v-col>
        </v-row>
        <v-row v-if="loading">
            <v-col>
                <v-progress-circular indeterminate color="primary"></v-progress-circular>
            </v-col>
        </v-row>
        <v-row v-else-if="error">
            <v-col>
                <v-alert type="error">{{ error }}</v-alert>
            </v-col>
        </v-row>
        <v-row v-else>
            <v-col lg="3" md="1">
                <v-card class="w-full">
                    <v-img
                        :src="good.ava_image || '/default-image.jpg'"
                        :alt="good.name"
                        lazy-src="/placeholder.jpg"
                        aspect-ratio="1"
                        cover
                        class="mb-4"
                    ></v-img>
                    <v-card-title>
                        <div class="whitespace-normal">
                            {{ good.name }}
                        </div>
                    </v-card-title>
                    <v-card-text>
                        <p>{{ good.description }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col lg="4">
                <v-data-table :items="quotations"
                              items-per-page="100"
                              :headers="headerQuotations"
                              fixed-header
                              height="360px"
                              density="compact"
                              hover
                ></v-data-table>
            </v-col>
            <v-col lg="3" md="1">
                <v-sheet v-if="showFormAddPrice">
                    <v-form @submit.prevent>
                        <v-row>
                            <v-col cols="8">
                                <v-text-field v-model="formAddPrice.price"
                                              label="Цена"
                                              variant="solo"
                                              density="comfortable"
                                              color="deep-purple"
                                ></v-text-field>
                            </v-col>
                            <v-col cols="4">
                                <v-select :items="currencies"
                                          :item-value="'id'"
                                          :item-title="'code'"
                                          v-model="formAddPrice.currency_id"
                                          label="Валюта"
                                          variant="solo"
                                          density="compact"
                                          color="grey"
                                ></v-select>
                            </v-col>
                        </v-row>
                        <v-row>
                            <v-col lg="2">
                                <v-btn text="store"
                                       @click="storePrice"
                                       variant="flat"
                                       density="comfortable"></v-btn>
                            </v-col>
                        </v-row>
                    </v-form>
                </v-sheet>
                <v-list border
                        rounded
                >
                    <v-list-item v-for="price in good.prices">
                        <v-row>
                            <v-col>
                                <span class="text-xs font-mono"
                                >
                                    {{date.format(price.created_at, 'fullDate')}}</span>
                            </v-col>
                            <v-col>
                                <span>{{price.price}}</span>
                            </v-col>
                            <v-col>
                                <span>{{price.currency.code}}</span>
                            </v-col>
                        </v-row>
                    </v-list-item>
                </v-list>
            </v-col>
        </v-row>
        <v-row>
            <v-col lg="4">
                <v-data-table :items="good.sales"
                              items-per-page="89"
                              :headers="headerSales"
                              fixed-header
                              height="510px"
                              density="comfortable"
                              hover
                >
                    <template v-slot:item.date="{item}">
                        <span class="text-xs">{{item.date}}</span>
                    </template>
                    <template v-slot:item.pivot.price="{item}">
                        <span class="font-bold font-ComfortaaVariableFont">{{item.pivot.price}}</span>
                    </template>
                </v-data-table>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
.v-card {
    margin-top: 20px;
}
.v-img {
    border-radius: 8px;
}
</style>
