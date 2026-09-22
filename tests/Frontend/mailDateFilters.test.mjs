import assert from 'node:assert/strict'
import { test } from 'node:test'
import { mailDateRange, validMailDateRange } from '../../resources/js/Components/Contacts/Emails/mailDateFilters.js'

test('mail presets follow the mailbox timezone at midnight and cross year boundaries', () => {
    const now = new Date('2026-12-31T21:30:00Z')
    assert.deepEqual(mailDateRange('today', 'Europe/Moscow', now), { date_from: '2027-01-01', date_to: '2027-01-01' })
    assert.deepEqual(mailDateRange('yesterday', 'Europe/Moscow', now), { date_from: '2026-12-31', date_to: '2026-12-31' })
    assert.deepEqual(mailDateRange('day_before', 'Europe/Moscow', now), { date_from: '2026-12-30', date_to: '2026-12-30' })
    assert.deepEqual(mailDateRange('week', 'Europe/Moscow', now), { date_from: '2026-12-26', date_to: '2027-01-01' })
    assert.deepEqual(mailDateRange('today', 'UTC', now), { date_from: '2026-12-31', date_to: '2026-12-31' })
    assert.deepEqual(mailDateRange(null, 'Europe/Moscow', now), { date_from: null, date_to: null })
})

test('custom mail dates allow a single day and leap day but reject incomplete or reversed ranges', () => {
    assert.equal(validMailDateRange('2028-02-29', '2028-02-29'), true)
    assert.equal(validMailDateRange('2026-09-01', '2026-09-22'), true)
    assert.equal(validMailDateRange('2026-09-22', '2026-09-01'), false)
    assert.equal(validMailDateRange('2026-02-29', '2026-03-01'), false)
    assert.equal(validMailDateRange('', '2026-09-22'), false)
    assert.equal(validMailDateRange('2026-09-22', ''), false)
})
