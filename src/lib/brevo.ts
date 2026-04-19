// Brevo (Sendinblue) email integration
// Documentation: https://www.brevo.com/

export interface EmailPayload {
  to: string
  subject: string
  htmlContent: string
  textContent?: string
  from?: {
    email: string
    name: string
  }
}

export async function sendEmail(payload: EmailPayload): Promise<void> {
  // TODO: Implement Brevo API call to send email
  // Use BREVO_API_KEY from env
  throw new Error('Brevo email integration not yet implemented')
}

export async function sendAdhesionConfirmation(
  email: string,
  firstName: string,
  montant: number
): Promise<void> {
  // TODO: Implement adhesion confirmation email
  throw new Error('Adhesion confirmation email not yet implemented')
}

export async function sendPaymentWebhookNotification(
  email: string,
  paymentId: string,
  status: string
): Promise<void> {
  // TODO: Implement payment notification email
  throw new Error('Payment notification email not yet implemented')
}
