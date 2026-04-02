export function usePhoneFormatter() {
    const formatPhone = (value) => {
        if (!value) return ''

        const raw = String(value).replace(/\D+/g, '')

        if (raw.length === 11) {
            return `+${raw[0]} ${raw.slice(1, 4)} ${raw.slice(4, 7)}-${raw.slice(7, 9)}-${raw.slice(9, 11)}`
        }

        return value
    }

    const formatPhones = (phones = []) => {
        return phones
            .map(phone => formatPhone(phone.number ?? phone))
            .join(', ')
    }

    return {
        formatPhone,
        formatPhones,
    }
}
