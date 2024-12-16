<script setup>
import LayoutDefault from "@/Layouts/LayoutDefault.vue";
import {useDate} from "vuetify";
import {Link} from "@inertiajs/vue3";
const date = useDate();

defineOptions({
    layout: LayoutDefault,
})
const props = defineProps({
    check: Object,
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
                <v-data-table :items="check.commodities">
                </v-data-table>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
