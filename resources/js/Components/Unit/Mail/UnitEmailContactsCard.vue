<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'

import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'

const props = defineProps({
    unit: {
        type: Object,
        required: true,
    },
    emails: {
        type: Array,
        default: () => [],
    },
    canManage: {
        type: Boolean,
        default: false,
    },
    canSend: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['compose', 'refresh'])

const emailDialog = ref(false)
const saving = ref(false)
const feedback = ref(null)
const errors = ref({})
const selectedEmail = ref(null)
const emailSearch = ref('')

const directEmails = computed(() => props.unit?.emails || [])
const directEmailIds = computed(() => new Set(directEmails.value.map((email) => Number(email.id))))

const availableEmails = computed(() => {
    return (props.emails || [])
        .filter((email) => !directEmailIds.value.has(Number(email.id)))
        .map((email) => ({
            ...email,
            title: email.name ? `${email.address} — ${email.name}` : email.address,
        }))
})

const selectedEmailId = computed(() => {
    if (selectedEmail.value && typeof selectedEmail.value === 'object') {
        return selectedEmail.value.id || null
    }

    return null
})

const typedEmailAddress = computed(() => {
    if (selectedEmailId.value) return ''

    if (typeof selectedEmail.value === 'string' && selectedEmail.value.trim()) {
        return selectedEmail.value.trim()
    }

    return String(emailSearch.value || '').trim()
})

const canSubmitEmail = computed(() => Boolean(selectedEmailId.value || typedEmailAddress.value))

const emailErrors = computed(() => [
    ...(errors.value.email_id || []),
    ...(errors.value.address || []),
])

const entityEmails = computed(() => {
    const directAddresses = new Set(
        directEmails.value.map((email) => String(email.address || '').toLowerCase()),
    )

    return (props.unit?.entities || [])
        .flatMap((entity) => (entity.emails || []).map((email) => ({
            ...email,
            entity_name: entity.name,
        })))
        .filter((email) => email.address && !directAddresses.has(String(email.address).toLowerCase()))
        .filter((email, index, items) => {
            const address = String(email.address).toLowerCase()

            return items.findIndex((item) => String(item.address).toLowerCase() === address) === index
        })
})

function clearFeedback() {
    feedback.value = null
    errors.value = {}
}

function openEmailDialog() {
    clearFeedback()
    selectedEmail.value = null
    emailSearch.value = ''
    emailDialog.value = true
}

async function store(payload) {
    saving.value = true
    errors.value = {}

    try {
        const { data } = await axios.post(`/api/units/${props.unit.id}/emails`, payload)

        feedback.value = {
            tone: 'success',
            text: !data.attached
                ? 'Этот email уже привязан к Unit.'
                : data.created
                    ? 'Новый email создан и привязан к Unit.'
                    : 'Email из базы привязан к Unit.',
        }
        emailDialog.value = false
        selectedEmail.value = null
        emailSearch.value = ''
        emit('refresh')
    } catch (error) {
        errors.value = error.response?.data?.errors || {}
        feedback.value = {
            tone: 'error',
            text: error.response?.data?.message || 'Не удалось сохранить email.',
        }
    } finally {
        saving.value = false
    }
}

async function attachEmail() {
    if (selectedEmailId.value) {
        await store({ email_id: selectedEmailId.value })
        return
    }

    if (typedEmailAddress.value) {
        await store({ address: typedEmailAddress.value })
    }
}
</script>

<template>
    <BaseSectionCard
        title="Email-контакты"
        icon="mdi-email-outline"
        header-color="teal"
        compact
    >
        <template #actions>
            <div class="unit-email-contacts__actions">
                <v-btn
                    v-if="canSend"
                    size="small"
                    variant="flat"
                    color="white"
                    @click="emit('compose')"
                >
                    Написать письмо
                </v-btn>
                <v-btn
                    v-if="canManage"
                    size="small"
                    variant="outlined"
                    color="white"
                    @click="openEmailDialog"
                >
                    Добавить email
                </v-btn>
            </div>
        </template>

        <div class="unit-email-contacts__grid">
            <section>
                <div class="unit-email-contacts__section-title">
                    Прямые контакты Unit
                    <span>{{ directEmails.length }}</span>
                </div>

                <div v-if="directEmails.length" class="unit-email-contacts__list">
                    <a
                        v-for="email in directEmails"
                        :key="email.id"
                        :href="`mailto:${email.address}`"
                        class="unit-email-contacts__item"
                    >
                        <strong>{{ email.address }}</strong>
                        <small>{{ email.name || 'Без подписи' }}</small>
                    </a>
                </div>

                <div v-else class="unit-email-contacts__empty">
                    У Unit пока нет email. Создайте новый адрес или привяжите существующий.
                </div>
            </section>

            <section>
                <div class="unit-email-contacts__section-title">
                    Контакты связанных Entity
                    <span>{{ entityEmails.length }}</span>
                </div>

                <div v-if="entityEmails.length" class="unit-email-contacts__list">
                    <a
                        v-for="email in entityEmails"
                        :key="email.id || email.address"
                        :href="`mailto:${email.address}`"
                        class="unit-email-contacts__item"
                    >
                        <strong>{{ email.address }}</strong>
                        <small>{{ email.entity_name }}</small>
                    </a>
                </div>

                <div v-else class="unit-email-contacts__empty">
                    Дополнительных email через Entity нет.
                </div>
            </section>
        </div>

        <v-alert
            v-if="feedback"
            class="mt-3"
            density="compact"
            variant="tonal"
            :type="feedback.tone"
            closable
            @click:close="feedback = null"
        >
            {{ feedback.text }}
        </v-alert>

        <v-dialog v-model="emailDialog" max-width="620">
            <v-card rounded="xl">
                <v-card-title>Выбрать или создать email</v-card-title>
                <v-card-text>
                    <v-combobox
                        v-model="selectedEmail"
                        v-model:search="emailSearch"
                        :items="availableEmails"
                        item-title="title"
                        item-value="id"
                        label="Email"
                        hint="Выберите email из базы или введите новый прямо в этом поле"
                        persistent-hint
                        variant="outlined"
                        density="comfortable"
                        clearable
                        return-object
                        :error-messages="emailErrors"
                        no-data-text="Введите новый email"
                        @keydown.enter.prevent="attachEmail"
                    />
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="emailDialog = false">Отмена</v-btn>
                    <v-btn
                        color="teal-darken-2"
                        :disabled="!canSubmitEmail"
                        :loading="saving"
                        @click="attachEmail"
                    >
                        Добавить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </BaseSectionCard>
</template>

<style scoped>
.unit-email-contacts__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px;
}

.unit-email-contacts__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.unit-email-contacts__grid > section {
    min-width: 0;
    padding: 10px;
    border: 1px solid rgba(0, 82, 77, 0.13);
    border-radius: 14px;
    background: #fbfefd;
}

.unit-email-contacts__section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
    color: #315b58;
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.unit-email-contacts__section-title span {
    min-width: 24px;
    padding: 2px 7px;
    border-radius: 999px;
    background: rgba(0, 82, 77, 0.09);
    text-align: center;
}

.unit-email-contacts__list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 6px;
}

.unit-email-contacts__item {
    min-width: 0;
    padding: 8px 10px;
    border: 1px solid rgba(0, 82, 77, 0.12);
    border-radius: 10px;
    background: #fff;
    color: #00524d;
    text-decoration: none;
}

.unit-email-contacts__item:hover {
    border-color: rgba(0, 82, 77, 0.35);
    background: #f1fbf8;
}

.unit-email-contacts__item strong,
.unit-email-contacts__item small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.unit-email-contacts__item strong {
    font-size: 0.82rem;
}

.unit-email-contacts__item small,
.unit-email-contacts__empty {
    color: #75827f;
    font-size: 0.7rem;
}

.unit-email-contacts__empty {
    padding: 10px;
    border: 1px dashed rgba(0, 82, 77, 0.2);
    border-radius: 10px;
}

@media (max-width: 900px) {
    .unit-email-contacts__actions,
    .unit-email-contacts__grid {
        display: grid;
        grid-template-columns: 1fr;
    }

    .unit-email-contacts__actions :deep(.v-btn) {
        width: 100%;
    }
}
</style>
