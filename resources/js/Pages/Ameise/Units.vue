<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})

let searchUnitsLike = ref('');
let limitUnits = ref(107);
let listUnits = ref();
function apiIndexUnits(like, limit){
    axios.get(route('api.units'), {
        params: {
            search: like,
            limit: limit,
        }
    }).then(function (response){
        listUnits.value = response.data;
    }).catch(function (error){
        console.log();
    });
}

onMounted(()=>{
    apiIndexUnits('', limitUnits.value);
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col cols="6">
                <v-text-field v-model="searchUnitsLike"
                              @input="apiIndexUnits(searchUnitsLike, limitUnits)"
                              label="Search"
                              variant="outlined"
                              density="compact"
                ></v-text-field>
            </v-col>
            <v-col cols="3">
                <v-label>Limit</v-label>
                <input type="number"
                       v-model="limitUnits"
                       @change="apiIndexUnits(searchUnitsLike, limitUnits)"
                >
            </v-col>
            <v-col cols="3"></v-col>
        </v-row>

        <v-row>
            <v-col>
                <v-list>
                    <v-list-item v-for="unit in listUnits"
                                 class="rounded border border-emerald-900"
                    >
                        <v-card>
                            <v-card-title>
                                <span class="font-FIFARussia2018 font-[11px]"
                                >
                                    {{unit.name}}</span>
                            </v-card-title>
                            <v-card-text>
                                <v-chip v-if="unit.entities.length > 0"
                                >{{unit.entities.length}}</v-chip>
                            </v-card-text>
                        </v-card>
                    </v-list-item>
                </v-list>
            </v-col>
            <v-col></v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
