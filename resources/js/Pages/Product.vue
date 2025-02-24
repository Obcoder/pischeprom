<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
defineOptions({
    layout: VerwalterLayout,
})
const props = defineProps({
    product: Object,
})
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
                        <v-list>
                            <v-list-item v-for="consumption in product.consumptions">
                                {{consumption}}
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
