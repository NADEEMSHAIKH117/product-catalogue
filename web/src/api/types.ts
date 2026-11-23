// Types mirror the backend's API contract (api/app/Http/Resources), not Fake Store.

export interface Category {
  slug: string
  name: string
}

export interface Product {
  id: number
  title: string
  description: string
  price: number
  category: Category
  image_url: string | null
  rating: {
    average: number | null
    count: number
  }
}

export interface CategoryProducts {
  category: Category
  top_rated: Product[]
  others: Product[]
}

export interface EnquiryInput {
  product_id: number
  name: string
  email: string
  phone?: string
  message?: string
}

export type FieldErrors = Partial<Record<keyof EnquiryInput, string[]>>
