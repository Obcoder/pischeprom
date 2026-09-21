import test from 'node:test'
import assert from 'node:assert/strict'
import { calendarDay, deliveryDateQuery, formatDeliveryDate } from '../src/delivery-date.js'

test('delivery dates remain calendar days with no conversion from UTC midnight', () => {
    assert.equal(formatDeliveryDate('2026-09-21'), '21.09.2026')
    assert.equal(formatDeliveryDate(null), 'Не назначена')
    assert.equal(formatDeliveryDate('2026-09-21T00:00:00Z'), 'Не назначена')
    assert.equal(calendarDay(0, new Date(2026, 8, 21, 23, 59)), '2026-09-21')
})

test('tomorrow crosses month, year and leap-day boundaries correctly', () => {
    assert.equal(calendarDay(1, new Date(2026, 11, 31, 23, 59)), '2027-01-01')
    assert.equal(calendarDay(1, new Date(2028, 1, 28)), '2028-02-29')
    assert.equal(calendarDay(1, new Date(2028, 1, 29)), '2028-03-01')
})

test('unscheduled and exact delivery day filters cannot be sent together', () => {
    assert.deepEqual(deliveryDateQuery('2026-09-21'), { delivery_date: '2026-09-21' })
    assert.deepEqual(deliveryDateQuery('2026-09-21', true), { delivery_unscheduled: 1 })
    assert.deepEqual(deliveryDateQuery(''), {})
})
