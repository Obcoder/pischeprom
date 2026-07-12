import { computed, onMounted, onUnmounted, ref } from 'vue'

export const HOME_BEE_ANIMATION_KEY = 'pischeprom:home:hero-bee-animation'

const DEFAULT_HOME_BEE_ANIMATION_ENABLED = true

function isBrowser() {
    return typeof window !== 'undefined'
}

function parseStoredValue(value) {
    if (value === 'true') {
        return true
    }

    if (value === 'false') {
        return false
    }

    return DEFAULT_HOME_BEE_ANIMATION_ENABLED
}

export function readHomeBeeAnimationSetting() {
    if (!isBrowser()) {
        return DEFAULT_HOME_BEE_ANIMATION_ENABLED
    }

    return parseStoredValue(window.localStorage.getItem(HOME_BEE_ANIMATION_KEY))
}

const homeBeeAnimationEnabled = ref(readHomeBeeAnimationSetting())

export function setHomeBeeAnimationEnabled(value) {
    const enabled = Boolean(value)

    homeBeeAnimationEnabled.value = enabled

    if (isBrowser()) {
        window.localStorage.setItem(HOME_BEE_ANIMATION_KEY, enabled ? 'true' : 'false')
    }
}

function syncHomeBeeAnimationFromStorage() {
    homeBeeAnimationEnabled.value = readHomeBeeAnimationSetting()
}

export function useHomeBeeAnimationSetting() {
    const beeAnimationEnabled = computed({
        get: () => homeBeeAnimationEnabled.value,
        set: setHomeBeeAnimationEnabled,
    })

    function handleStorage(event) {
        if (event.key === HOME_BEE_ANIMATION_KEY) {
            syncHomeBeeAnimationFromStorage()
        }
    }

    onMounted(() => {
        syncHomeBeeAnimationFromStorage()

        if (isBrowser()) {
            window.addEventListener('storage', handleStorage)
        }
    })

    onUnmounted(() => {
        if (isBrowser()) {
            window.removeEventListener('storage', handleStorage)
        }
    })

    return {
        beeAnimationEnabled,
        homeBeeAnimationStorageKey: HOME_BEE_ANIMATION_KEY,
        setHomeBeeAnimationEnabled,
    }
}
