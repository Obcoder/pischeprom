<script setup>
import { useDate } from 'vuetify'
import {onMounted, ref} from "vue";
import axios from "axios";
import {useForm, Link} from "@inertiajs/vue3";
const date = useDate()
const props = defineProps({
    unit: Object,
    products: Object,
})
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
defineOptions({
    layout: VerwalterLayout,
})

let da = new Date();
function timeDiff(time){
    let d = new Date();
    let diffMilliseconds = d - Date.parse(time);
    let denominator = 1000 * 3600 * 24;
    return Math.round(diffMilliseconds/denominator);
}
let listProducts = ref();
function apiIndexProducts(){
    axios.get(route('api.products')).then(function (response){
        listProducts.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}
let listMeasures = ref();
function apiIndexMeasures(){
    axios.get(route('api.measures')).then(function (response){
        listMeasures.value = response.data;
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
            showFormConsumption = false;
            console.log("Форма скрыта:", showFormConsumption);
        },
    });
}

onMounted(()=> {
    apiIndexProducts();
    apiIndexMeasures();
})


const sendEmail = async () => {
    try {
        const response = await axios.post(route('api.mail'));
        // console.log(response);
    } catch (error) {
        console.error('Ошибка при отправке:', error);
    }
};
</script>

<template>
    <Head>
        <title>Unit</title>
    </Head>

    <v-container>
        <v-row class="h-1/3"
        >
            <v-col cols="6">
                <v-card>
                    <v-card-title class="bg-orange-accent-3">
                        {{unit.name}}</v-card-title>
                    <v-card-subtitle class="d-flex">
                        <v-sheet>
                            <div v-for="uri in unit.uris"
                            >
                                <a :href="uri.address"
                                   target="_blank"
                                >
                                    {{uri.address}}
                                </a>
                            </div>
                        </v-sheet>
                    </v-card-subtitle>
                    <v-card-subtitle>
                        <v-sheet>
                            <div v-for="label in unit.labels">
                                {{label.name}}
                            </div>
                        </v-sheet>
                    </v-card-subtitle>
                </v-card>
            </v-col>
            <v-col></v-col>
            <v-col></v-col>
        </v-row>
        <v-row>
            <v-col cols="5">
                <v-card>
                    <v-card-title>
                        Consumptions
                        <v-btn @click="showFormConsumption = !showFormConsumption"
                               text="добавить"
                               variant="elevated"
                        ></v-btn>
                    </v-card-title>
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
                                    <p>DEBUG TEXT</p>
                                    <v-row>
                                        <v-col>
                                            <v-autocomplete :items="listProducts"
                                                            :item-title="'rus'"
                                                            :item-value="'id'"
                                                            v-model="formConsumption.product_id"
                                                            variant="outlined"
                                            ></v-autocomplete>
                                        </v-col>
                                        <v-col>
                                            <v-text-field v-model="formConsumption.quantity"
                                                          label="quantity"
                                                          variant="solo"
                                            ></v-text-field>
                                        </v-col>
                                        <v-col>
                                            <v-select :items="listMeasures"
                                                      :item-title="'name'"
                                                      :item-value="'id'"
                                                      v-model="formConsumption.measure_id"
                                                      variant="outlined"
                                            ></v-select>
                                        </v-col>
                                    </v-row>
                                    <v-row>
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
            <v-btn @click="sendEmail"
                   text="Отправляем email"
                   variant="elevated"
            ></v-btn>
        </v-row>
        <v-row>
            <v-col>
                <v-card color="puple">
                    <v-card-text>
                        <v-list>
                            <v-list-item v-for="building in unit.buildings">
                                {{building.address}}
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col>
                <v-card>
                    <v-card-title>Cities</v-card-title>
                    <v-card-text>
                        <v-list>
                            <v-list-item v-for="building in unit.buildings"
                            >
                                {{building.city.name}}
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
