import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { fakeApi, makeProduct, renderAt } from './fakeApi'

const product = makeProduct(3, 'Mens Cotton Jacket')

async function openEnquiryFormAndFillIn() {
  renderAt('/products/3')
  await userEvent.click(await screen.findByRole('link', { name: 'Enquire' }))
  await screen.findByRole('heading', { name: 'Enquire about Mens Cotton Jacket' })

  await userEvent.type(screen.getByLabelText('Name'), 'Jane Doe')
  await userEvent.type(screen.getByLabelText('Email'), 'jane@example.com')
  await userEvent.type(screen.getByLabelText(/Message/), 'Do you have XL?')
}

describe('Enquiry', () => {
  it('submits contact details for the product and thanks the customer', async () => {
    let sentBody: unknown
    fakeApi({
      'GET /api/v1/products/3': () => ({ body: { data: product } }),
      'POST /api/v1/enquiries': (_url, init) => {
        sentBody = JSON.parse(String(init?.body))
        return { status: 202, body: { message: 'Thank you! Our sales team will be in touch soon.' } }
      },
    })

    await openEnquiryFormAndFillIn()
    await userEvent.click(screen.getByRole('button', { name: 'Send enquiry' }))

    expect(await screen.findByRole('heading', { name: 'Thank you!' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Send enquiry' })).not.toBeInTheDocument()
    // Only the product ID is sent; the server looks the product up itself.
    expect(sentBody).toEqual({
      product_id: 3,
      name: 'Jane Doe',
      email: 'jane@example.com',
      message: 'Do you have XL?',
    })
  })

  it('shows server validation errors next to the fields and keeps the form', async () => {
    fakeApi({
      'GET /api/v1/products/3': () => ({ body: { data: product } }),
      'POST /api/v1/enquiries': () => ({
        status: 422,
        body: {
          message: 'The email field must be a valid email address.',
          errors: { email: ['The email field must be a valid email address.'] },
        },
      }),
    })

    await openEnquiryFormAndFillIn()
    await userEvent.click(screen.getByRole('button', { name: 'Send enquiry' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('valid email address')
    const email = screen.getByLabelText(/Email/)
    expect(email).toHaveValue('jane@example.com')
    // The field error is announced with the input, and focus moves to it.
    expect(email).toHaveAttribute('aria-invalid', 'true')
    expect(email).toHaveAccessibleDescription('The email field must be a valid email address.')
    expect(email).toHaveFocus()
    expect(screen.queryByText('Thank you!')).not.toBeInTheDocument()
  })

  it('does not thank the customer if the email could not be sent', async () => {
    fakeApi({
      'GET /api/v1/products/3': () => ({ body: { data: product } }),
      'POST /api/v1/enquiries': () => ({
        status: 503,
        body: { message: 'Sorry, we could not send your enquiry right now. Please try again shortly.' },
      }),
    })

    await openEnquiryFormAndFillIn()
    await userEvent.click(screen.getByRole('button', { name: 'Send enquiry' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('could not send your enquiry')
    expect(screen.getByRole('button', { name: 'Send enquiry' })).toBeEnabled()
  })
})
