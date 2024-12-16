<script setup>
import {useDate} from "vuetify";
import {Link} from "@inertiajs/vue3";
import {onMounted, ref} from "vue";

const date = useDate();
const props = defineProps({
    check: Object,
})
let listCommodities = ref();
onMounted(()=>{
    listCommodities.value = props.check.commodities;
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col>
                {{date.format(check.date, 'fullDate')}}
            </v-col>
            <v-col>
                {{check.amount}}
            </v-col>
            <v-col>
                {{check.entity.name}}
            </v-col>
        </v-row>
        <v-row>
            <v-col></v-col>
            <v-col>
                <v-data-table :items="listCommodities">
                </v-data-table>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
