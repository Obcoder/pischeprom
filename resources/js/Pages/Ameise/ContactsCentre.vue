<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {useHead} from "@vueuse/head";
defineOptions({
    layout: VerwalterLayout,
})

const headersEmails = [
    {
        title: 'date',
        key: 'created_at',
    },
    {
        title: 'email',
        key: 'address',
    },
    {
        title: 'кол-во отправок',
        key: 'sendings.length',
    },
]

const emails = ref()

function indexEmails(){
    axios.get(route('emails.index')).then(function (response){
        emails.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}

onMounted(()=>{
    indexEmails()
})

useHead({
    title: `Contacts: emails, telephones, uris, addresses`,
    meta: [
        {
            name: 'description',
            content: `Все contacts in db`,
        }
    ]
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col>
                <v-data-table :items="emails"
                              :headers="headersEmails"
                              density="compact"
                              hover
                              >
                </v-data-table>
            </v-col>
            <v-col></v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>

<style scoped>

</style>
