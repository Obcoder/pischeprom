<script setup>
import LayoutDefault from '@/Layouts/LayoutDefault.vue'
import {onMounted, ref} from "vue";
import axios from "axios";
import {route} from "ziggy-js";
defineOptions({
    layout: LayoutDefault,
})
const props = defineProps({
    category: Object,
})

const category = ref()

function fetchCategory(id){
    axios.get(route('categories.show', id)).then(function (response){
        category.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}

onMounted(()=>{
    category.value = props.category
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col cols="1">
                <v-img :src="category.image"
                       aspect-ratio="1/1"
                       cover
                       rounded></v-img>
            </v-col>
        </v-row>
        <v-row>
            <v-col lg="1"></v-col>
            <v-col lg="3">
                <v-list>
                    <v-list-item v-for="product in category.products">
                        <div>{{product.name}}</div>
                    </v-list-item>
                </v-list>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>

</style>
