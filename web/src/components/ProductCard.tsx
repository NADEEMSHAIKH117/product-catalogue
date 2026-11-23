import { Link } from 'react-router-dom'
import type { Product } from '../api/types'
import { formatPrice } from '../lib/format'

export function ProductCard({ product }: { product: Product }) {
  return (
    <Link to={`/products/${product.id}`} className="product-card">
      <div className="product-card__image">
        {product.image_url && <img src={product.image_url} alt="" loading="lazy" />}
      </div>
      <div className="product-card__body">
        <span className="product-card__category">{product.category.name}</span>
        <h3 className="product-card__title">{product.title}</h3>
        <div className="product-card__meta">
          <span className="price">{formatPrice(product.price)}</span>
          {product.rating.average !== null && (
            <span className="rating" aria-label={`Rated ${product.rating.average} out of 5`}>
              ★ {product.rating.average.toFixed(1)}
            </span>
          )}
        </div>
      </div>
    </Link>
  )
}
