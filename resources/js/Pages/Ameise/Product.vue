<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {Link} from "@inertiajs/vue3";
import {route} from "ziggy-js";
defineOptions({
    layout: VerwalterLayout,
})
const props = defineProps({
    product: Object,
})

const headerConsumers = [
    {
        title: 'Unit',
        key: 'unit',
    },
    {
        title: 'Uris',
        key: 'unit.uris',
        align: 'start',
    },
    {
        title: 'Emails',
        key: 'unit.emails',
        align: 'start',
    },
    {
        title: 'Entities',
        key: 'unit.entities',
        align: 'start',
    },
    {
        title: 'Кол-во',
        key: 'quantity',
    },
    {
        title: 'ед изм',
        key: 'measure',
    },
    {
        title: 'Nodes',
        key: 'unit.buildings',
        align: 'start',
    },
]
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col cols="3">
                <v-card>
                    <v-card-title class="font-Typingrad text-black bg-indigo"
                    >
                        {{product.rus}}
                    </v-card-title>
                    <v-card-text>
                        <v-list density="compact">
                            <v-list-item>
                                {{product.eng}}
                            </v-list-item>
                            <v-list-item>
                                {{product.zh}}
                            </v-list-item>
                            <v-list-item>
                                {{product.es}}
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col>
                <v-card>
                    <v-card-title class="bg-cyan-950 text-cyan-100"
                    >
                        Manufacturers</v-card-title>
                    <v-card-text>
                        <v-list>
                            <v-list-item v-for="manufacturer in product.manufacturers">
                                <Link :href="route('web.unit.show', manufacturer.id)"
                                      class="font-OrelegaOneRegular"
                                >
                                    <span>{{manufacturer.name}}</span>
                                </Link>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col>
                <v-card>
                    <v-card-title>Goods</v-card-title>
                    <v-card-text>
                        <v-list>
                            <v-list-item v-for="good in product.goods">
                                {{good.name}}
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
        <v-row>
            <v-col cols="3"></v-col>
            <v-col cols="7">
                <v-card>
                    <v-card-title>Consumptions</v-card-title>
                    <v-card-text>
                        <v-data-table :items="product.consumers"
                                      :headers="headerConsumers"
                                      density="compact"
                        >
                            <template v-slot:item.unit="{item}">
                                <span>{{item.unit.name}}</span>
                            </template>
                            <template v-slot:item.unit.uris="{item}">
                                <div v-for="uri in item.unit.uris">{{uri.address}}</div>
                            </template>
                            <template v-slot:item.measure="{item}">
                                <span>{{item.measure.name}}</span>
                            </template>
                            <template v-slot:item.unit.emails="{item}">
                                <div v-for="email in item.unit.emails">{{email.address}}</div>
                            </template>
                            <template v-slot:item.unit.entities="{item}">
                                <div v-for="entity in item.unit.entities">{{entity.name}}</div>
                            </template>
                            <template v-slot:item.unit.buildings="{item}">
                                <div v-for="building in item.unit.buildings">
                                    <div>{{building.city.name}}</div>
                                    <div>{{building.address}}</div>
                                </div>
                            </template>
                        </v-data-table>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="3"></v-col>
        </v-row>
    </v-container>
</template>
