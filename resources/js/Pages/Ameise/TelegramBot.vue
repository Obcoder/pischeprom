<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {useDate} from "vuetify";
const date = useDate()
defineOptions({
    layout: VerwalterLayout,
})

const headersMessages = [
    {
        title: 'Content',
        key: 'content',
    },
    {
        title: 'Created',
        key: 'created_at',
    },
    {
        title: 'Chat',
        key: 'chat_id',
    },
    {
        title: 'Date',
        key: 'date',
    },
    {
        title: 'message_id',
        key: 'message_id',
    },
    {
        title: 'update_id',
        key: 'update_id',
    },
]
let messages = ref();
function indexMessages(){
    axios.get(route('messages.index')).then(function (response){
        messages.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}
let chats = ref();
function indexChats(){
    axios.get(route('api.chats')).then(function (response){
        chats.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

let showFormTelegramMessage = ref(false);
let chat = ref();
let message = ref();
async function sendTelegramMessage(chat, message) {
    try {
        const { data } = await axios.post(route('api.telegram.sendMessage'), {
            chat_id: chat,
            text: message,
        });

        message.value = null;
        indexMessages();
        return data;
    } catch (error) {
        console.error('Ошибка при отправке сообщения:', error);
    }
}
onMounted(()=>{
    indexChats();
    indexMessages();
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-btn text="sendMessage"
                   @click="showFormTelegramMessage = !showFormTelegramMessage"
                   variant="elevated"
            ></v-btn>
            <v-dialog v-model="showFormTelegramMessage"
                      width="775"
                      >
                <template v-slot:default="{isActive}">
                    <v-card>
                        <v-card-title>Form Message</v-card-title>
                        <v-card-text>
                            <v-row>
                                <v-autocomplete :items="chats"
                                                :item-title="'first_name'"
                                                :item-value="'numbers'"
                                                v-model="chat"
                                                label="chat"
                                                placeholder="Выбери chat"
                                                variant="outlined"
                                                density="compact"
                                                color="black"
                                ></v-autocomplete>
                            </v-row>
                            <v-row>
                                <v-textarea v-model="message"
                                            label="Message"
                                            variant="outlined"
                                            color="grey"
                                ></v-textarea>
                            </v-row>
                        </v-card-text>
                        <v-card-actions>
                            <v-btn @click="sendTelegramMessage(chat, message)"
                                   text="send"
                                   variant="elevated"
                                   density="comfortable"
                                   color="cyan-accent-4"
                            ></v-btn>
                        </v-card-actions>
                    </v-card>
                </template>
            </v-dialog>
        </v-row>
        <v-row>
            <v-col>
                <v-data-table :items="messages"
                              :headers="headersMessages"
                              items-per-page="500"
                              density="compact"
                              hover="true"
                >
                    <template v-slot:item.content="{item}">
                        <span class="font-sans text-sm">{{item.content}}</span>
                    </template>
                    <template v-slot:item.created_at="{item}">
                        <span class="font-sans text-sm">{{date.format(item.created_at, 'fullDateTime24h')}}</span>
                    </template>
                    <template v-slot:item.chat_id="{item}">
                        <span>{{item.chat.first_name}}</span>
                    </template>
                    <template v-slot:item.date="{item}">
                        <<span>{{date.format(item.date, 'fullDate')}}</span>
                    </template>
                </v-data-table>
            </v-col>
            <v-col>
                <v-data-table :items="chats"
                              density="comfortable"
                              hover="true"
                ></v-data-table>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
