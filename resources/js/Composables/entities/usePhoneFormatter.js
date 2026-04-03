export function usePhoneFormatter() {
    const normalizePhoneValue = (phone) => {
        if (!phone) return ''

        if (typeof phone === 'string') return phone

        return phone.number || phone.telephone || phone.phone || phone.value || ''
    }

    const formatPhone = (value) => {
        if (!value) return ''

        const raw = String(value).replace(/\D+/g, '')

        // 11 цифр: +7 900 123-45-67
        if (raw.length === 11) {
            return `+${raw[0]} ${raw.slice(1, 4)} ${raw.slice(4, 7)}-${raw.slice(7, 9)}-${raw.slice(9, 11)}`
        }

        // больше 11: код страны переменной длины
        if (raw.length > 11) {
            const countryCode = raw.slice(0, raw.length - 10)
            const local = raw.slice(-10)
            return `+${countryCode} ${local.slice(0, 3)} ${local.slice(3, 6)}-${local.slice(6, 8)}-${local.slice(8, 10)}`
        }

        // 10 цифр без кода страны
        if (raw.length === 10) {
            return `${raw.slice(0, 3)} ${raw.slice(3, 6)}-${raw.slice(6, 8)}-${raw.slice(8, 10)}`
        }

        return value
    }

    const formatPhones = (phones = []) => {
        return phones
            .map(phone => formatPhone(normalizePhoneValue(phone)))
            .filter(Boolean)
            .join(', ')
    }

    return {
        formatPhone,
        formatPhones,
    }
}
