import assert from 'node:assert/strict'
import { test } from 'node:test'
import { sentCopyNotice, readStatusTitle } from '../../resources/js/Components/Contacts/Emails/mailMessageStatus.js'

test('local read flags cannot claim that an unlinked message is read on the server', () => {
    const message = { direction: 'outgoing', is_seen: true, imap_uid: null, sent_copy_status: 'legacy' }
    assert.match(readStatusTitle(message), /локальная архивная копия/)
    assert.doesNotMatch(readStatusTitle(message), /Прочитано на почтовом сервере/)
    assert.equal(readStatusTitle({ ...message, imap_uid: 24 }), 'Прочитано на почтовом сервере')
})

test('uncertain SMTP and pending server archive have distinct outcomes', () => {
    assert.match(sentCopyNotice({ direction: 'outgoing', delivery_status: 'unknown' }), /Проверьте доставку перед повторной отправкой/)
    assert.match(sentCopyNotice({ direction: 'outgoing', delivery_status: 'sent', sent_copy_status: 'pending' }), /Письмо отправлено/)
    assert.match(sentCopyNotice({ direction: 'outgoing', delivery_status: 'sent', sent_copy_status: 'failed' }), /повторно отправлять письмо не нужно/)
    assert.equal(sentCopyNotice({ direction: 'incoming' }), null)
})

test('reconstructed archives are distinguished from original server copies', () => {
    assert.match(sentCopyNotice({ direction: 'outgoing', imap_uid: 15, sent_copy_status: 'reconstructed' }), /восстановлена/)
    assert.equal(sentCopyNotice({ direction: 'outgoing', imap_uid: 15, sent_copy_status: 'linked' }), null)
})
