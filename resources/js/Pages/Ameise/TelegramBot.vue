<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})

let messages = ref();
function indexMessages(){
    axios.get(route('api.messages')).then(function (response){
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
        // return data;
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
                                <v-text-field v-model="chat"
                                              label="chat_id"
                                              variant="outlined"
                                              color="black"
                                ></v-text-field>
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
                              density="comfortable"
                              hover="true"
                ></v-data-table>
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
