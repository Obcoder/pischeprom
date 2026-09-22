export const mailDatePresets = [
    { value: 'today', title: 'Почта сегодня' },
    { value: 'yesterday', title: 'Вчера' },
    { value: 'day_before', title: 'Позавчера' },
    { value: 'week', title: 'Неделя', hint: 'Последние 7 дней, включая сегодня' },
]

export function mailDateRange(preset, timezone, now = new Date()) {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en', {
        timeZone: timezone,
        year: 'numeric', month: '2-digit', day: '2-digit',
    }).formatToParts(now).map(({ type, value }) => [type, value]))
    const day = new Date(`${parts.year}-${parts.month}-${parts.day}T00:00:00Z`)
    const shift = (offset) => {
        const date = new Date(day)
        date.setUTCDate(date.getUTCDate() + offset)
        return date.toISOString().slice(0, 10)
    }
    const offsets = { today: [0, 0], yesterday: [-1, -1], day_before: [-2, -2], week: [-6, 0] }
    const range = offsets[preset]

    return range ? { date_from: shift(range[0]), date_to: shift(range[1]) } : { date_from: null, date_to: null }
}

export function validMailDateRange(from, to) {
    const validDate = (value) => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return false
        const date = new Date(`${value}T00:00:00Z`)
        return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value
    }
    return validDate(from) && validDate(to) && from <= to
}

export function mailDateRangeLabel(from, to) {
    const format = (value) => value?.split('-').reverse().join('.') || '…'
    return from === to ? format(from) : `${format(from)} — ${format(to)}`
}
