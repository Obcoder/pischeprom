<script setup>
import {Head, useForm, Link} from "@inertiajs/vue3";
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
defineOptions({
    layout: VerwalterLayout,
})
</script>

<template>
    <Head>
        <title>Flux Monitor</title>
    </Head>

    <v-container>
        <v-row>
            <v-col>
                Flux Monitor
            </v-col>
        </v-row>
    </v-container>
</template>
