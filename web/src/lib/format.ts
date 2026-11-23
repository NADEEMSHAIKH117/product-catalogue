// Fake Store doesn't specify a currency; we assume AUD for an Australian audience (see DECISIONS.md).
const priceFormatter = new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' })

export const formatPrice = (price: number) => priceFormatter.format(price)
