import { Link, useParams } from 'react-router-dom'
import { api } from '../api/client'
import { AsyncContent } from '../components/AsyncContent'
import { useAsync } from '../hooks/useAsync'
import { parseProductId } from '../lib/params'
import { NotFoundPage } from './NotFoundPage'
import { formatPrice } from '../lib/format'

export function ProductPage() {
  const id = parseProductId(useParams().id)

  if (id === null) return <NotFoundPage />

  return <ProductLoader id={id} />
}

function ProductLoader({ id }: { id: number }) {
  const state = useAsync(() => api.product(id), [id])

  return (
    <AsyncContent state={state}>
      {(product) => (
        <article className="product-detail">
          <div className="product-detail__image">
            {product.image_url && <img src={product.image_url} alt={product.title} />}
          </div>
          <div className="product-detail__info">
            <Link to={`/category/${product.category.slug}`} className="product-card__category">
              {product.category.name}
            </Link>
            <h1>{product.title}</h1>
            <p className="price price--large">{formatPrice(product.price)}</p>
            {product.rating.average !== null && (
              <p className="rating">
                ★ {product.rating.average.toFixed(1)} ({product.rating.count} reviews)
              </p>
            )}
            <p>{product.description}</p>
            <Link to={`/products/${product.id}/enquire`} className="button">
              Enquire
            </Link>
          </div>
        </article>
      )}
    </AsyncContent>
  )
}
