import test from 'node:test'
import assert from 'node:assert/strict'
import { deliveryBuildingAddress, deliveryMapUrl, telephoneHref } from '../src/delivery.js'

test('phone action uses only the server normalized dial number', () => {
    assert.equal(telephoneHref({ number: '+7 (999) 123-45-67', dial_number: '+79991234567' }), 'tel:+79991234567')
    for (const dial_number of ['', 'javascript:alert(1)', '+79991234567?other=1', '*100#', '123;456', '7'.repeat(21)]) {
        assert.equal(telephoneHref({ dial_number }), null)
    }
    assert.equal(telephoneHref(null), null)
})

test('map action preserves an approved Yandex URL and safely encodes an address fallback', () => {
    const url = 'https://yandex.ru/maps/?text=%D0%9C%D0%BE%D1%81%D0%BA%D0%B2%D0%B0'
    assert.equal(deliveryMapUrl({ yandex_maps_url: url }), url)
    const address = 'Москва, ул. Лесная, 5 & 7'
    for (const yandex_maps_url of ['javascript:alert(1)', 'https://yandex.ru.evil.test/maps/', 'https://user:pass@yandex.ru/maps/', 'http://yandex.ru/maps/']) {
        const result = new URL(deliveryMapUrl({ yandex_maps_url, full_address: address }))
        assert.equal(result.origin, 'https://yandex.ru')
        assert.equal(result.searchParams.get('text'), address)
    }
    assert.equal(deliveryMapUrl({}), null)
})

test('map navigation excludes the apartment while the displayed delivery address retains its number', () => {
    const address = {
        building_address: 'Москва, Лесная, 5',
        full_address: 'Москва, Лесная, 5, офис 12А',
        apartment: { id: 1, number: '12А', type: 'office', label: 'офис 12А' },
    }
    assert.equal(deliveryBuildingAddress(address), 'Москва, Лесная, 5')
    assert.equal(new URL(deliveryMapUrl(address)).searchParams.get('text'), 'Москва, Лесная, 5')
    assert.equal(address.full_address, 'Москва, Лесная, 5, офис 12А')
    assert.equal(deliveryBuildingAddress({ full_address: 'Старый адрес, 8' }), 'Старый адрес, 8')
})
