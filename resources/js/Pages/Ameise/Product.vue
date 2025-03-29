<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
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
        title: 'Кол-во',
        key: 'quantity',
    },
    {
        title: 'ед изм',
        key: 'measure',
    },
]
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col>
                <v-card>
                    <v-card-title color="indigo"
                                  class="font-Typingrad text-black"
                    >
                        {{product.rus}}
                    </v-card-title>
                    <v-card-text>
                        <v-list>
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
                    <v-card-title>Consumptions</v-card-title>
                    <v-card-text>
                        <v-data-table :items="product.consumers"
                                      :headers="headerConsumers"
                                      density="compact"
                        >
                            <template v-slot:item.unit="{item}">
                                {{item.unit.name}}
                            </template>
                            <template v-slot:item.measure="{item}">
                                {{item.measure.name}}
                            </template>
                        </v-data-table>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col></v-col>
        </v-row>
        <v-row>
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
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
