<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})

let genera = ref();
function indexGenera(like){
    axios.get(route('api.genera'), {
        params: {
            search: like,
        }
    }).then(function (response){
        genera.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

onMounted(()=>{
    indexGenera();
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col cols="2">
                <v-list>
                    <v-list-item v-for="genus in genera"
                                 class="text-sm"
                                 density="compact"
                                 variant="elevated"
                                 color="light-green-lighten-5"
                    >
                        {{genus.name}}
                    </v-list-item>
                </v-list>
            </v-col>
        </v-row>
    </v-container>
</template>
