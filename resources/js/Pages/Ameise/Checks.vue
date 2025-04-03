<script setup>
import {Link, useForm} from "@inertiajs/vue3";
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})

const headersChecks = [
    {
        title: 'id',
        key: 'id',
    },
    {
        title: 'date',
        key: 'date',
    },
    {
        title: 'Entity',
        key: 'entity_id',
    },
    {
        title: 'Amount',
        key: 'amount',
    },
]
let checks = ref()
function indexChecks(){
    axios.get(route('checks.index')).then(function (response){
        checks.value = response.data
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
const formCheck = useForm({
    date: null,
    entity_id: null,
    amount: null,
})
function storeCheck(){
    formCheck.post(route('checks.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formCheck.reset()
            indexChecks()
        },
    });
}

onMounted(()=>{
    indexChecks()
    indexEntities()
})
</script>
<template>
    <v-row>
        <v-col cols="7">
            <v-menu>
                <v-date-picker></v-date-picker>
            </v-menu>
        </v-col>
        <v-col cols="5">
            <v-dialog transition="dialog-top-transition"
                      width="602"
            >
                <template v-slot:activator="{ props: activatorProps }">
                    <v-btn
                        v-bind="activatorProps"
                        text="+ check"
                        block
                    ></v-btn>
                </template>

                <template v-slot:default="{ isActive }">
                    <v-card>
                        <v-card-title>Check form</v-card-title>
                        <v-card-text>
                            <v-form @submit.prevent>
                                <v-row>
                                    <v-col>
                                        <v-autocomplete :items="entities"
                                                        :item-title="'name'"
                                                        :item-value="'id'"
                                                        v-model="formCheck.entity_id"
                                                        label="Сущность"
                                                        variant="outlined"
                                                        density="compact"
                                        ></v-autocomplete>
                                    </v-col>
                                </v-row>
                                <v-row>
                                    <v-col>
                                        <input type="date" v-model="formCheck.date">
                                    </v-col>
                                    <v-col>
                                        <v-text-field v-model="formCheck.amount"
                                                      label="Amount"
                                                      density="comfortable"
                                                      variant="outlined"
                                        ></v-text-field>
                                    </v-col>
                                </v-row>
                                <v-row>
                                    <v-col></v-col>
                                    <v-col></v-col>
                                    <v-col>
                                        <v-btn text="save"
                                               variant="outlined"
                                               @click="storeCheck"
                                        ></v-btn>
                                    </v-col>
                                </v-row>
                            </v-form>
                        </v-card-text>
                    </v-card>
                </template>
            </v-dialog>
        </v-col>
    </v-row>
    <v-data-table :items="checks"
                  :headers="headersChecks"
                  items-per-page="125"
                  density="compact"
                  hover="hover"
    >
        <template v-slot:item.entity_id="{item}">
            {{item.entity.name}}
        </template>
        <template v-slot:item.amount="{item}">
            <Link :href="route('checks.show', item.id)">
                {{item.amount}}
            </Link>
        </template>
    </v-data-table>
</template>

<style scoped>

</style>
