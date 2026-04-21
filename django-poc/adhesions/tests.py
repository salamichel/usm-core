import hashlib
import hmac
import json
from decimal import Decimal
from unittest.mock import patch

from django.contrib.auth import get_user_model
from django.test import Client, TestCase, override_settings
from django.urls import reverse

from adhesions.models import Adhesion, StatutPaiement
from adhesions.services.helloasso_client import (
    CheckoutIntent,
    HelloAssoError,
    HelloAssoNotConfigured,
)
from seasons.models import Saison
from teams.models import CategorieAdhesion


WEBHOOK_SECRET = "test-webhook-secret"


@override_settings(HELLOASSO_WEBHOOK_SECRET=WEBHOOK_SECRET)
class HelloAssoWebhookTests(TestCase):
    def setUp(self):
        self.client = Client()
        self.url = reverse("helloasso_webhook")
        self.saison = Saison.objects.create(label="2024-2025", is_active=True)
        self.user = get_user_model().objects.create_user(
            email="user@example.com",
            password="pwd12345",
            first_name="John",
            last_name="Doe",
        )
        self.adhesion = Adhesion.objects.create(
            user=self.user,
            saison=self.saison,
            categorie_adhesion=CategorieAdhesion.COMPETITION_VOLLEY,
            montant=Decimal("100.00"),
            statut_paiement=StatutPaiement.EN_ATTENTE,
        )

    def _sign(self, payload: dict) -> tuple[str, str]:
        raw = json.dumps(payload)
        digest = hmac.new(
            WEBHOOK_SECRET.encode(),
            raw.encode(),
            hashlib.sha256,
        ).hexdigest()
        return raw, f"sha256={digest}"

    def _payload(self, **overrides) -> dict:
        base = {
            "id": "webhook-1",
            "eventType": "Payment",
            "data": {
                "id": "payment-1",
                "payer": {"email": "user@example.com"},
                "amount": 10000,
                "state": "authorized",
                "metadata": {"source": "test"},
            },
        }
        base.update(overrides)
        return base

    def test_valid_webhook_marks_adhesion_validee(self):
        raw, sig = self._sign(self._payload())

        response = self.client.post(
            self.url,
            data=raw,
            content_type="application/json",
            HTTP_X_HELLOASSO_SIGNATURE=sig,
        )

        self.assertEqual(response.status_code, 200)
        self.adhesion.refresh_from_db()
        self.assertEqual(self.adhesion.statut_paiement, StatutPaiement.VALIDE)
        self.assertEqual(self.adhesion.helloasso_payment_id, "payment-1")
        # webhook_id = payment_id (HelloAsso ne renvoie pas d'id racine distinct)
        self.assertEqual(self.adhesion.helloasso_webhook_id, "payment-1")
        self.assertEqual(self.adhesion.helloasso_payer_email, "user@example.com")
        self.assertIsNotNone(self.adhesion.last_webhook_at)

    def test_refunded_state_marks_remboursee(self):
        payload = self._payload(
            id="webhook-refund",
            data={
                "id": "payment-2",
                "payer": {"email": "user@example.com"},
                "amount": 10000,
                "state": "refunded",
            },
        )
        raw, sig = self._sign(payload)

        response = self.client.post(
            self.url,
            data=raw,
            content_type="application/json",
            HTTP_X_HELLOASSO_SIGNATURE=sig,
        )

        self.assertEqual(response.status_code, 200)
        self.adhesion.refresh_from_db()
        self.assertEqual(self.adhesion.statut_paiement, StatutPaiement.REMBOURSE)

    def test_missing_signature_rejected(self):
        raw = json.dumps(self._payload())
        response = self.client.post(
            self.url,
            data=raw,
            content_type="application/json",
        )
        self.assertEqual(response.status_code, 401)

    def test_invalid_signature_rejected(self):
        raw = json.dumps(self._payload())
        response = self.client.post(
            self.url,
            data=raw,
            content_type="application/json",
            HTTP_X_HELLOASSO_SIGNATURE="sha256=invalid",
        )
        self.assertEqual(response.status_code, 401)
        self.adhesion.refresh_from_db()
        self.assertEqual(self.adhesion.statut_paiement, StatutPaiement.EN_ATTENTE)

    def test_duplicate_webhook_is_idempotent(self):
        raw, sig = self._sign(self._payload())

        r1 = self.client.post(self.url, data=raw, content_type="application/json",
                              HTTP_X_HELLOASSO_SIGNATURE=sig)
        r2 = self.client.post(self.url, data=raw, content_type="application/json",
                              HTTP_X_HELLOASSO_SIGNATURE=sig)

        self.assertEqual(r1.status_code, 200)
        self.assertEqual(r2.status_code, 200)
        self.assertEqual(
            Adhesion.objects.filter(helloasso_webhook_id="payment-1").count(),
            1,
        )

    def test_adhesion_not_found_returns_202(self):
        payload = self._payload(
            data={
                "id": "payment-xx",
                "payer": {"email": "nobody@example.com"},
                "amount": 10000,
                "state": "authorized",
            },
        )
        raw, sig = self._sign(payload)

        response = self.client.post(
            self.url,
            data=raw,
            content_type="application/json",
            HTTP_X_HELLOASSO_SIGNATURE=sig,
        )
        self.assertEqual(response.status_code, 202)

    def test_invalid_json_returns_400(self):
        raw = "not-json"
        digest = hmac.new(WEBHOOK_SECRET.encode(), raw.encode(), hashlib.sha256).hexdigest()
        response = self.client.post(
            self.url,
            data=raw,
            content_type="application/json",
            HTTP_X_HELLOASSO_SIGNATURE=f"sha256={digest}",
        )
        self.assertEqual(response.status_code, 400)


@override_settings(
    HELLOASSO_CLIENT_ID="fake-id",
    HELLOASSO_CLIENT_SECRET="fake-secret",
    HELLOASSO_ORGANIZATION_SLUG="usm-volley",
)
class AdhesionPaymentViewTests(TestCase):
    def setUp(self):
        self.client = Client()
        self.saison = Saison.objects.create(label="2024-2025", is_active=True)
        self.user = get_user_model().objects.create_user(
            email="user@example.com",
            password="pwd12345",
            first_name="John",
            last_name="Doe",
        )
        self.adhesion = Adhesion.objects.create(
            user=self.user,
            saison=self.saison,
            categorie_adhesion=CategorieAdhesion.COMPETITION_VOLLEY,
            montant=Decimal("100.00"),
            statut_paiement=StatutPaiement.EN_ATTENTE,
        )
        self.client.force_login(self.user)

    @patch("adhesions.views_payment.create_checkout_intent")
    def test_payer_redirects_to_helloasso(self, mock_checkout):
        mock_checkout.return_value = CheckoutIntent(
            id="intent-abc",
            redirect_url="https://helloasso.com/checkout/intent-abc",
        )

        url = reverse("adhesion_payer", args=[self.adhesion.pk])
        response = self.client.post(url)

        self.assertEqual(response.status_code, 302)
        self.assertEqual(response["Location"], "https://helloasso.com/checkout/intent-abc")

        self.adhesion.refresh_from_db()
        self.assertEqual(self.adhesion.helloasso_checkout_intent_id, "intent-abc")

        # Checkout called with les bonnes valeurs
        kwargs = mock_checkout.call_args.kwargs
        self.assertEqual(kwargs["amount"], Decimal("100.00"))
        self.assertEqual(kwargs["payer_email"], "user@example.com")
        self.assertEqual(kwargs["metadata"]["adhesion_id"], self.adhesion.pk)

    @patch("adhesions.views_payment.create_checkout_intent")
    def test_payer_already_paid_redirects(self, mock_checkout):
        self.adhesion.statut_paiement = StatutPaiement.VALIDE
        self.adhesion.save()

        url = reverse("adhesion_payer", args=[self.adhesion.pk])
        response = self.client.post(url)

        self.assertEqual(response.status_code, 302)
        self.assertEqual(response["Location"], reverse("mon_compte_adhesions"))
        mock_checkout.assert_not_called()

    @patch(
        "adhesions.views_payment.create_checkout_intent",
        side_effect=HelloAssoNotConfigured("missing creds"),
    )
    def test_payer_not_configured_shows_error(self, _mock):
        url = reverse("adhesion_payer", args=[self.adhesion.pk])
        response = self.client.post(url, follow=True)

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, "pas encore configuré")

    @patch(
        "adhesions.views_payment.create_checkout_intent",
        side_effect=HelloAssoError("boom"),
    )
    def test_payer_api_error_shows_error(self, _mock):
        url = reverse("adhesion_payer", args=[self.adhesion.pk])
        response = self.client.post(url, follow=True)

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, "Impossible d&#x27;initier le paiement")

    def test_payer_other_user_returns_404(self):
        other = get_user_model().objects.create_user(
            email="other@example.com", password="pwd12345"
        )
        self.client.force_login(other)

        url = reverse("adhesion_payer", args=[self.adhesion.pk])
        response = self.client.post(url)
        self.assertEqual(response.status_code, 404)

    def test_retour_view_renders(self):
        url = reverse("adhesion_paiement_retour", args=[self.adhesion.pk])
        response = self.client.get(url)
        self.assertEqual(response.status_code, 200)
        self.assertContains(response, "Merci")

    def test_erreur_view_renders(self):
        url = reverse("adhesion_paiement_erreur", args=[self.adhesion.pk])
        response = self.client.get(url)
        self.assertEqual(response.status_code, 200)
        self.assertContains(response, "Paiement non finalisé")
