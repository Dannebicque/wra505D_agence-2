import { categories } from '../data/documents'

export default defineEventHandler(() =>
  collectionHydra('DocumentCategory', '/api/document_categories', categories),
)
