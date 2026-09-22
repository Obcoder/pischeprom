<script setup>
import axios from 'axios'
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    modelValue: Boolean,
    kind: { type: String, default: 'email' },
    good: { type: Object, required: true },
    quantity: { type: Number, default: 1 },
    purchase: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue', 'submitted'])
const page = usePage()
const saving = ref(false)
const result = ref(null)
const errors = ref({})
const errorMessage = ref('')
const errorSummary = ref(null)
const successHeading = ref(null)
const uncertain = ref(false)
const pendingRequest = ref(null)
const pendingGoodId = ref(null)
const activeKind = ref('email')
const requestToken = ref('')
const initialized = ref(false)
const activeGood = ref(props.good)
const activePurchase = ref(props.purchase)

const form = reactive({
    quantity: 1,
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    company: '',
    delivery_city: '',
    delivery_address: '',
    comment: '',
    proposed_price: '',
    bargain_scenario: 'custom',
    preferred_contact: 'email',
    max_contact: '',
    consent: false,
    website: '',
})

const variants = {
    email: {
        eyebrow: 'Поговорим о вашей задаче',
        title: 'Написать о товаре',
        description: 'Уточните условия, запросите документы или расскажите, что нужно для вашей закупки.',
        action: 'Отправить заявку',
        icon: 'mdi-email-outline',
    },
    bargain: {
        eyebrow: 'Хорошая сделка начинается с диалога',
        title: 'Ваша цена. Наш ход.',
        description: 'Предложите цену и объём. Мы рассмотрим условия и ответим, что можем предложить.',
        action: 'Предложить свою цену',
        icon: 'mdi-handshake-outline',
    },
    order: {
        eyebrow: 'От заявки — к поставке',
        title: 'Оформить заказ',
        description: 'Укажите количество и контакты. Менеджер подтвердит наличие, стоимость и условия поставки.',
        action: 'Отправить заказ',
        icon: 'mdi-package-variant-closed',
    },
}

const scenarios = [
    { value: 'volume', title: 'Беру объём', icon: 'mdi-package-variant', description: 'Крупная закупка', advice: 'Укажите весь объём закупки. Если нужна поставка частями, добавьте желаемый график в комментарий.' },
    { value: 'repeat', title: 'Покупаю регулярно', icon: 'mdi-calendar-sync-outline', description: 'Плановые поставки', advice: 'Расскажите, сколько товара нужно в месяц и как часто удобна отгрузка. Это поможет обсудить условия регулярных поставок.' },
    { value: 'ready', title: 'Готов к сделке', icon: 'mdi-lightning-bolt-outline', description: 'Есть сроки и бюджет', advice: 'Укажите, когда готовы оплатить и забрать товар. Менеджер проверит, какие условия доступны к вашей дате.' },
    { value: 'custom', title: 'Есть предложение', icon: 'mdi-message-text-outline', description: 'Обсудим вашу задачу', advice: 'Расскажите, что важно для сделки: бюджет, фасовка, доставка или документы. Даже если цену нельзя подтвердить, обсудим возможные условия.' },
]

const variant = computed(() => variants[activeKind.value] || variants.email)
const isBargain = computed(() => activeKind.value === 'bargain')
const isOrder = computed(() => activeKind.value === 'order')
const packageWeight = computed(() => {
    const value = Number(activePurchase.value.package_weight ?? activeGood.value.denominator)
    return Number.isFinite(value) && value > 0 ? value : null
})
const priceUnit = computed(() => activePurchase.value.price_unit_label || activePurchase.value.unit || (packageWeight.value ? 'кг' : 'упаковка'))
const pricedByWeight = computed(() => activePurchase.value.price_unit === 'kg' || priceUnit.value === 'кг')
const currency = computed(() => activePurchase.value.currency_code || activePurchase.value.currency || 'RUB')
const currencySymbol = computed(() => new Intl.NumberFormat('ru-RU', { style: 'currency', currency: currency.value }).formatToParts(0).find((part) => part.type === 'currency')?.value || currency.value)
const validQuantity = computed(() => {
    const quantity = Number(form.quantity)
    return Number.isInteger(quantity) && quantity > 0 && quantity <= 9999 ? quantity : 0
})
const totalWeight = computed(() => packageWeight.value && validQuantity.value ? packageWeight.value * validQuantity.value : null)
const publishedPrice = computed(() => {
    const value = Number(activePurchase.value.price)
    return Number.isFinite(value) && value > 0 ? value : null
})
const total = computed(() => {
    if (!validQuantity.value) return null
    if (isBargain.value) {
        const price = Number(form.proposed_price)
        if (!Number.isFinite(price) || price <= 0) return null
        if (pricedByWeight.value && !packageWeight.value) return null
        return price * validQuantity.value * (pricedByWeight.value ? packageWeight.value : 1)
    }
    const packagePrice = Number(activePurchase.value.package_price)
    return Number.isFinite(packagePrice) && packagePrice > 0 ? packagePrice * validQuantity.value : null
})
const scenarioAdvice = computed(() => scenarios.find((scenario) => scenario.value === form.bargain_scenario)?.advice)
const maxUrl = computed(() => {
    try {
        const url = new URL(activePurchase.value.max_url)
        return url.protocol === 'https:' && ['max.ru', 'www.max.ru', 'web.max.ru'].includes(url.hostname) ? url.href : null
    } catch {
        return null
    }
})
const money = (value) => new Intl.NumberFormat('ru-RU', {
    style: 'currency', currency: currency.value, maximumFractionDigits: 2,
}).format(value)
const number = (value) => new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 3 }).format(value)
const fieldError = (field) => {
    const value = errors.value[field]
    return Array.isArray(value) ? value[0] : value
}

function newToken() {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID()
    return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (character) => (
        Number(character) ^ globalThis.crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> Number(character) / 4
    ).toString(16))
}

watch(() => [props.modelValue, props.kind, props.good.id], ([open, kind]) => {
    if (!open || saving.value || uncertain.value) return
    activeGood.value = props.good
    activePurchase.value = props.purchase
    activeKind.value = variants[kind] ? kind : 'email'
    result.value = null
    errors.value = {}
    errorMessage.value = ''
    pendingRequest.value = null
    requestToken.value = newToken()
    form.quantity = Math.max(1, Math.min(9999, Math.floor(Number(props.quantity) || 1)))
    form.consent = false
    if (!initialized.value) {
        form.customer_name = page.props.auth?.user?.name || ''
        form.customer_email = page.props.auth?.user?.email || ''
        initialized.value = true
    }
}, { immediate: true })

function close() {
    if (!saving.value) emit('update:modelValue', false)
}

function clearError(event) {
    const field = event.target?.name
    if (field && errors.value[field]) delete errors.value[field]
}

async function focusError() {
    await nextTick()
    errorSummary.value?.focus()
}

async function submit() {
    if (saving.value || result.value) return
    saving.value = true
    errorMessage.value = ''
    errors.value = {}

    if (!uncertain.value || !pendingRequest.value) {
        pendingGoodId.value = activeGood.value.id
        pendingRequest.value = {
            kind: activeKind.value,
            quantity: Number(form.quantity),
            customer_name: form.customer_name.trim(),
            customer_email: form.customer_email.trim(),
            customer_phone: form.customer_phone.trim(),
            company: form.company.trim(),
            delivery_city: form.delivery_city.trim(),
            delivery_address: form.delivery_address.trim(),
            comment: form.comment.trim(),
            proposed_price: isBargain.value ? Number(form.proposed_price) : null,
            bargain_scenario: isBargain.value ? form.bargain_scenario : null,
            preferred_contact: form.preferred_contact,
            max_contact: form.preferred_contact === 'max' ? form.max_contact.trim() : null,
            consent: Boolean(form.consent),
            request_token: requestToken.value,
            website: form.website,
        }
    }

    try {
        const response = await axios.post(`/g/${pendingGoodId.value}/inquiries`, pendingRequest.value, {
            headers: { Accept: 'application/json' },
            timeout: 30000,
        })
        if (!response.data?.inquiry?.number) throw new Error('Incomplete inquiry response')
        result.value = response.data.inquiry
        uncertain.value = false
        emit('submitted', result.value)
        await nextTick()
        successHeading.value?.focus()
    } catch (error) {
        const status = error.response?.status
        if (status === 422) {
            uncertain.value = false
            errors.value = error.response?.data?.errors || {}
            errorMessage.value = 'Проверьте отмеченные поля — и отправьте заявку ещё раз.'
        } else if (status === 429) {
            errorMessage.value = 'Слишком много попыток за короткое время. Подождите немного и отправьте заявку снова.'
        } else if (status === 409) {
            uncertain.value = false
            errorMessage.value = error.response?.data?.message || 'Данные этой заявки уже изменились. Проверьте их перед повторной отправкой.'
            requestToken.value = newToken()
        } else if (status === 419) {
            uncertain.value = false
            errorMessage.value = 'Сессия истекла. Обновите страницу и заполните заявку снова.'
        } else if (status && status >= 400 && status < 500) {
            uncertain.value = false
            errorMessage.value = error.response?.data?.message || 'Заявку не удалось принять. Попробуйте снова или свяжитесь с нами в MAX.'
        } else {
            uncertain.value = true
            errorMessage.value = 'Не удалось получить подтверждение. Нажмите «Проверить и повторить»: мы проверим эту же заявку, не создавая дубль.'
        }
        await focusError()
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <v-dialog
        :model-value="modelValue"
        :persistent="saving"
        max-width="960"
        content-class="good-inquiry-overlay"
        aria-labelledby="good-inquiry-title"
        aria-describedby="good-inquiry-description"
        @update:model-value="value => !value && close()"
    >
        <section class="good-inquiry" :aria-busy="saving">
            <button v-if="result" class="inquiry-close" type="button" aria-label="Закрыть форму" :disabled="saving" @click="close">
                <v-icon icon="mdi-close" size="22" />
            </button>

            <div v-if="result" class="inquiry-success" role="status" aria-live="polite">
                <span class="success-mark"><v-icon icon="mdi-check" size="36" /></span>
                <p class="inquiry-eyebrow">Продолжим разговор</p>
                <h2 id="good-inquiry-title" ref="successHeading" tabindex="-1">{{ isOrder ? 'Заказ принят в работу' : isBargain ? 'Предложение принято в работу' : 'Заявка принята' }}</h2>
                <p id="good-inquiry-description" class="success-description ym-hide-content">
                    {{ isBargain ? 'Менеджер рассмотрит вашу цену и условия закупки.' : 'Менеджер проверит детали и свяжется с вами для согласования.' }}
                    {{ form.preferred_contact === 'max' ? 'Вы выбрали ответ в MAX; email остаётся дополнительным контактом.' : `Контакт для ответа: ${form.customer_email}.` }}
                </p>
                <div class="success-reference">
                    <span>{{ result.order_number ? 'Номер заказа' : 'Номер заявки' }}</span>
                    <strong>{{ result.order_number || result.number }}</strong>
                    <small v-if="result.order_number">Заявка {{ result.number }}</small>
                </div>
                <p class="success-note">{{ isOrder ? 'Оплата на этом этапе не требуется. Наличие, итоговую цену и доставку подтвердим отдельно.' : 'Отправка заявки не обязывает к покупке. Все условия согласуем с вами.' }}</p>
                <a v-if="maxUrl" :href="maxUrl" target="_blank" rel="noopener noreferrer" class="inquiry-button inquiry-button--primary">
                    Продолжить в MAX <v-icon icon="mdi-arrow-top-right" size="18" />
                </a>
                <p v-if="maxUrl" class="success-max-hint">Напишите первым и укажите номер {{ result.order_number || result.number }}, чтобы менеджер нашёл обращение.</p>
                <button type="button" class="inquiry-button inquiry-button--plain" @click="close">Вернуться к товару</button>
            </div>

            <template v-else>
                <header class="inquiry-header">
                    <button class="inquiry-close" type="button" aria-label="Закрыть форму" :disabled="saving" @click="close">
                        <v-icon icon="mdi-close" size="22" />
                    </button>
                    <p class="inquiry-eyebrow">{{ variant.eyebrow }}</p>
                    <h2 id="good-inquiry-title">{{ variant.title }}</h2>
                    <p id="good-inquiry-description">{{ variant.description }}</p>
                </header>

                <div class="inquiry-layout">
                    <details :key="`${activeGood.id}-${activeKind}`" class="inquiry-mobile-summary">
                        <summary>
                            <img v-if="activeGood.ava_thumb || activeGood.ava_image" :src="activeGood.ava_thumb || activeGood.ava_image" alt="" />
                            <span class="mobile-summary-product"><strong>{{ activeGood.name }}</strong><small>Товар и расчёт <span aria-hidden="true">·</span> {{ validQuantity ? `${number(validQuantity)} уп.` : 'Укажите количество' }}</small></span>
                            <v-icon class="mobile-summary-chevron" icon="mdi-chevron-down" size="22" />
                        </summary>
                        <div class="mobile-summary-content">
                            <dl class="summary-details">
                                <div v-if="packageWeight"><dt>В упаковке</dt><dd>{{ number(packageWeight) }} кг</dd></div>
                                <div v-if="publishedPrice"><dt>Цена на сайте</dt><dd>{{ money(publishedPrice) }} / {{ priceUnit }}</dd></div>
                                <div v-if="totalWeight"><dt>Общий вес</dt><dd>{{ number(totalWeight) }} кг</dd></div>
                            </dl>
                            <div class="summary-total">
                                <span>{{ isBargain ? 'По вашей цене' : 'Предварительная сумма' }}</span>
                                <strong>{{ total !== null ? money(total) : isBargain ? 'Предложите цену' : 'Уточним в ответе' }}</strong>
                                <p>{{ isBargain ? 'Менеджер рассмотрит предложение. Все условия согласуем с вами.' : 'Без доставки. Стоимость и наличие подтвердит менеджер.' }}</p>
                            </div>
                        </div>
                    </details>
                    <aside class="inquiry-summary" aria-label="Товар и предварительный расчёт">
                        <div class="summary-product">
                            <img v-if="activeGood.ava_thumb || activeGood.ava_image" :src="activeGood.ava_thumb || activeGood.ava_image" :alt="activeGood.name" class="summary-image" />
                            <div>
                                <span class="summary-caption">Ваш товар</span>
                                <h3>{{ activeGood.name }}</h3>
                                <p v-if="packageWeight" class="summary-weight">{{ number(packageWeight) }} кг в упаковке</p>
                            </div>
                        </div>
                        <dl class="summary-details">
                            <div v-if="publishedPrice"><dt>Цена на сайте</dt><dd>{{ money(publishedPrice) }} / {{ priceUnit }}</dd></div>
                            <div><dt>Количество</dt><dd>{{ validQuantity ? `${number(validQuantity)} уп.` : 'Укажите' }}</dd></div>
                            <div v-if="totalWeight"><dt>Общий вес</dt><dd>{{ number(totalWeight) }} кг</dd></div>
                        </dl>
                        <div class="summary-total" aria-live="polite" aria-atomic="true">
                            <span>{{ isBargain ? 'По вашей цене' : 'Предварительная сумма' }}</span>
                            <strong>{{ total !== null ? money(total) : isBargain ? 'Предложите цену' : 'Уточним в ответе' }}</strong>
                            <p>{{ isBargain ? 'Предложение рассмотрит менеджер. Итоговые условия согласуем с вами.' : 'Без доставки. Стоимость и наличие подтвердит менеджер.' }}</p>
                        </div>
                        <div class="summary-process">
                            <v-icon :icon="variant.icon" size="22" />
                            <p>Заявка → ответ менеджера → согласование → поставка</p>
                        </div>
                    </aside>

                    <form class="inquiry-form ym-disable-keys" @submit.prevent="submit" @input="clearError">
                        <div v-if="errorMessage" ref="errorSummary" class="inquiry-error" tabindex="-1" role="alert">
                            <v-icon icon="mdi-alert-circle-outline" size="20" />
                            <div>
                                <p>{{ errorMessage }}</p>
                                <ul v-if="Object.keys(errors).length">
                                    <li v-for="(messages, field) in errors" :key="field">{{ Array.isArray(messages) ? messages[0] : messages }}</li>
                                </ul>
                            </div>
                        </div>

                        <fieldset :disabled="saving || uncertain" class="inquiry-fields">
                            <legend class="sr-only">Данные заявки</legend>
                            <div class="form-section-title"><span>01</span><h3>{{ isBargain ? 'Предложите условия' : 'Детали закупки' }}</h3></div>

                            <div v-if="isBargain" class="scenario-group" role="radiogroup" aria-label="Сценарий закупки">
                                <label v-for="scenario in scenarios" :key="scenario.value" class="scenario-option" :class="{ 'is-selected': form.bargain_scenario === scenario.value }">
                                    <input v-model="form.bargain_scenario" type="radio" name="bargain_scenario" :value="scenario.value" />
                                    <v-icon :icon="scenario.icon" size="20" />
                                    <span><strong>{{ scenario.title }}</strong><small>{{ scenario.description }}</small></span>
                                </label>
                            </div>
                            <p v-if="isBargain" class="scenario-advice" aria-live="polite">{{ scenarioAdvice }}</p>

                            <div class="field-grid">
                                <div class="inquiry-field">
                                    <label for="inquiry-quantity">Количество упаковок <span>*</span></label>
                                    <div class="quantity-input">
                                        <button type="button" aria-label="Уменьшить количество" :disabled="Number(form.quantity) <= 1" @click="form.quantity = Math.max(1, Math.floor(Number(form.quantity) || 1) - 1)">−</button>
                                        <input id="inquiry-quantity" v-model="form.quantity" name="quantity" type="number" min="1" max="9999" step="1" required inputmode="numeric" :aria-invalid="Boolean(fieldError('quantity'))" aria-describedby="inquiry-quantity-hint inquiry-quantity-error" />
                                        <button type="button" aria-label="Увеличить количество" :disabled="Number(form.quantity) >= 9999" @click="form.quantity = Math.min(9999, Math.floor(Number(form.quantity) || 0) + 1)">+</button>
                                    </div>
                                    <small id="inquiry-quantity-hint" class="field-hint">{{ packageWeight ? `1 упаковка = ${number(packageWeight)} кг` : 'От 1 до 9 999 упаковок' }}</small>
                                    <small v-if="fieldError('quantity')" id="inquiry-quantity-error" class="field-error">{{ fieldError('quantity') }}</small>
                                </div>
                                <div v-if="isBargain" class="inquiry-field">
                                    <label for="inquiry-price">Ваша цена за {{ pricedByWeight ? 'кг' : 'упаковку' }} <span>*</span></label>
                                    <div class="price-input"><input id="inquiry-price" v-model="form.proposed_price" name="proposed_price" type="number" inputmode="decimal" min="0.01" max="99999999.99" step="0.01" placeholder="Укажите цену" required :aria-invalid="Boolean(fieldError('proposed_price'))" aria-describedby="inquiry-price-error" /><span>{{ currencySymbol }}</span></div>
                                    <small v-if="fieldError('proposed_price')" id="inquiry-price-error" class="field-error">{{ fieldError('proposed_price') }}</small>
                                </div>
                                <div v-if="!isBargain" class="inquiry-field">
                                    <label for="inquiry-city">Город поставки</label>
                                    <input id="inquiry-city" v-model="form.delivery_city" class="ym-disable-keys" name="delivery_city" type="text" maxlength="160" autocomplete="address-level2" placeholder="Например, Москва" :aria-invalid="Boolean(fieldError('delivery_city'))" />
                                    <small v-if="fieldError('delivery_city')" class="field-error">{{ fieldError('delivery_city') }}</small>
                                </div>
                            </div>

                            <div v-if="isOrder" class="inquiry-field">
                                <label for="inquiry-address">Адрес или способ получения</label>
                                <input id="inquiry-address" v-model="form.delivery_address" class="ym-disable-keys" name="delivery_address" type="text" maxlength="1000" autocomplete="street-address" placeholder="Адрес доставки или «Самовывоз»" :aria-invalid="Boolean(fieldError('delivery_address'))" />
                                <small class="field-hint">Можно согласовать с менеджером после заявки.</small>
                                <small v-if="fieldError('delivery_address')" class="field-error">{{ fieldError('delivery_address') }}</small>
                            </div>

                            <div class="inquiry-field">
                                <label for="inquiry-comment">{{ isBargain ? 'Что поможет договориться?' : 'Ваш вопрос или пожелания' }}</label>
                                <textarea id="inquiry-comment" v-model="form.comment" class="ym-disable-keys" name="comment" rows="3" maxlength="3000" :placeholder="isBargain ? 'Срок закупки, регулярный объём, город, условия оплаты…' : 'Срок поставки, нужные документы, особенности заказа…'" :aria-invalid="Boolean(fieldError('comment'))" />
                                <small v-if="fieldError('comment')" class="field-error">{{ fieldError('comment') }}</small>
                            </div>

                            <div class="form-section-title"><span>02</span><h3>Как с вами связаться</h3></div>
                            <p class="required-note">Поля со звёздочкой обязательны</p>
                            <div class="field-grid">
                                <div class="inquiry-field">
                                    <label for="inquiry-name">Ваше имя <span>*</span></label>
                                    <input id="inquiry-name" v-model="form.customer_name" class="ym-disable-keys" name="customer_name" type="text" maxlength="160" required autocomplete="name" placeholder="Как к вам обращаться" :aria-invalid="Boolean(fieldError('customer_name'))" />
                                    <small v-if="fieldError('customer_name')" class="field-error">{{ fieldError('customer_name') }}</small>
                                </div>
                                <div class="inquiry-field">
                                    <label for="inquiry-email">Email <span>*</span></label>
                                    <input id="inquiry-email" v-model="form.customer_email" class="ym-disable-keys" name="customer_email" type="email" maxlength="254" required autocomplete="email" inputmode="email" placeholder="you@company.ru" :aria-invalid="Boolean(fieldError('customer_email'))" />
                                    <small v-if="fieldError('customer_email')" class="field-error">{{ fieldError('customer_email') }}</small>
                                </div>
                                <div class="inquiry-field">
                                    <label for="inquiry-phone">Телефон</label>
                                    <input id="inquiry-phone" v-model="form.customer_phone" class="ym-disable-keys" name="customer_phone" type="tel" minlength="7" maxlength="64" autocomplete="tel" placeholder="+7 999 123-45-67" :aria-invalid="Boolean(fieldError('customer_phone'))" />
                                    <small v-if="fieldError('customer_phone')" class="field-error">{{ fieldError('customer_phone') }}</small>
                                </div>
                                <div class="inquiry-field">
                                    <label for="inquiry-company">Компания / ИП</label>
                                    <input id="inquiry-company" v-model="form.company" class="ym-disable-keys" name="company" type="text" maxlength="255" autocomplete="organization" placeholder="Если покупаете для бизнеса" :aria-invalid="Boolean(fieldError('company'))" />
                                    <small v-if="fieldError('company')" class="field-error">{{ fieldError('company') }}</small>
                                </div>
                            </div>

                            <div v-if="maxUrl" class="contact-preference">
                                <span>Где удобнее получить ответ?</span>
                                <div class="contact-options" role="radiogroup" aria-label="Способ ответа">
                                    <label :class="{ 'is-selected': form.preferred_contact === 'email' }"><input v-model="form.preferred_contact" type="radio" name="preferred_contact" value="email" /><v-icon icon="mdi-email-outline" size="17" /> По email</label>
                                    <label :class="{ 'is-selected': form.preferred_contact === 'max' }"><input v-model="form.preferred_contact" type="radio" name="preferred_contact" value="max" /><v-icon icon="mdi-message-outline" size="17" /> В MAX</label>
                                </div>
                            </div>
                            <div v-if="form.preferred_contact === 'max'" class="inquiry-field">
                                <label for="inquiry-max">Ваш контакт в MAX <span>*</span></label>
                                <input id="inquiry-max" v-model="form.max_contact" class="ym-disable-keys" name="max_contact" type="text" maxlength="255" required placeholder="Ссылка на профиль или телефон в MAX" :aria-invalid="Boolean(fieldError('max_contact'))" />
                                <small class="field-hint">После отправки откройте наш MAX и напишите номер заявки, чтобы начать диалог.</small>
                                <small v-if="fieldError('max_contact')" class="field-error">{{ fieldError('max_contact') }}</small>
                            </div>
                            <div class="inquiry-trap" aria-hidden="true"><label for="inquiry-website">Ваш сайт</label><input id="inquiry-website" v-model="form.website" name="website" type="text" tabindex="-1" autocomplete="off" /></div>
                            <label class="inquiry-consent"><input v-model="form.consent" type="checkbox" name="consent" required :aria-invalid="Boolean(fieldError('consent'))" /><span>Согласен на обработку персональных данных для ответа на заявку в соответствии с <a href="/privacy-policy" target="_blank" rel="noopener noreferrer">политикой конфиденциальности</a>.</span></label>
                            <small v-if="fieldError('consent')" class="field-error">{{ fieldError('consent') }}</small>
                        </fieldset>

                        <div class="inquiry-submit-area">
                            <div class="mobile-submit-total" aria-live="polite" aria-atomic="true">
                                <span>{{ isBargain ? 'Ваше предложение' : 'Предварительная сумма' }}<small>{{ validQuantity ? `${number(validQuantity)} уп.` : 'Укажите количество' }}{{ totalWeight ? ` · ${number(totalWeight)} кг` : '' }}</small></span>
                                <strong>{{ total !== null ? money(total) : 'Уточним' }}</strong>
                            </div>
                            <button type="submit" class="inquiry-button inquiry-button--primary inquiry-submit" :disabled="saving">
                                <v-progress-circular v-if="saving" indeterminate size="20" width="2" />
                                {{ saving ? 'Отправляем…' : uncertain ? 'Проверить и повторить' : variant.action }}
                                <v-icon v-if="!saving" icon="mdi-arrow-right" size="20" />
                            </button>
                            <p class="submit-note">{{ isOrder ? 'Без онлайн-оплаты. Сначала подтвердим все детали.' : 'Заявку получит команда ПИЩЕПРОМ-СЕРВЕР. Без подписки на рассылку.' }}</p>
                        </div>
                    </form>
                </div>
            </template>
        </section>
    </v-dialog>
</template>

<style scoped>
.good-inquiry { --inquiry-brand: #800000; --inquiry-ink: #381f24; --inquiry-muted: #78656a; position: relative; max-height: min(94dvh, 1100px); overflow-y: auto; background: #fffaf8; color: var(--inquiry-ink); border-radius: 24px; box-shadow: 0 28px 100px #50000030; font-family: inherit; }
.good-inquiry *, .good-inquiry *::before, .good-inquiry *::after { box-sizing: border-box; }
.inquiry-close { position: absolute; top: 20px; right: 20px; z-index: 2; display: grid; place-items: center; width: 38px; height: 38px; border: 1px solid #eadcde; border-radius: 50%; background: #fffaf8; color: var(--inquiry-ink); }
.inquiry-close:hover { background: #f8e9e9; }
.inquiry-header { position: relative; padding: 38px 72px 28px 36px; border-bottom: 1px solid #eadcde; }
.inquiry-eyebrow { margin: 0 0 10px; color: #8b555c; font-size: 11px; line-height: 1.5; font-weight: 750; letter-spacing: .11em; text-transform: uppercase; }
.inquiry-header h2, .inquiry-success h2 { margin: 0; font-size: clamp(25px, 4vw, 34px); line-height: 1.15; font-weight: 750; letter-spacing: -.035em; color: var(--inquiry-brand); }
.inquiry-header > p:last-child { margin: 12px 0 0; max-width: 630px; color: var(--inquiry-muted); font-size: 14px; line-height: 1.6; }
.inquiry-mobile-summary, .mobile-submit-total { display: none; }
.inquiry-layout { display: grid; grid-template-columns: 255px minmax(0, 1fr); }
.inquiry-summary { padding: 30px 24px; background: #fbefed; border-right: 1px solid #eadcde; }
.summary-image { width: 96px; height: 96px; object-fit: contain; display: block; border-radius: 14px; background: white; margin-bottom: 18px; }
.summary-caption { color: var(--inquiry-muted); font-size: 11px; letter-spacing: .08em; text-transform: uppercase; }
.summary-product h3 { margin: 9px 0 0; font-size: 17px; font-weight: 700; line-height: 1.4; overflow-wrap: anywhere; }
.summary-weight { margin-top: 10px; color: var(--inquiry-muted); font-size: 12px; }
.summary-details { display: grid; gap: 13px; padding: 24px 0; margin: 20px 0 0; border-top: 1px solid #ead7d8; }
.summary-details > div { display: flex; flex-wrap: wrap; gap: 5px 10px; justify-content: space-between; font-size: 12px; }
.summary-details dt { color: var(--inquiry-muted); }
.summary-details dd { font-weight: 650; margin: 0; }
.summary-total { padding-top: 20px; border-top: 1px solid #ead7d8; }
.summary-total > span { font-size: 12px; color: var(--inquiry-muted); }
.summary-total strong { display: block; margin-top: 7px; font-size: 23px; line-height: 1.25; letter-spacing: -.035em; color: var(--inquiry-brand); overflow-wrap: anywhere; }
.summary-total p { margin-top: 10px; font-size: 11px; line-height: 1.6; color: var(--inquiry-muted); }
.summary-process { display: flex; align-items: flex-start; gap: 10px; margin-top: 35px; color: #8b555c; }
.summary-process p { margin: 0; max-width: 155px; font-size: 11px; line-height: 1.7; }
.inquiry-form { padding: 30px 32px 28px; min-width: 0; }
.inquiry-fields { display: grid; gap: 19px; min-width: 0; padding: 0; border: 0; margin: 0; }
.inquiry-fields:disabled { opacity: .65; }
.form-section-title { display: flex; align-items: center; gap: 9px; margin: 2px 0 0; }
.form-section-title > span { width: 25px; height: 25px; display: grid; place-items: center; border-radius: 50%; background: #fae8e8; color: #800000; font-size: 10px; font-weight: 700; }
.form-section-title h3 { margin: 0; font-size: 15px; font-weight: 700; }
.field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 17px 14px; }
.inquiry-field { display: flex; min-width: 0; flex-direction: column; gap: 7px; }
.inquiry-field > label { font-size: 12px; font-weight: 650; line-height: 1.5; }
.inquiry-field label > span { color: #800000; }
.inquiry-field input, .inquiry-field textarea { width: 100%; min-width: 0; padding: 12px 13px; border: 1px solid #e6d5d8; border-radius: 10px; background: #fff; color: var(--inquiry-ink); font-family: inherit; font-size: 14px; line-height: 1.45; outline: 0; box-shadow: none; transition: border-color .15s, box-shadow .15s; }
.inquiry-field input { min-height: 46px; }
.inquiry-field textarea { resize: vertical; min-height: 86px; max-height: 240px; }
.inquiry-field input::placeholder, .inquiry-field textarea::placeholder { color: #927f84; font-size: 12px; opacity: 1; }
.inquiry-field input:focus, .inquiry-field textarea:focus { border-color: var(--inquiry-brand); box-shadow: 0 0 0 3px #80000014; }
.inquiry-field input[aria-invalid="true"], .inquiry-field textarea[aria-invalid="true"] { border-color: #b84535; }
.quantity-input { display: flex; align-items: center; min-height: 46px; overflow: hidden; border: 1px solid #e6d5d8; border-radius: 10px; background: #fff; }
.quantity-input:focus-within { border-color: var(--inquiry-brand); box-shadow: 0 0 0 3px #80000014; }
.quantity-input input { padding-inline: 0; text-align: center; -moz-appearance: textfield; border: 0; border-radius: 0; }
.quantity-input input:focus { box-shadow: none; }
.quantity-input input::-webkit-inner-spin-button, .quantity-input input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
.quantity-input button { flex: 0 0 42px; height: 44px; color: var(--inquiry-brand); background: #fff; font-size: 22px; }
.quantity-input button:hover { background: #fae8e8; }
.quantity-input button:disabled { color: #cbb7bb; }
.price-input { position: relative; }
.price-input input { padding-right: 30px; }
.price-input > span { position: absolute; right: 12px; top: 12px; color: var(--inquiry-muted); font-size: 14px; pointer-events: none; }
.field-hint, .field-error { display: block; font-size: 11px; line-height: 1.5; }
.field-hint { color: var(--inquiry-muted); }
.field-error { color: #aa3c2c; }
.required-note { margin: -10px 0 -4px; color: var(--inquiry-muted); font-size: 11px; }
.scenario-group { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 9px; }
.scenario-option { display: flex; position: relative; align-items: flex-start; gap: 8px; padding: 12px 10px; border: 1px solid #e6d5d8; border-radius: 11px; color: #7e646a; cursor: pointer; background: #fff; }
.scenario-option.is-selected { color: var(--inquiry-brand); border-color: var(--inquiry-brand); background: #fff0ef; box-shadow: inset 0 0 0 .5px var(--inquiry-brand); }
.scenario-option strong { display: block; font-size: 11px; line-height: 1.45; font-weight: 750; }
.scenario-option small { display: block; margin-top: 3px; color: var(--inquiry-muted); font-size: 10px; line-height: 1.4; }
.scenario-option input, .contact-options input { position: absolute; opacity: 0; width: 1px; height: 1px; }
.scenario-option:focus-within, .contact-options label:focus-within { outline: 2px solid var(--inquiry-brand); outline-offset: 3px; }
.scenario-advice { padding: 11px 13px; margin: -7px 0 0; background: #fcf0eb; border-left: 2px solid #b76568; border-radius: 0 8px 8px 0; font-size: 12px; line-height: 1.6; color: #865057; }
.contact-preference { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.contact-preference > span { font-size: 12px; }
.contact-options { display: flex; gap: 7px; }
.contact-options label { position: relative; display: flex; align-items: center; gap: 6px; padding: 8px 10px; border: 1px solid #e6d5d8; border-radius: 8px; font-size: 11px; cursor: pointer; }
.contact-options label.is-selected { color: var(--inquiry-brand); border-color: var(--inquiry-brand); background: #fff0ef; }
.inquiry-consent { display: flex; gap: 10px; align-items: flex-start; color: var(--inquiry-muted); font-size: 11px; line-height: 1.6; cursor: pointer; }
.inquiry-consent input { flex-shrink: 0; width: 17px; height: 17px; margin-top: 2px; border: 1px solid #b7979e; border-radius: 4px; color: var(--inquiry-brand); accent-color: var(--inquiry-brand); }
.inquiry-consent a { color: var(--inquiry-brand); text-decoration: underline; text-underline-offset: 2px; }
.inquiry-button { display: inline-flex; justify-content: center; align-items: center; gap: 10px; min-height: 48px; padding: 12px 20px; border-radius: 11px; text-decoration: none; font-size: 13px; line-height: 1.45; font-weight: 700; transition: background .15s; }
.inquiry-button--primary { color: #fffaf8; background: var(--inquiry-brand); }
.inquiry-button--primary:hover { background: #a00000; }
.inquiry-button--primary:disabled { opacity: .7; cursor: wait; }
.inquiry-button--plain { color: var(--inquiry-brand); background: transparent; }
.inquiry-button--plain:hover { background: #fae8e8; }
.inquiry-submit { width: 100%; margin-top: 22px; }
.submit-note { margin: 10px 0 0; text-align: center; color: var(--inquiry-muted); font-size: 10px; line-height: 1.6; }
.inquiry-error { display: flex; align-items: flex-start; gap: 9px; padding: 13px; margin-bottom: 20px; border: 1px solid #ebc9bc; border-radius: 10px; color: #913d2d; background: #fff4ee; font-size: 12px; line-height: 1.6; }
.inquiry-error p { margin: 0; }
.inquiry-error ul { margin: 8px 0 0; padding-left: 18px; }
.inquiry-error .v-icon { flex-shrink: 0; margin-top: 1px; }
.inquiry-trap { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
.inquiry-success { display: flex; align-items: center; flex-direction: column; padding: 55px 34px 35px; text-align: center; }
.success-mark { display: grid; place-items: center; width: 78px; height: 78px; margin-bottom: 25px; border-radius: 50%; background: #f8e1e2; color: var(--inquiry-brand); box-shadow: 0 0 0 10px #fcf0ef; }
.success-description { max-width: 530px; margin: 18px 0 0; font-size: 14px; line-height: 1.7; color: var(--inquiry-muted); overflow-wrap: anywhere; }
.success-reference { display: grid; gap: 6px; min-width: min(100%, 290px); padding: 20px 24px; margin-top: 25px; border: 1px solid #ead7d8; border-radius: 13px; background: #fbefed; }
.success-reference span, .success-reference small { color: var(--inquiry-muted); font-size: 11px; }
.success-reference strong { font-size: 23px; color: var(--inquiry-brand); font-variant-numeric: tabular-nums; }
.success-note { max-width: 480px; margin: 20px 0; font-size: 12px; line-height: 1.7; color: var(--inquiry-muted); }
.success-max-hint { max-width: 380px; margin: 12px 0 3px; color: var(--inquiry-muted); font-size: 11px; line-height: 1.6; }
.good-inquiry button:focus-visible, .good-inquiry a:focus-visible { outline: 2px solid #b76568; outline-offset: 3px; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
@media (max-width: 767px) {
    :global(.good-inquiry-overlay) { width: 100% !important; max-width: 100% !important; height: 100% !important; max-height: 100% !important; margin: 0 !important; }
    .good-inquiry { height: 100dvh; max-height: 100dvh; border-radius: 0; overscroll-behavior-y: contain; scroll-padding-top: 150px; }
    .inquiry-header { position: sticky; top: 0; z-index: 3; padding: calc(17px + env(safe-area-inset-top)) 68px 15px 20px; background: #fffaf8; box-shadow: 0 3px 12px #80000008; }
    .inquiry-close { top: calc(14px + env(safe-area-inset-top)); right: 14px; width: 44px; height: 44px; }
    .inquiry-header h2 { font-size: 25px; line-height: 1.15; letter-spacing: -.03em; }
    .inquiry-header > p:last-child { margin-top: 8px; font-size: 12px; line-height: 1.5; }
    .inquiry-eyebrow { display: none; }
    .inquiry-layout { display: block; }
    .inquiry-summary { display: none; }
    .inquiry-mobile-summary { display: block; border-bottom: 1px solid #eadcde; background: #fbefed; }
    .inquiry-mobile-summary > summary { display: flex; align-items: center; gap: 11px; min-height: 80px; padding: 14px 20px; cursor: pointer; list-style: none; }
    .inquiry-mobile-summary > summary::-webkit-details-marker { display: none; }
    .inquiry-mobile-summary > summary:focus-visible { outline: 2px solid var(--inquiry-brand); outline-offset: -4px; }
    .inquiry-mobile-summary > summary img { flex: 0 0 46px; width: 46px; height: 46px; border-radius: 10px; object-fit: contain; background: white; }
    .mobile-summary-product { min-width: 0; flex: 1; }
    .mobile-summary-product strong { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; font-size: 13px; line-height: 1.45; font-weight: 700; }
    .mobile-summary-product small { display: block; margin-top: 4px; color: var(--inquiry-muted); font-size: 11px; }
    .mobile-summary-chevron { flex-shrink: 0; color: var(--inquiry-brand); transition: transform .15s; }
    .inquiry-mobile-summary[open] .mobile-summary-chevron { transform: rotate(180deg); }
    .mobile-summary-content { padding: 0 20px 17px; }
    .summary-details { gap: 10px; padding: 15px 0; margin: 0; }
    .summary-total { display: flex; align-items: baseline; justify-content: space-between; flex-wrap: wrap; gap: 4px 10px; padding-top: 13px; }
    .summary-total > span { font-size: 11px; }
    .summary-total strong { margin: 0; font-size: 20px; }
    .summary-total p { flex-basis: 100%; margin: 3px 0 0; font-size: 11px; line-height: 1.5; }
    .inquiry-form { padding: 23px 20px 0; }
    .inquiry-fields { gap: 20px; }
    .form-section-title { gap: 10px; }
    .form-section-title > span { width: 29px; height: 29px; }
    .form-section-title h3 { font-size: 16px; }
    .field-grid { grid-template-columns: 1fr; gap: 19px; }
    .inquiry-field > label { font-size: 13px; }
    .inquiry-field input, .inquiry-field textarea { font-size: 16px; scroll-margin-top: 150px; }
    .inquiry-field input { min-height: 50px; }
    .inquiry-field input::placeholder, .inquiry-field textarea::placeholder { font-size: 13px; }
    .quantity-input { min-height: 50px; }
    .quantity-input button { flex-basis: 52px; height: 50px; }
    .quantity-input input { min-height: 50px; }
    .price-input > span { top: 14px; }
    .field-hint, .field-error { font-size: 12px; }
    .scenario-option { gap: 8px; align-items: center; padding: 13px 10px; min-height: 68px; }
    .scenario-option strong { font-size: 12px; }
    .scenario-option small { font-size: 10px; }
    .scenario-advice { margin-top: -8px; font-size: 12px; }
    .contact-preference { display: grid; gap: 12px; }
    .contact-preference > span { font-size: 13px; }
    .contact-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .contact-options label { justify-content: center; min-height: 46px; font-size: 13px; }
    .inquiry-consent { gap: 12px; font-size: 12px; }
    .inquiry-consent input { width: 21px; height: 21px; }
    .inquiry-submit-area { margin: 24px -20px 0; padding: 18px 20px calc(18px + env(safe-area-inset-bottom)); border-top: 1px solid #eadcde; background: #fbefed; }
    .mobile-submit-total { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .mobile-submit-total > span { color: var(--inquiry-muted); font-size: 12px; line-height: 1.45; }
    .mobile-submit-total small { display: block; margin-top: 3px; font-size: 11px; }
    .mobile-submit-total strong { color: var(--inquiry-brand); text-align: right; font-size: 21px; line-height: 1.3; overflow-wrap: anywhere; }
    .inquiry-submit { min-height: 54px; margin-top: 15px; font-size: 14px; }
    .submit-note { margin-top: 11px; font-size: 11px; }
    .inquiry-success { min-height: 100%; justify-content: center; padding: calc(78px + env(safe-area-inset-top)) 22px calc(30px + env(safe-area-inset-bottom)); }
    .inquiry-success .inquiry-eyebrow { display: block; }
    .inquiry-success h2 { font-size: 28px; }
}
@media (max-width: 360px) {
    .inquiry-header { padding-left: 16px; padding-right: 65px; }
    .inquiry-header h2 { font-size: 23px; }
    .inquiry-form { padding-inline: 16px; }
    .inquiry-mobile-summary > summary { padding-inline: 16px; }
    .mobile-summary-content { padding-inline: 16px; }
    .inquiry-submit-area { margin-inline: -16px; padding-inline: 16px; }
    .scenario-option { padding-inline: 8px; }
    .scenario-option > .v-icon { display: none; }
    .mobile-submit-total strong { font-size: 19px; }
}
</style>
