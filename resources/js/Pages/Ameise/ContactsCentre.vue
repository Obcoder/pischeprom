<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {useHead} from "@vueuse/head";
import {route} from "ziggy-js";
import {useDate} from "vuetify";
import {Link} from "@inertiajs/vue3";
defineOptions({
    layout: VerwalterLayout,
})
const date = useDate()

const emails = ref()
const sendings = ref([])

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
const headersSendings = [
    {
        title: 'Date',
        key: 'created_at',
    },
    {
        title: 'Subject',
        key: 'subject',
    },
    {
        title: 'Email',
        key: 'email.address',
    },
]

function indexEmails(){
    axios.get(route('emails.index')).then(function (response){
        emails.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
function indexSendings(){
    axios.get(route('sendings.index')).then(function (response){
        sendings.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}

const daysSinceSending = (createdAt) => {
    const sendingDate = new Date(createdAt);
    const today = new Date();
    const diffTime = today - sendingDate;
    return Math.floor(diffTime / (1000 * 60 * 60 * 24));
};

onMounted(()=>{
    indexEmails()
    indexSendings()
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
            <v-col>
                <v-data-table :items="sendings"
                              :headers="headersSendings"
                              density="compact"
                              hover>
                    <template v-slot:item.created_at="{item}">
                        <v-row>
                            <v-col cols="9">
                                <span>{{date.format(item.created_at, 'fullDate')}}</span>
                            </v-col>
                            <v-col cols="3">
                                <span>{{daysSinceSending(item.created_at)}}</span>
                            </v-col>
                        </v-row>
                    </template>
                    <template v-slot:item.subject="{item}">
                        <span class="text-xs">{{item.subject}}</span>
                    </template>
                    <template v-slot:item.email="{item}">
                        <Link v-for="unit in item.units"
                              :href="route('web.unit.show', unit.id)">
                            {{unit.name}}
                        </Link>
                    </template>
                </v-data-table>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>

<style scoped>

</style>
