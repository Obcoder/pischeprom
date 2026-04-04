import { ref, watch } from 'vue'

export function useDebounce(source, delay = 400) {
    const debounced = ref(source.value)
    let timeoutId = null

    watch(
        source,
        (value) => {
            clearTimeout(timeoutId)

            timeoutId = setTimeout(() => {
                debounced.value = value
            }, delay)
        },
        { immediate: true }
    )

    return debounced
}
