import { documents } from '../data/documents'

export default defineEventHandler(() => collectionHydra('Document', '/api/documents', documents))
