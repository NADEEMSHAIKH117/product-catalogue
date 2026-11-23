import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, ApiError } from '../api/client'
import type { FieldErrors } from '../api/types'
import { AsyncContent } from '../components/AsyncContent'
import { useAsync } from '../hooks/useAsync'
import { parseProductId } from '../lib/params'
import { NotFoundPage } from './NotFoundPage'

type SubmitState =
  | { status: 'idle' }
  | { status: 'submitting' }
  | { status: 'error'; message: string; fieldErrors: FieldErrors }
  | { status: 'sent'; message: string }

export function EnquiryPage() {
  const id = parseProductId(useParams().id)

  if (id === null) return <NotFoundPage />

  return <ProductLoader id={id} />
}

function ProductLoader({ id }: { id: number }) {
  const product = useAsync(() => api.product(id), [id])

  return (
    <AsyncContent state={product}>
      {(product) => <EnquiryForm productId={product.id} productTitle={product.title} />}
    </AsyncContent>
  )
}

function EnquiryForm({ productId, productTitle }: { productId: number; productTitle: string }) {
  const [submit, setSubmit] = useState<SubmitState>({ status: 'idle' })
  const formRef = useRef<HTMLFormElement>(null)

  // After a validation failure, move focus to the first invalid field.
  useEffect(() => {
    if (submit.status === 'error') {
      formRef.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus()
    }
  }, [submit])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    setSubmit({ status: 'submitting' })

    try {
      const result = await api.submitEnquiry({
        product_id: productId,
        name: String(form.get('name') ?? ''),
        email: String(form.get('email') ?? ''),
        phone: String(form.get('phone') ?? '') || undefined,
        message: String(form.get('message') ?? '') || undefined,
      })
      setSubmit({ status: 'sent', message: result.message })
    } catch (error) {
      const apiError = error instanceof ApiError ? error : null
      setSubmit({
        status: 'error',
        message: apiError?.message ?? 'Something went wrong. Please try again.',
        fieldErrors: apiError?.fieldErrors ?? {},
      })
    }
  }

  if (submit.status === 'sent') {
    return (
      <section className="thank-you" role="status">
        <h1>Thank you!</h1>
        <p>{submit.message}</p>
        <Link to={`/products/${productId}`}>Back to {productTitle}</Link>
      </section>
    )
  }

  const fieldErrors = submit.status === 'error' ? submit.fieldErrors : {}
  const errorFor = (field: keyof FieldErrors) => fieldErrors[field]?.[0]
  // Links an input to its error message for screen readers.
  const errorProps = (field: keyof FieldErrors) =>
    errorFor(field) ? { 'aria-invalid': true, 'aria-describedby': `${field}-error` } : {}
  const fieldError = (field: keyof FieldErrors) =>
    errorFor(field) && (
      <span className="field-error" id={`${field}-error`}>{errorFor(field)}</span>
    )

  return (
    <section className="enquiry">
      <h1>Enquire about {productTitle}</h1>
      <p>Leave your details and our sales team will get back to you.</p>

      {submit.status === 'error' && (
        <p className="status status--error" role="alert">{submit.message}</p>
      )}

      <form className="form" onSubmit={handleSubmit} ref={formRef}>
        <label>
          Name
          <input name="name" required maxLength={100} autoComplete="name" {...errorProps('name')} />
          {fieldError('name')}
        </label>
        <label>
          Email
          <input name="email" type="email" required maxLength={254} autoComplete="email" {...errorProps('email')} />
          {fieldError('email')}
        </label>
        <label>
          Phone <span className="optional">(optional)</span>
          <input name="phone" type="tel" maxLength={30} autoComplete="tel" {...errorProps('phone')} />
          {fieldError('phone')}
        </label>
        <label>
          Message <span className="optional">(optional)</span>
          <textarea name="message" rows={5} maxLength={2000} {...errorProps('message')} />
          {fieldError('message')}
        </label>
        <button type="submit" disabled={submit.status === 'submitting'}>
          {submit.status === 'submitting' ? 'Sending…' : 'Send enquiry'}
        </button>
      </form>
    </section>
  )
}
