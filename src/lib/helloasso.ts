// HelloAsso API integration
// Documentation: https://www.helloasso.com/

export interface HelloAssoPaymentLink {
  id: string
  url: string
  amount: number
  itemName: string
}

export async function createPaymentLink(
  amount: number,
  itemName: string,
  userEmail: string
): Promise<HelloAssoPaymentLink> {
  // TODO: Implement HelloAsso API call to create payment link
  // This will be called from /api/adhesion/calculate route
  throw new Error('HelloAsso integration not yet implemented')
}

export async function verifyWebhookSignature(
  payload: string,
  signature: string
): Promise<boolean> {
  // TODO: Implement webhook signature verification
  // Use HELLOASSO_WEBHOOK_SECRET from env
  throw new Error('Webhook verification not yet implemented')
}

export interface HelloAssoWebhookPayload {
  id: string
  state: 'authorized' | 'refused'
  payer: {
    email: string
  }
  amount: number
  metadata?: Record<string, string>
}

export async function processPaymentWebhook(
  payload: HelloAssoWebhookPayload
): Promise<void> {
  // TODO: Implement webhook processing
  // Update Adhesion.statutPaiement based on state
  throw new Error('Webhook processing not yet implemented')
}
