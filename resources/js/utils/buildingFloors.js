import { apartmentLabel } from './buildingApartments.js'

export const floorLabel = floor => floor ? `${floor.number} этаж${floor.name ? ` · ${floor.name}` : ''}` : 'Без этажа'
export function floorGroups(building) {
    const rooms = [...(building?.apartments || [])].sort((a, b) => String(a.number).localeCompare(String(b.number), 'ru', { numeric: true }) || a.id - b.id)
    const floors = [...(building?.floors || [])].sort((a, b) => b.number - a.number)
    const groups = floors.map(floor => ({ id: floor.id, floor, label: floorLabel(floor), rooms: rooms.filter(room => Number(room.building_floor_id) === Number(floor.id)) }))
    const known = new Set(floors.map(floor => Number(floor.id)))
    const unassigned = rooms.filter(room => !room.building_floor_id || !known.has(Number(room.building_floor_id)))
    if (unassigned.length || !groups.length) groups.push({ id: null, floor: null, label: 'Без этажа', rooms: unassigned })
    return groups
}
export const roomTitle = room => [apartmentLabel(room), ...(room.entities || []).map(entity => entity.name), room.notes].filter(Boolean).join(' · ')
