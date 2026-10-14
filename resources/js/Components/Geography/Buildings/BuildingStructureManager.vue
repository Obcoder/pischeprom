<script setup>
import axios from 'axios'
import { computed, onScopeDispose, reactive, ref, watch } from 'vue'
import { floorGroups, floorLabel } from '@/utils/buildingFloors'
import { apartmentLabel } from '@/utils/buildingApartments'

const props = defineProps({ buildingId: { type: Number, required: true }, initialRoomId: { type: Number, default: null }, initialFloorId: { type: [Number, String], default: 'all' } })
const emit = defineEmits(['close', 'changed'])
const building = ref(null), loading = ref(false), busy = ref(false), error = ref(''), notice = ref(''), tab = ref('structure')
const activeFloor = ref('all'), search = ref(''), selectedRoom = ref(null), roomEditing = ref(false), floorEditing = ref(false), floorId = ref(null)
const roomForm = reactive({ number: '', type: 'apartment', building_floor_id: null, notes: '' }), floorForm = reactive({ number: 1, name: '', notes: '' })
const roomBaseline = ref(''), floorBaseline = ref(''), entitySearch = ref(''), entityOptions = ref([]), entityLoading = ref(false), entityError = ref(''), assignments = reactive({})
let disposed = false, generation = 0, entityVersion = 0, entityTimer, controller, entityController
const message = e => Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'Не удалось выполнить действие. Повторите попытку.'
const groups = computed(() => floorGroups(building.value))
const rooms = computed(() => (building.value?.apartments || []).filter(room => (activeFloor.value === 'all' || String(room.building_floor_id ?? 'none') === String(activeFloor.value)) && [room.number, room.notes, ...(room.entities || []).map(e => e.name)].filter(Boolean).join(' ').toLocaleLowerCase('ru').includes(search.value.trim().toLocaleLowerCase('ru'))).sort((a,b) => a.number.localeCompare(b.number,'ru',{numeric:true})))
const currentRoom = computed(() => building.value?.apartments?.find(room => room.id === selectedRoom.value))
const dirty = computed(() => (roomEditing.value && JSON.stringify(roomForm) !== roomBaseline.value) || (floorEditing.value && JSON.stringify(floorForm) !== floorBaseline.value))
const typeLabel = type => ({ apartment: 'Квартира', office: 'Офис', premise: 'Помещение' })[type]
const floorFor = room => building.value?.floors?.find(floor => floor.id === room.building_floor_id)
function allowChange() { return !busy.value && (!dirty.value || window.confirm('Есть несохранённые изменения. Продолжить без сохранения?')) }
function close() { if (allowChange()) emit('close') }
async function load() {
    controller?.abort(); controller = new AbortController(); const version = ++generation; loading.value = true; error.value = ''
    try { const {data} = await axios.get(`/api/buildings/${props.buildingId}/structure`, {signal:controller.signal}); if (!disposed && version === generation) building.value = data }
    catch(e) { if (!disposed && version === generation && e.code !== 'ERR_CANCELED') error.value = message(e) }
    finally { if (!disposed && version === generation) loading.value = false }
}
function editRoom(room = null) {
    if (!allowChange()) return
    floorEditing.value = false; roomEditing.value = true; selectedRoom.value = room?.id || null
    Object.assign(roomForm, {number:room?.number || '', type:room?.type || 'apartment', building_floor_id:room?.building_floor_id ?? (activeFloor.value !== 'all' && activeFloor.value !== 'none' ? Number(activeFloor.value) : null), notes:room?.notes || ''})
    roomBaseline.value = JSON.stringify(roomForm); entitySearch.value = ''; entityOptions.value = []; entityError.value = ''
}
function editFloor(floor = null) {
    if (!allowChange()) return
    roomEditing.value = false; floorEditing.value = true; floorId.value = floor?.id || null
    Object.assign(floorForm, {number:floor?.number ?? Math.max(0,...(building.value?.floors || []).map(f=>f.number))+1,name:floor?.name || '',notes:floor?.notes || ''}); floorBaseline.value = JSON.stringify(floorForm)
}
async function mutate(operation, success) {
    if (busy.value) return false
    busy.value = true; error.value = ''; notice.value = ''
    try { await operation(); if (disposed) return false; await load(); notice.value = success; emit('changed', building.value); return true }
    catch(e) { if (!disposed) error.value = message(e); return false }
    finally { if (!disposed) busy.value = false }
}
async function saveFloor() {
    const url = `/api/buildings/${props.buildingId}/floors`, payload = {...floorForm, number:Number(floorForm.number),name:floorForm.name.trim() || null,notes:floorForm.notes.trim() || null}
    if (await mutate(() => floorId.value ? axios.put(`${url}/${floorId.value}`,payload) : axios.post(url,payload),'Этаж сохранён.')) { floorEditing.value = false }
}
async function deleteFloor(floor) {
    if (!allowChange() || !window.confirm(`Удалить ${floorLabel(floor)}? Помещения должны быть предварительно перенесены.`)) return
    if (await mutate(()=>axios.delete(`/api/buildings/${props.buildingId}/floors/${floor.id}`),'Этаж удалён.')) { activeFloor.value='all';floorEditing.value=false }
}
async function saveRoom() {
    if (!roomForm.number.trim()) return
    const url = `/api/buildings/${props.buildingId}/apartments`, payload = {...roomForm,number:roomForm.number.trim(),building_floor_id:roomForm.building_floor_id ? Number(roomForm.building_floor_id) : null,notes:roomForm.notes.trim() || null}
    let saved
    if (await mutate(async()=>{ const response = selectedRoom.value ? await axios.put(`${url}/${selectedRoom.value}`,payload) : await axios.post(url,payload); saved=response.data },'Помещение сохранено.')) { roomEditing.value=false;editRoom(saved) }
}
async function deleteRoom(room) {
    if (!allowChange() || !window.confirm(`Удалить ${apartmentLabel(room)}?`)) return
    if (await mutate(()=>axios.delete(`/api/buildings/${props.buildingId}/apartments/${room.id}`),'Помещение удалено.')) { selectedRoom.value=null;roomEditing.value=false }
}
async function searchEntities() {
    entityController?.abort(); entityController = new AbortController(); const version=++entityVersion;entityLoading.value=true;entityError.value=''
    try { const {data}=await axios.get('/api/building-entity-options',{params:{search:entitySearch.value.trim() || undefined},signal:entityController.signal});if(!disposed&&version===entityVersion)entityOptions.value=data }
    catch(e){if(!disposed&&version===entityVersion&&e.code!=='ERR_CANCELED')entityError.value=message(e)}
    finally{if(!disposed&&version===entityVersion)entityLoading.value=false}
}
async function attach(entity, roomId=selectedRoom.value, replace=false) {
    if (!roomId) return
    if(await mutate(()=>axios.post(`/api/buildings/${props.buildingId}/apartments/${roomId}/entities`,{entity_id:entity.id,replace_building_link:replace}),'Entity привязан к помещению.')) {entityOptions.value=[];entitySearch.value=''}
}
async function detach(entity) {
    if (!currentRoom.value || !window.confirm(`Убрать связь «${entity.name}» с ${apartmentLabel(currentRoom.value)}?`)) return
    await mutate(()=>axios.delete(`/api/buildings/${props.buildingId}/apartments/${selectedRoom.value}/entities/${entity.id}`),'Связь с помещением удалена.')
}
watch(entitySearch,()=>{clearTimeout(entityTimer);entityVersion++;entityController?.abort();entityOptions.value=[];if(entitySearch.value.trim())entityTimer=setTimeout(searchEntities,250)})
onScopeDispose(()=>{disposed=true;generation++;entityVersion++;controller?.abort();entityController?.abort();clearTimeout(entityTimer)})
async function initialize(){await load();if(disposed)return;if(props.initialFloorId)activeFloor.value=props.initialFloorId;if(props.initialRoomId){const room=building.value?.apartments?.find(room=>room.id===props.initialRoomId);if(room)editRoom(room)}}
initialize()
</script>
<template>
<section class="structure-manager" aria-label="Этажи и помещения здания">
    <header class="structure-header"><div><h2>{{ building?.address || 'Загрузка здания…' }}</h2><p>{{ building?.city?.name }} <span>· {{ building?.floors?.length || 0 }} этажей · {{ building?.apartments?.length || 0 }} помещений</span></p></div><div><button :disabled="busy || loading" @click="load">Обновить</button><button :disabled="busy" aria-label="Закрыть этажи и помещения" @click="close">×</button></div></header>
    <div v-if="error" class="structure-message error" role="alert">{{ error }}</div><div v-if="notice" class="structure-message success" role="status">{{ notice }}</div>
    <nav class="structure-tabs"><button :class="{active:tab==='structure'}" @click="tab='structure'">Этажи и помещения</button><button :class="{active:tab==='legacy'}" @click="tab='legacy'">Entity без помещения <b>{{ building?.unassigned_entities?.length || 0 }}</b></button></nav>
    <div v-if="loading && !building" class="structure-empty">Загрузка…</div>
    <div v-else-if="tab==='structure'" class="structure-grid">
        <aside class="floor-sidebar"><div class="sidebar-heading"><strong>Этажи</strong><button :disabled="busy" aria-label="Добавить этаж" @click="editFloor()">+</button></div><button :class="['floor-choice',{active:activeFloor==='all'}]" @click="activeFloor='all'">Все помещения <b>{{ building?.apartments?.length || 0 }}</b></button><div v-for="group in groups" :key="group.id ?? 'none'" class="floor-entry"><button :class="['floor-choice',{active:String(activeFloor)===String(group.id ?? 'none')}]" @click="activeFloor=group.id ?? 'none'"><span>{{ group.label }}</span><b>{{ group.rooms.length }}</b></button><button v-if="group.floor" :disabled="busy" :aria-label="`Изменить ${group.label}`" class="floor-edit" @click="editFloor(group.floor)">✎</button></div><button v-if="!groups.some(g=>g.id===null)" class="floor-choice" @click="activeFloor='none'">Без этажа <b>0</b></button><p>0 — цокольный; отрицательные номера — подземные этажи.</p></aside>
        <main class="rooms-panel"><div class="rooms-toolbar"><input v-model="search" type="search" aria-label="Поиск помещения" placeholder="Номер, Entity, заметка"><button :disabled="busy" class="primary" @click="editRoom()">+ Помещение</button></div><div class="rooms-table-scroll"><table class="rooms-table"><thead><tr><th>Помещение</th><th>Этаж</th><th>Entity</th><th></th></tr></thead><tbody><tr v-for="room in rooms" :key="room.id" :class="{selected:room.id===selectedRoom}"><td><button class="room-link" @click="editRoom(room)">{{ apartmentLabel(room) }}</button><small v-if="room.notes" :title="room.notes">{{ room.notes }}</small></td><td>{{ floorFor(room) ? `${floorFor(room).number} эт.` : 'Без этажа' }}</td><td><a v-for="entity in room.entities || []" :key="entity.id" :href="`/Ameise/entity/${entity.id}`" target="_blank" rel="noopener">{{ entity.name }} ↗</a><span v-if="!room.entities?.length" class="muted">Нет привязки</span></td><td><button :disabled="busy" :aria-label="`Удалить ${apartmentLabel(room)}`" class="danger" @click="deleteRoom(room)">×</button></td></tr><tr v-if="!rooms.length"><td colspan="4" class="structure-empty">Помещений по выбранным условиям нет.</td></tr></tbody></table></div></main>
        <aside class="structure-editor">
            <form v-if="floorEditing" @submit.prevent="saveFloor"><h3>{{ floorId ? 'Изменить этаж' : 'Новый этаж' }}</h3><label>Номер этажа<input v-model="floorForm.number" type="number" min="-20" max="300" step="1" required :disabled="busy"></label><label>Название<input v-model="floorForm.name" maxlength="80" placeholder="Например, цокольный" :disabled="busy"></label><label>Заметка<textarea v-model="floorForm.notes" rows="3" maxlength="5000" :disabled="busy"></textarea></label><div class="form-actions"><button type="button" :disabled="busy" @click="allowChange() && (floorEditing=false)">Отмена</button><button class="primary" :disabled="busy">Сохранить этаж</button></div><button v-if="floorId" type="button" class="danger" :disabled="busy" @click="deleteFloor(building.floors.find(f=>f.id===floorId))">Удалить этаж</button></form>
            <template v-else-if="roomEditing"><form @submit.prevent="saveRoom"><h3>{{ selectedRoom ? 'Помещение' : 'Новое помещение' }}</h3><div class="form-pair"><label>Тип<select aria-label="Тип помещения" v-model="roomForm.type" :disabled="busy"><option value="apartment">Квартира</option><option value="office">Офис</option><option value="premise">Помещение</option></select></label><label>Номер<input v-model="roomForm.number" required maxlength="50" placeholder="12А" :disabled="busy"></label></div><label>Этаж<select aria-label="Этаж помещения" v-model="roomForm.building_floor_id" :disabled="busy"><option :value="null">Без этажа</option><option v-for="floor in building?.floors || []" :key="floor.id" :value="floor.id">{{ floorLabel(floor) }}</option></select></label><label>Заметка<textarea v-model="roomForm.notes" rows="2" maxlength="5000" :disabled="busy"></textarea></label><div class="form-actions"><button type="button" :disabled="busy" @click="allowChange() && (roomEditing=false)">Отмена</button><button class="primary" :disabled="busy || !roomForm.number.trim()">Сохранить помещение</button></div></form><section v-if="currentRoom" class="entity-links"><h3>Entity в {{ apartmentLabel(currentRoom) }}</h3><div v-for="entity in currentRoom.entities || []" :key="entity.id" class="linked-entity"><a :href="`/Ameise/entity/${entity.id}`" target="_blank" rel="noopener">{{ entity.name }} ↗<small v-if="entity.INN">ИНН {{ entity.INN }}</small></a><button :disabled="busy" :aria-label="`Отвязать ${entity.name}`" @click="detach(entity)">×</button></div><p v-if="!currentRoom.entities?.length" class="muted">Entity пока не привязаны.</p><label>Найти Entity<input v-model="entitySearch" type="search" maxlength="150" placeholder="Название или ИНН" :disabled="busy"></label><p v-if="entityError" class="error">{{ entityError }}</p><p v-if="entityLoading" class="muted">Поиск…</p><div class="entity-results"><button v-for="entity in entityOptions" :key="entity.id" :disabled="busy || currentRoom.entities?.some(e=>e.id===entity.id)" @click="attach(entity)">{{ entity.name }}<small>{{ entity.INN || `#${entity.id}` }}</small></button></div></section><p v-else class="muted">После сохранения помещения можно привязать Entity.</p></template>
            <div v-else class="structure-empty">Выберите помещение или этаж для редактирования.</div>
        </aside>
    </div>
    <section v-else class="legacy-panel"><p>Эти Entity связаны со зданием без указания помещения. Выберите квартиру, офис или помещение, чтобы уточнить адрес.</p><div class="rooms-table-scroll"><table class="rooms-table"><thead><tr><th>Entity</th><th>Помещение</th><th></th></tr></thead><tbody><tr v-for="entity in building?.unassigned_entities || []" :key="entity.id"><td>{{ entity.name }}<small>{{ entity.INN }}</small></td><td><select v-model="assignments[entity.id]" :aria-label="`Помещение для ${entity.name}`"><option :value="undefined" disabled>Выберите помещение</option><option v-for="room in building?.apartments || []" :key="room.id" :value="room.id">{{ apartmentLabel(room) }} · {{ floorFor(room) ? `${floorFor(room).number} эт.` : 'Без этажа' }}</option></select></td><td><button :disabled="busy || !assignments[entity.id]" @click="attach(entity,assignments[entity.id],true)">Привязать к помещению</button></td></tr><tr v-if="!building?.unassigned_entities?.length"><td colspan="3" class="structure-empty">Нет адресов, требующих уточнения.</td></tr></tbody></table></div></section>
</section>
</template>
<style scoped>
.structure-manager{background:#f6f7f9;color:#35404c;border:1px solid #cfd6df;border-radius:9px;font:12px/1.4 -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-height:92dvh;display:flex;flex-direction:column;overflow:hidden}.structure-manager *{box-sizing:border-box}.structure-header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px;background:#fff;flex-shrink:0}.structure-header h2{font-size:16px;font-weight:600;margin:0;overflow-wrap:anywhere}.structure-header p{margin:3px 0 0;color:#7c8792;font-size:11px}.structure-header>div:last-child{display:flex;gap:6px;flex-shrink:0}.structure-tabs{display:flex;gap:5px;padding:5px 12px;background:#edf0f4;border-block:1px solid #dce1e7}.structure-tabs button{border-color:transparent;background:transparent}.structure-tabs button.active{background:white;border-color:#d1d9e3}.structure-tabs b{font-size:10px;margin-left:4px}.structure-grid{display:grid;grid-template-columns:170px minmax(280px,1fr) 310px;min-height:420px;overflow:auto}.floor-sidebar{padding:9px;background:#f2f4f7;border-right:1px solid #e0e4ea}.sidebar-heading{display:flex;align-items:center;justify-content:space-between;margin-bottom:7px}.floor-entry{display:flex;gap:2px;margin:3px 0}.floor-choice{display:flex!important;gap:6px;justify-content:space-between;width:100%;text-align:left;white-space:normal!important;border:0!important;background:transparent!important}.floor-choice.active{background:#dfe8f3!important;color:#3f638a}.floor-choice b{font-size:10px;color:#8a96a3;font-weight:500}.floor-sidebar p{font-size:10px;color:#8a949e;margin-top:16px}.floor-edit{padding:2px 6px!important;min-width:24px}.rooms-panel{padding:10px;min-width:0}.rooms-toolbar{display:flex;gap:7px;margin-bottom:8px}.rooms-toolbar input{flex:1;min-width:70px}.rooms-table-scroll{overflow:auto;max-height:60dvh}.rooms-table{width:100%;border-collapse:collapse;text-align:left;font-size:11px;background:#fff}.rooms-table th{padding:6px;background:#eef1f5;color:#798694;font-size:10px;font-weight:500;white-space:nowrap;border-bottom:1px solid #d8dfe7}.rooms-table td{padding:7px 6px;vertical-align:top;border-bottom:1px solid #e8ecf0}.rooms-table tr.selected{background:#edf3fa}.rooms-table td small{display:block;color:#8b959e;font-size:10px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.rooms-table td a{display:block;margin-bottom:3px;overflow-wrap:anywhere}.room-link{font-weight:600!important;border:0!important;background:none!important;padding:0!important;min-height:20px!important}.structure-editor{border-left:1px solid #dce2e9;padding:12px;background:#fff;overflow:auto;max-height:72dvh}.structure-editor h3{font-size:12px;margin:0 0 10px;font-weight:600}.structure-editor form,.structure-editor label{display:grid;gap:5px}.structure-editor form{gap:10px}.structure-editor label{font-size:11px;color:#7d8895}.form-pair{display:grid;grid-template-columns:1fr 95px;gap:8px}.form-actions{display:flex;justify-content:flex-end;gap:6px}.entity-links{border-top:1px solid #e0e5eb;margin-top:14px;padding-top:12px}.linked-entity{display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f0f2f5}.linked-entity>a{flex:1;overflow-wrap:anywhere}.linked-entity small{display:block;color:#929aa3;font-size:10px}.entity-links>label{margin-top:10px}.entity-results{max-height:200px;overflow:auto}.entity-results button{display:block;width:100%;text-align:left;white-space:normal!important;margin-top:4px}.entity-results small{display:block;color:#98a0aa;font-size:10px}.legacy-panel{padding:14px;overflow:auto}.legacy-panel>p{color:#7c8793;margin:0 0 12px}.structure-empty{text-align:center;padding:25px 12px;color:#929ca6}.structure-message{padding:7px 14px;font-size:11px}.error{color:#a24d56;background:#fff2f3}.success{color:#527c60;background:#eef7f0}.muted{color:#919ba5;font-size:11px}button,input,select,textarea{font:inherit}button{min-height:28px;border:1px solid #d5dce5;background:#fff;border-radius:4px;padding:4px 8px;color:#617285;cursor:pointer;white-space:nowrap}button:hover:enabled{background:#eaf0f7}button:disabled{opacity:.45;cursor:default}button.primary{background:#617fa4;color:#fff;border-color:#617fa4}.danger{color:#ae5c62}input,select,textarea{width:100%;min-width:0;border:1px solid #d5dce5;border-radius:4px;padding:5px 7px;background:#fff;color:#455466;min-height:29px}textarea{resize:vertical}a{color:#52779b;text-decoration:none}a:hover{text-decoration:underline}:is(button,input,select,textarea,a):focus-visible{outline:2px solid #91afd1;outline-offset:2px}@media(max-width:1050px){.structure-grid{grid-template-columns:140px minmax(220px,1fr)}.structure-editor{grid-column:1/-1;border-left:0;border-top:1px solid #dce2e9;max-height:none}.structure-editor>form{max-width:600px}.rooms-table-scroll{max-height:45vh}}@media(max-width:600px){.structure-header{flex-wrap:wrap}.structure-grid{grid-template-columns:95px minmax(0,1fr)}.floor-sidebar{padding:5px}.floor-choice{font-size:10px}.rooms-toolbar{flex-wrap:wrap}.rooms-panel{padding:5px}.rooms-table{min-width:320px}.structure-tabs{overflow:auto}.structure-editor{padding:10px}.structure-header h2{font-size:14px}}
</style>
