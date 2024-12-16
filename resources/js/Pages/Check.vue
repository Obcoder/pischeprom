<script setup>
import {useDate} from "vuetify";
import {Link} from "@inertiajs/vue3";
import {onMounted, ref} from "vue";

const date = useDate();
const props = defineProps({
    check: Object,
})
let listCommodities = ref();
let headersCommodities = ref();
onMounted(()=>{
    listCommodities.value = props.check.commodities;
    headersCommodities.value = [
        {
            title: 'name',
            key: 'name',
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
        },
    ]
})
</script>

<template>
    <v-container class="border border-1 rounded">
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
            <v-col cols="1"></v-col>
            <v-col>
                <v-data-table :items="listCommodities"
                              :headers="headersCommodities"
                              hover="hover"
                >
                    <template v-slot:item.measure_id="{item}">
                        {{item.measure.name}}
                    </template>
                </v-data-table>
            </v-col>
            <v-col cols="1"></v-col>
        </v-row>
    </v-container>
</template>
