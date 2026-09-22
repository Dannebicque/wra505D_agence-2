import { evenements } from '../data/pedagogie'

export default defineEventHandler(() => collectionHydra('EdtEvent', '/api/edt_events', evenements))
