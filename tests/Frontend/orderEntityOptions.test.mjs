import assert from 'node:assert/strict'
import { test } from 'node:test'
import { entityContacts, entityMatches, entityRequisites } from '../../resources/js/utils/orderEntityOptions.js'

const entity = {
    id: 17, name: 'Ромашка', full_name: 'ООО «Торговый дом Ромашка»',
    INN: '7701234567', KPP: '770101001', OGRN: '1027700123456',
    legal_address: 'Москва, улица Ленина, 15', classification: { name: 'Магазин' },
    telephones: [{ number: '+7 (999) 123-45-67' }], emails: [{ address: 'shop@example.test' }],
}

test('entity search identifies a customer by legal name, tax IDs, address, contact or formatted phone', () => {
    for (const query of ['ромашка торговый', '7701234567', '770101001', '1027700123456', 'москва ленина', 'МАГАЗИН', 'shop@example', '9991234567', '+7 999 123 45 67', '17']) {
        assert.equal(entityMatches(entity, query), true, query)
    }
    assert.equal(entityMatches(entity, 'Другой покупатель'), false)
    assert.equal(entityMatches(entity, 'Ромашка неизвестный'), false)
    assert.equal(entityMatches(entity, ''), true)
})

test('entity summaries tolerate missing fields without exposing empty labels', () => {
    assert.deepEqual(entityRequisites(entity), ['ИНН 7701234567', 'КПП 770101001', 'ОГРН 1027700123456'])
    assert.deepEqual(entityContacts(entity), ['+7 (999) 123-45-67', 'shop@example.test'])
    assert.deepEqual(entityRequisites({ name: 'ИП' }), [])
    assert.deepEqual(entityContacts({ name: 'ИП' }), [])
    assert.equal(entityMatches({ name: 'ИП' }, 'ип'), true)
})
