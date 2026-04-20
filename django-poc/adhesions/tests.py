import hashlib
import hmac
import json
from decimal import Decimal

from django.contrib.auth import get_user_model
from django.test import Client, TestCase, override_settings
from django.urls import reverse

from adhesions.models import Adhesion, StatutPaiement
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
        self.assertEqual(self.adhesion.helloasso_webhook_id, "webhook-1")
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
            Adhesion.objects.filter(helloasso_webhook_id="webhook-1").count(),
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
