<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import UnitCreateRelationField from './UnitCreateRelationField.vue'
import ApartmentSelector from '@/Components/Geography/Buildings/ApartmentSelector.vue'
import { emptyUnitForm, unitCreationPayload, unitCreationErrorSection, unitRelationIds } from '@/utils/unitCreation.js'

const props = defineProps({ modelValue: Boolean })
const emit = defineEmits(['update:modelValue', 'created'])
const form = reactive(emptyUnitForm())
const activeSection = ref('profile')
const saving = ref(false)
const error = ref('')
const errors = ref({})
const confirmDiscard = ref(false)
const openAfterCreate = ref(false)
const nameInput = ref(null)
const contactInputs = ref([])
const sections = [
    { id: 'profile', title: 'Профиль', detail: 'Название и связи', icon: 'mdi-domain' },
    { id: 'contacts', title: 'Контакты', detail: 'Телефоны, почта, сайты', icon: 'mdi-card-account-phone-outline' },
    { id: 'geography', title: 'География', detail: 'Города и помещения', icon: 'mdi-map-marker-outline' },
    { id: 'classification', title: 'Классификация', detail: 'Деятельность и метки', icon: 'mdi-tag-multiple-outline' },
]
const contacts = [
    { key: 'telephones', label: 'Телефоны', hint: 'Выберите номер или введите новый, например +7 921 123-45-67.' },
    { key: 'emails', label: 'Электронная почта', hint: 'Выберите адрес или введите новый, например mail@company.ru.' },
    { key: 'uris', label: 'Сайты и ссылки', hint: 'Выберите ссылку или введите новую, например company.ru.' },
]
const dirty = computed(() => JSON.stringify(form) !== JSON.stringify(emptyUnitForm()))
const relationCount = computed(() => ['entities', 'emails', 'telephones', 'uris', 'cities', 'buildings', 'fields', 'labels', 'industries', 'classifications']
    .reduce((sum, key) => sum + form[key].length, 0))
const sectionCounts = computed(() => ({
    profile: form.entities.length,
    contacts: form.emails.length + form.telephones.length + form.uris.length,
    geography: form.cities.length + form.buildings.length,
    classification: form.fields.length + form.labels.length + form.industries.length + form.classifications.length,
}))
const sectionErrors = computed(() => new Set(Object.keys(errors.value).map(unitCreationErrorSection)))
const activeTitle = computed(() => sections.find(section => section.id === activeSection.value)?.title)

function fieldErrors(key) {
    return Object.entries(errors.value)
        .filter(([field]) => field === key || field.startsWith(`${key}.`) || field === `new_${key}` || field.startsWith(`new_${key}.`))
        .flatMap(([, messages]) => messages)
}

function close() {
    if (saving.value) return
    contactInputs.value.forEach(input => input?.flushPending())
    if (dirty.value) confirmDiscard.value = true
    else emit('update:modelValue', false)
}

function discard() {
    confirmDiscard.value = false
    emit('update:modelValue', false)
}

async function save() {
    if (saving.value) return
    saving.value = true
    contactInputs.value.forEach(input => input?.flushPending())
    await nextTick()
    error.value = ''
    errors.value = {}
    if (!form.name.trim() || form.name.trim().length > 255) {
        errors.value = { name: ['Укажите название Unit — от 1 до 255 символов.'] }
        activeSection.value = 'profile'
        await nextTick()
        nameInput.value?.focus()
        saving.value = false
        return
    }
    try {
        const { data } = await axios.post('/api/units', unitCreationPayload(form))
        const unit = data.data || data
        emit('created', unit)
        emit('update:modelValue', false)
        if (openAfterCreate.value) router.visit(`/Ameise/unit/${unit.id}`)
    } catch (exception) {
        errors.value = exception.response?.data?.errors || {}
        const firstField = Object.keys(errors.value)[0]
        if (firstField) activeSection.value = unitCreationErrorSection(firstField)
        error.value = firstField ? 'Проверьте выделенные поля. Введённые данные сохранены в форме.'
            : exception.response?.data?.message || 'Не удалось создать Unit. Попробуйте ещё раз.'
    } finally {
        saving.value = false
    }
}

watch(() => form.industries, () => {
    if (!unitRelationIds(form.industries).includes(Number(form.primary_industry_id))) form.primary_industry_id = null
}, { deep: true })
watch(() => props.modelValue, async open => {
    if (!open) return
    Object.assign(form, emptyUnitForm())
    activeSection.value = 'profile'
    errors.value = {}
    error.value = ''
    confirmDiscard.value = false
    await nextTick()
    nameInput.value?.focus()
})
</script>

<template>
    <v-dialog :model-value="modelValue" max-width="1080" :persistent="saving || dirty" class="unit-create-dialog" aria-labelledby="unit-create-title" @update:model-value="value => { if (!value) close() }">
        <v-card class="unit-create" theme="light" rounded="xl">
            <header class="unit-create__header">
                <span class="unit-create__avatar"><v-icon icon="mdi-domain-plus" size="25" /></span>
                <div><p class="unit-create__eyebrow">КОНТРАГЕНТЫ / НОВАЯ ЗАПИСЬ</p><h2 id="unit-create-title">Создать Unit</h2></div>
                <v-btn icon="mdi-close" variant="text" size="small" aria-label="Закрыть форму" :disabled="saving" @click="close" />
            </header>
            <form class="unit-create__form" @submit.prevent="save">
                <div class="unit-create__workspace">
                    <aside class="unit-create__sidebar">
                        <nav aria-label="Разделы формы Unit">
                            <button v-for="section in sections" :key="section.id" type="button" :class="{ 'is-active': activeSection === section.id }" :aria-current="activeSection === section.id ? 'step' : undefined" @click="activeSection = section.id">
                                <v-icon :icon="section.icon" size="19" />
                                <span><strong>{{ section.title }}</strong><small>{{ section.detail }}</small></span>
                                <span v-if="sectionErrors.has(section.id)" class="unit-create__error-dot" aria-label="Есть ошибки">!</span>
                                <span v-else-if="sectionCounts[section.id]" class="unit-create__count">{{ sectionCounts[section.id] }}</span>
                            </button>
                        </nav>
                        <div class="unit-create__note"><v-icon icon="mdi-information-outline" size="17" /><p>Для создания достаточно названия.<br>Остальное можно дополнить в карточке Unit.</p></div>
                    </aside>
                    <main class="unit-create__content">
                        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4" role="alert">{{ error }}</v-alert>
                        <section v-show="activeSection === 'profile'" aria-label="Профиль Unit">
                            <div class="unit-create__section-heading"><span>01</span><div><h3>Основная информация</h3><p>Компания, бренд или направление, с которым вы работаете.</p></div></div>
                            <v-text-field ref="nameInput" v-model="form.name" label="Название Unit *" placeholder="Например, Северная ягода" variant="outlined" density="comfortable" :disabled="saving" :error-messages="fieldErrors('name')" maxlength="255" hide-details="auto" autofocus />
                            <p class="unit-create__label">Роль в работе <span>Можно выбрать обе</span></p>
                            <div class="unit-create__roles">
                                <button type="button" :aria-pressed="form.is_customer" :class="{ selected: form.is_customer }" :disabled="saving" @click="form.is_customer = !form.is_customer"><v-icon icon="mdi-cart-outline" size="21" /><span><strong>Покупатель</strong><small>Покупает у нас</small></span><v-icon :icon="form.is_customer ? 'mdi-checkbox-marked-circle' : 'mdi-checkbox-blank-circle-outline'" size="19" /></button>
                                <button type="button" :aria-pressed="form.is_supplier" :class="{ selected: form.is_supplier }" :disabled="saving" @click="form.is_supplier = !form.is_supplier"><v-icon icon="mdi-truck-outline" size="21" /><span><strong>Поставщик</strong><small>Поставляет нам</small></span><v-icon :icon="form.is_supplier ? 'mdi-checkbox-marked-circle' : 'mdi-checkbox-blank-circle-outline'" size="19" /></button>
                            </div>
                            <div class="unit-create__divider" />
                            <h4>Связанные Entity</h4>
                            <p class="unit-create__description">Юридические лица, ИП и контактные лица. Можно связать несколько записей с одним Unit.</p>
                            <UnitCreateRelationField v-model="form.entities" kind="entities" label="Выберите Entity" :disabled="saving" :error-messages="fieldErrors('entities')" hint="Поиск по названию или ИНН. Существующие связи Entity сохранятся." />
                        </section>
                        <section v-show="activeSection === 'contacts'" aria-label="Контакты Unit">
                            <div class="unit-create__section-heading"><span>02</span><div><h3>Всегда на связи</h3><p>Общие контакты Unit. Контакты связанных Entity остаются в их карточках.</p></div></div>
                            <div class="unit-create__fields">
                                <UnitCreateRelationField v-for="contact in contacts" :key="contact.key" ref="contactInputs" v-model="form[contact.key]" :kind="contact.key" :label="contact.label" :hint="contact.hint" :disabled="saving" :error-messages="fieldErrors(contact.key)" />
                            </div>
                            <div class="unit-create__callout"><v-icon icon="mdi-plus-circle-outline" size="19" /><span>Новый контакт добавляется клавишей Enter. Все выбранные контакты сохранятся вместе с Unit.</span></div>
                        </section>
                        <section v-show="activeSection === 'geography'" aria-label="География Unit">
                            <div class="unit-create__section-heading"><span>03</span><div><h3>Где работает Unit</h3><p>Города присутствия, офисы, склады и производство.</p></div></div>
                            <div class="unit-create__fields">
                                <UnitCreateRelationField v-model="form.cities" kind="cities" label="Города присутствия" :disabled="saving" :error-messages="fieldErrors('cities')" />
                                <UnitCreateRelationField v-model="form.buildings" kind="buildings" label="Здания и адреса" :disabled="saving" :error-messages="fieldErrors('buildings')" hint="После выбора здания можно уточнить офис или помещение." />
                            </div>
                            <div v-if="form.buildings.length" class="unit-create__premises">
                                <h4>Помещения в выбранных зданиях</h4>
                                <ApartmentSelector v-for="building in form.buildings" :key="building.id" v-model="form.building_apartments[building.id]" :building="building" :disabled="saving" :error-messages="fieldErrors(`building_apartments.${building.id}`)" />
                                <p>Это адреса Unit. Привязки помещений к Entity управляются отдельно.</p>
                            </div>
                        </section>
                        <section v-show="activeSection === 'classification'" aria-label="Классификация Unit">
                            <div class="unit-create__section-heading"><span>04</span><div><h3>Деятельность и сегменты</h3><p>Помогают находить Unit и формировать точные подборки.</p></div></div>
                            <div class="unit-create__fields">
                                <UnitCreateRelationField v-model="form.fields" kind="fields" label="Сферы деятельности" :disabled="saving" :error-messages="fieldErrors('fields')" />
                                <div class="unit-create__pair">
                                    <UnitCreateRelationField v-model="form.classifications" kind="classifications" label="Классификация" :disabled="saving" :error-messages="fieldErrors('classifications')" />
                                    <UnitCreateRelationField v-model="form.labels" kind="labels" label="Метки" :disabled="saving" :error-messages="fieldErrors('labels')" />
                                </div>
                                <UnitCreateRelationField v-model="form.industries" kind="industries" label="Коды ОКВЭД" :disabled="saving" :error-messages="fieldErrors('industries')" />
                                <v-select v-if="form.industries.length" v-model="form.primary_industry_id" :items="form.industries" :item-title="item => `${item.code} · ${item.title}`" item-value="id" label="Основной ОКВЭД" variant="outlined" density="compact" clearable hide-details="auto" :disabled="saving" :error-messages="fieldErrors('primary_industry_id')" />
                            </div>
                        </section>
                    </main>
                </div>
                <footer class="unit-create__footer">
                    <div class="unit-create__footer-summary"><span>{{ activeTitle }}</span><small>Выбрано связей: {{ relationCount }}</small></div>
                    <label class="unit-create__open"><input v-model="openAfterCreate" type="checkbox" :disabled="saving"> Открыть карточку</label>
                    <v-btn variant="text" :disabled="saving" @click="close">Отмена</v-btn>
                    <v-btn type="submit" color="#6551a4" variant="flat" prepend-icon="mdi-plus" :loading="saving" :disabled="saving">Создать Unit</v-btn>
                </footer>
            </form>
        </v-card>
        <v-dialog v-model="confirmDiscard" max-width="420">
            <v-card theme="light" rounded="lg" title="Закрыть без сохранения?" text="Введённые данные Unit будут сброшены.">
                <v-card-actions><v-spacer /><v-btn @click="confirmDiscard = false">Продолжить</v-btn><v-btn color="error" @click="discard">Закрыть</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </v-dialog>
</template>

<style scoped>
.unit-create { --ink: #292637; --muted: #7d788b; --line: #e9e6ef; display: flex; flex-direction: column; color: var(--ink); height: min(710px, calc(100dvh - 48px)); max-height: calc(100dvh - 48px); border: 1px solid #ffffffa8; box-shadow: 0 24px 100px #15102033 !important; }
.unit-create__header { display: flex; align-items: center; gap: 13px; padding: 22px 26px; border-bottom: 1px solid var(--line); background: linear-gradient(110deg, #faf9fd, #fff); flex-shrink: 0; }
.unit-create__avatar { display: grid; place-items: center; width: 46px; height: 46px; border: 1px solid #e4dff1; border-radius: 14px; background: #efeaf9; color: #6551a4; }
.unit-create__header > .v-btn { margin-left: auto; color: var(--muted); }
.unit-create__eyebrow { margin: 0 0 3px; color: #91879f; font-size: 9px; font-weight: 650; letter-spacing: .12em; }
.unit-create h2 { margin: 0; font-size: 23px; font-weight: 650; line-height: 1.2; letter-spacing: -.03em; }
.unit-create__form { display: flex; flex-direction: column; flex: 1; min-height: 0; }
.unit-create__workspace { display: grid; grid-template-columns: 222px minmax(0, 1fr); min-height: 0; flex: 1; }
.unit-create__sidebar { display: flex; flex-direction: column; padding: 20px 12px; background: #f8f7fa; border-right: 1px solid var(--line); overflow-y: auto; }
.unit-create__sidebar nav { display: grid; gap: 5px; }
.unit-create__sidebar nav button { display: flex; align-items: center; gap: 10px; width: 100%; padding: 12px 10px; border: 1px solid transparent; border-radius: 10px; text-align: left; color: #81798e; transition: background .15s; }
.unit-create__sidebar nav button:hover { background: #eeebf4; }
.unit-create__sidebar nav button.is-active { color: #6551a4; background: #fff; border-color: #e8e2f3; box-shadow: 0 2px 5px #28203a05; }
.unit-create__sidebar strong { display: block; color: #514a61; font-size: 12px; font-weight: 650; }
.unit-create__sidebar small { display: block; font-size: 10px; color: #928a9e; margin-top: 3px; }
.unit-create__count, .unit-create__error-dot { margin-left: auto; padding: 1px 5px; border-radius: 5px; background: #ece7f6; font-size: 10px; font-weight: 650; }
.unit-create__error-dot { color: #b3354c; background: #fde8ec; }
.unit-create__note { display: flex; gap: 8px; margin: auto 8px 0; padding-top: 30px; color: #938b9f; }
.unit-create__note p { margin: 0; font-size: 10px; line-height: 1.7; }
.unit-create__content { min-width: 0; overflow-y: auto; overscroll-behavior: contain; padding: 28px 32px; }
.unit-create__section-heading { display: flex; gap: 12px; margin-bottom: 24px; align-items: flex-start; }
.unit-create__section-heading > span { padding-top: 4px; color: #aba1bb; font-size: 11px; font-weight: 650; }
.unit-create h3 { font-size: 18px; letter-spacing: -.02em; font-weight: 650; }
.unit-create__section-heading p, .unit-create__description { color: var(--muted); font-size: 12px; line-height: 1.65; margin: 5px 0 0; }
.unit-create__label { font-size: 11px; font-weight: 650; margin: 23px 0 9px; }
.unit-create__label span { font-size: 10px; color: #96909f; font-weight: 400; margin-left: 8px; }
.unit-create__roles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.unit-create__roles button { display: flex; align-items: center; text-align: left; gap: 10px; padding: 13px; border: 1px solid #e4e0ea; border-radius: 10px; color: #938b9f; background: #fff; }
.unit-create__roles button.selected { border-color: #b4a2d8; color: #6551a4; background: #f7f4fc; }
.unit-create__roles button > .v-icon:last-child { margin-left: auto; }
.unit-create__roles strong { display: block; color: #4b425d; font-size: 12px; font-weight: 650; }
.unit-create__roles small { display: block; font-size: 10px; margin-top: 2px; }
.unit-create__divider { height: 1px; background: var(--line); margin: 25px 0 20px; }
.unit-create h4 { font-size: 13px; font-weight: 650; margin: 0 0 6px; }
.unit-create__description { margin-bottom: 14px; }
.unit-create__fields { display: grid; gap: 22px; }
.unit-create__pair { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.unit-create__callout { display: flex; align-items: flex-start; gap: 10px; padding: 13px; margin-top: 24px; border: 1px dashed #d9d0e8; border-radius: 9px; background: #faf8fd; color: #8b7c9d; font-size: 11px; line-height: 1.6; }
.unit-create__premises { display: grid; gap: 12px; margin-top: 22px; padding: 16px; background: #faf9fc; border: 1px solid var(--line); border-radius: 10px; }
.unit-create__premises > p { color: var(--muted); font-size: 10px; margin: 0; }
.unit-create__footer { display: flex; flex-shrink: 0; align-items: center; gap: 10px; padding: 16px 24px; border-top: 1px solid var(--line); background: #fff; }
.unit-create__footer-summary { display: grid; gap: 2px; margin-right: auto; font-size: 11px; font-weight: 600; }
.unit-create__footer-summary small { font-size: 10px; font-weight: 400; color: var(--muted); }
.unit-create__open { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--muted); cursor: pointer; }
.unit-create__open input { width: 14px; height: 14px; border: 1px solid #d6cedf; border-radius: 4px; color: #6551a4; accent-color: #6551a4; }
.unit-create :deep(.v-btn) { text-transform: none; letter-spacing: 0; font-size: 12px; border-radius: 8px; }
.unit-create :deep(.v-field) { border-radius: 9px; font-size: 13px; }
.unit-create :deep(.v-field__outline) { color: #bdb4ca; }
.unit-create :deep(.v-label) { font-size: 13px; color: #6d617c; opacity: 1; }
.unit-create button:focus-visible { outline: 2px solid #8871bb; outline-offset: 3px; }
@media (max-width: 760px) {
    .unit-create { height: calc(100dvh - 32px); max-height: calc(100dvh - 32px); }
    .unit-create__header { padding: 15px 18px; }
    .unit-create__workspace { display: flex; flex-direction: column; }
    .unit-create__sidebar { flex: 0 0 auto; padding: 8px; border-right: 0; border-bottom: 1px solid var(--line); overflow: visible; }
    .unit-create__sidebar nav { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 3px; }
    .unit-create__sidebar nav button { flex-direction: column; gap: 4px; padding: 8px 2px; text-align: center; position: relative; }
    .unit-create__sidebar strong { font-size: 9px; }
    .unit-create__sidebar small, .unit-create__note { display: none; }
    .unit-create__count, .unit-create__error-dot { position: absolute; top: 3px; right: 3px; font-size: 8px; }
    .unit-create__content { flex: 1; padding: 20px 18px; }
    .unit-create__section-heading { margin-bottom: 18px; }
    .unit-create__footer { flex-wrap: wrap; padding: 12px 16px; gap: 7px; }
    .unit-create__footer-summary { display: none; }
    .unit-create__open { flex: 1 0 100%; margin-bottom: 2px; }
    .unit-create__footer > .v-btn:first-of-type { margin-left: auto; }
    .unit-create__pair { grid-template-columns: 1fr; }
    .unit-create__roles { gap: 8px; }
    .unit-create__roles button { padding: 10px; gap: 6px; }
    .unit-create__roles button > .v-icon:first-child { display: none; }
}
</style>
