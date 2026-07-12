<script setup>
import { computed, onMounted, ref } from 'vue'
import { useHead } from '@vueuse/head'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import {
    HOME_BEE_ANIMATION_KEY,
    readHomeBeeAnimationSetting,
    setHomeBeeAnimationEnabled,
} from '@/Composables/useHomeBeeAnimationSetting'

defineOptions({
    layout: VerwalterLayout,
})

const GROSSBUCH_TAB_KEY = 'ameise:grossbuch:tab'

const grossbuchTabs = [
    { value: 'units', title: 'Объекты', subtitle: 'Units / Entities' },
    { value: 'contacts', title: 'Контакты', subtitle: 'Телефоны / Uris / Emails / Письма' },
    { value: 'products', title: 'Products', subtitle: 'Categories / Products / Goods / Components' },
    { value: 'segments', title: 'Классификаторы', subtitle: 'Industries / Catalogs / Fields / Segments' },
    { value: 'geography', title: 'География', subtitle: 'Cities / Buildings / Regions / Countries' },
    { value: 'purchases', title: 'Закупки', subtitle: 'Purchases' },
    { value: 'sales', title: 'Продажи', subtitle: 'Sales' },
]

const grossbuchStartTab = ref('units')
const homeBeeAnimationEnabled = ref(true)
const grossbuchSaved = ref(false)
const homeSaved = ref(false)

const selectedTab = computed(() => {
    return grossbuchTabs.find((item) => item.value === grossbuchStartTab.value) || grossbuchTabs[0]
})

const homeBeeAnimationLabel = computed(() => {
    return homeBeeAnimationEnabled.value ? 'Включена' : 'Выключена'
})

function markSaved(flag) {
    flag.value = true
    window.setTimeout(() => {
        flag.value = false
    }, 1800)
}

function loadSettings() {
    if (typeof window === 'undefined') {
        return
    }

    const value = window.localStorage.getItem(GROSSBUCH_TAB_KEY)

    if (grossbuchTabs.some((item) => item.value === value)) {
        grossbuchStartTab.value = value
    }

    homeBeeAnimationEnabled.value = readHomeBeeAnimationSetting()
}

function saveGrossbuchSettings() {
    if (typeof window === 'undefined') {
        return
    }

    window.localStorage.setItem(GROSSBUCH_TAB_KEY, grossbuchStartTab.value)
    markSaved(grossbuchSaved)
}

function saveHomeSettings() {
    if (typeof window === 'undefined') {
        return
    }

    setHomeBeeAnimationEnabled(homeBeeAnimationEnabled.value)
    markSaved(homeSaved)
}

function resetGrossbuchStartTab() {
    grossbuchStartTab.value = 'units'
    saveGrossbuchSettings()
}

function resetHomeSettings() {
    homeBeeAnimationEnabled.value = true
    saveHomeSettings()
}

onMounted(loadSettings)

useHead({
    title: 'Настройки Ameise',
})
</script>

<template>
    <v-container fluid class="ameise-settings">
        <section class="settings-hero">
            <div>
                <div class="settings-hero__eyebrow">Ameise admin</div>
                <h1>Настройки</h1>
                <p>Локальные настройки интерфейса админки для текущего браузера.</p>
            </div>

            <Link :href="route('Ameise.großbuch')" class="settings-hero__link">
                <v-icon icon="mdi-arrow-left" size="16" />
                Grossbuch
            </Link>
        </section>

        <v-row dense>
            <v-col cols="12" lg="7">
                <div class="settings-cards-stack">
                    <v-card class="settings-card">
                        <v-card-title class="settings-card__title">
                            <div>
                                <span>Grossbuch</span>
                                <strong>Стартовая вкладка</strong>
                            </div>
                            <v-chip size="small" color="teal-darken-3" variant="flat">
                                localStorage
                            </v-chip>
                        </v-card-title>

                        <v-card-text>
                            <p class="settings-card__hint">
                                Эта вкладка будет открываться при загрузке <code>Ameise/grossbuch</code>.
                                По умолчанию используется <code>Объекты</code>.
                            </p>

                            <v-radio-group v-model="grossbuchStartTab" class="settings-tabs" hide-details>
                                <v-radio
                                    v-for="item in grossbuchTabs"
                                    :key="item.value"
                                    :value="item.value"
                                    color="teal-darken-3"
                                    class="settings-tab-option"
                                >
                                    <template #label>
                                        <div class="settings-tab-option__label">
                                            <strong>{{ item.title }}</strong>
                                            <span>{{ item.subtitle }}</span>
                                        </div>
                                    </template>
                                </v-radio>
                            </v-radio-group>
                        </v-card-text>

                        <v-card-actions class="settings-card__actions">
                            <v-btn color="teal-darken-3" variant="flat" @click="saveGrossbuchSettings">
                                Сохранить
                            </v-btn>
                            <v-btn variant="text" @click="resetGrossbuchStartTab">
                                Сбросить на Units
                            </v-btn>
                            <v-spacer />
                            <v-fade-transition>
                                <span v-if="grossbuchSaved" class="settings-saved">Сохранено</span>
                            </v-fade-transition>
                        </v-card-actions>
                    </v-card>

                    <v-card class="settings-card">
                        <v-card-title class="settings-card__title">
                            <div>
                                <span>Главная страница</span>
                                <strong>Анимация пчелы</strong>
                            </div>
                            <v-chip size="small" color="amber-darken-3" variant="flat">
                                localStorage
                            </v-chip>
                        </v-card-title>

                        <v-card-text>
                            <p class="settings-card__hint">
                                Управляет декоративной пчелой в верхнем баннере главной страницы.
                                По умолчанию анимация включена.
                            </p>

                            <div class="settings-switch-option">
                                <div class="settings-switch-option__copy">
                                    <strong>{{ homeBeeAnimationLabel }}</strong>
                                    <span>Показывать летающую пчелу на главной</span>
                                </div>

                                <v-switch
                                    v-model="homeBeeAnimationEnabled"
                                    color="amber-darken-3"
                                    hide-details
                                    inset
                                />
                            </div>
                        </v-card-text>

                        <v-card-actions class="settings-card__actions">
                            <v-btn color="teal-darken-3" variant="flat" @click="saveHomeSettings">
                                Сохранить
                            </v-btn>
                            <v-btn variant="text" @click="resetHomeSettings">
                                Сбросить
                            </v-btn>
                            <v-spacer />
                            <v-fade-transition>
                                <span v-if="homeSaved" class="settings-saved">Сохранено</span>
                            </v-fade-transition>
                        </v-card-actions>
                    </v-card>
                </div>
            </v-col>

            <v-col cols="12" lg="5">
                <v-card class="settings-preview">
                    <div class="settings-preview__eyebrow">Сейчас выбрано</div>
                    <h2>{{ selectedTab.title }}</h2>
                    <p>{{ selectedTab.subtitle }}</p>
                    <div class="settings-preview__rows">
                        <div class="settings-preview__code">
                            {{ GROSSBUCH_TAB_KEY }} = {{ selectedTab.value }}
                        </div>
                        <div class="settings-preview__code">
                            {{ HOME_BEE_ANIMATION_KEY }} = {{ homeBeeAnimationEnabled }}
                        </div>
                    </div>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
.ameise-settings {
    min-height: calc(100vh - 56px);
    padding: 18px;
    background:
        radial-gradient(circle at 12% 0%, rgba(20, 131, 116, 0.18), transparent 32%),
        linear-gradient(135deg, #f2fbf8 0%, #fffaf0 58%, #edf5f2 100%);
    color: #143b37;
}

.settings-hero {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 14px;
    padding: 16px;
    border: 1px solid rgba(20, 83, 76, 0.14);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.72);
    box-shadow: 0 18px 46px rgba(19, 66, 60, 0.08);
}

.settings-hero__eyebrow,
.settings-preview__eyebrow {
    color: rgba(20, 59, 55, 0.58);
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.settings-hero h1,
.settings-preview h2 {
    margin: 2px 0;
    font-family: Georgia, 'Times New Roman', serif;
    font-weight: 900;
    letter-spacing: -0.04em;
}

.settings-hero p,
.settings-preview p {
    margin: 0;
    color: rgba(20, 59, 55, 0.68);
}

.settings-hero__link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 10px;
    border: 1px solid rgba(20, 83, 76, 0.16);
    border-radius: 999px;
    background: #ffffff;
    color: #14534c;
    font-size: 12px;
    font-weight: 900;
    text-decoration: none;
}

.settings-card,
.settings-preview {
    border: 1px solid rgba(20, 83, 76, 0.14);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.82);
    box-shadow: 0 18px 46px rgba(19, 66, 60, 0.08);
}

.settings-cards-stack {
    display: grid;
    gap: 14px;
}

.settings-card__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.settings-card__title span {
    display: block;
    color: rgba(20, 59, 55, 0.54);
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.settings-card__title strong {
    display: block;
    color: #143b37;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 22px;
}

.settings-card__hint {
    margin: 0 0 12px;
    color: rgba(20, 59, 55, 0.68);
    font-size: 13px;
}

.settings-tabs {
    display: grid;
    gap: 6px;
}

.settings-tab-option {
    margin: 0;
    padding: 6px 8px;
    border: 1px solid rgba(20, 83, 76, 0.12);
    border-radius: 12px;
    background: rgba(244, 251, 248, 0.78);
}

.settings-tab-option__label {
    display: grid;
    gap: 1px;
    color: #143b37;
}

.settings-tab-option__label strong {
    font-size: 13px;
    font-weight: 900;
}

.settings-tab-option__label span {
    color: rgba(20, 59, 55, 0.58);
    font-size: 11px;
}

.settings-switch-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 12px 14px;
    border: 1px solid rgba(20, 83, 76, 0.12);
    border-radius: 12px;
    background: rgba(244, 251, 248, 0.78);
}

.settings-switch-option__copy {
    display: grid;
    gap: 2px;
    color: #143b37;
}

.settings-switch-option__copy strong {
    font-size: 14px;
    font-weight: 900;
}

.settings-switch-option__copy span {
    color: rgba(20, 59, 55, 0.58);
    font-size: 12px;
}

.settings-card__actions {
    padding: 10px 16px 16px;
}

.settings-saved {
    color: #0f766e;
    font-size: 12px;
    font-weight: 900;
}

.settings-preview {
    padding: 18px;
}

.settings-preview__code {
    margin-top: 14px;
    padding: 9px 10px;
    border-radius: 10px;
    background: #123d39;
    color: #b8fff0;
    font-family: 'Courier New', monospace;
    font-size: 12px;
}

.settings-preview__rows {
    display: grid;
    gap: 8px;
    margin-top: 14px;
}

.settings-preview__rows .settings-preview__code {
    margin-top: 0;
}

@media (max-width: 600px) {
    .settings-switch-option {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
