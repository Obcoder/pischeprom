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
const good = ref(null);
const loading = ref(true);
const error = ref(null);
// const route = useRoute();

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

function indexCurrencies(){
    axios.get(route('currencies.index')).then(function (response){
        currencies.value = response.data
    }).catch(function (error){
        console.error(error)
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
};

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

onMounted(() => {
    fetchGood();
    indexCurrencies()
});
</script>

<template>
    <v-container>
        <v-row>
            <v-col lg="3">
                <v-btn text="add price"
                       @click="showFormAddPrice = !showFormAddPrice"
                       variant="plain"
                       density="compact"
                       color="green"
                ></v-btn>
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
            <v-col cols="4">
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
