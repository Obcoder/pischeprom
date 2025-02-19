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
            <v-col></v-col>
            <v-col>
                <v-row>
                    <v-sheet color="purple"
                             class="font-Typingrad"
                    >
                        {{product.rus}}
                    </v-sheet><v-sheet color="purple">
                        {{product.eng}}
                    </v-sheet><v-sheet color="purple">
                        {{product.zh}}
                    </v-sheet><v-sheet color="purple">
                        {{product.es}}
                    </v-sheet>

                </v-row>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
