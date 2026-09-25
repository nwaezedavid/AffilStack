"""A small, hand-built wrapper around AffilStack's /v1/* endpoints,
described in resources/openapi/openapi.yaml — kept in sync with that spec
by hand rather than generated, mirroring the JS SDK (sdks/js) method for
method so the two never drift into different shapes.
"""

from __future__ import annotations

from typing import Any, Iterable, Mapping, Optional

import requests

DEFAULT_BASE_URL = "https://app.affilstack.example/api/v1"


class AffilStackApiError(Exception):
    """Raised for any non-2xx response. ``status`` and ``body`` carry the
    HTTP status code and parsed JSON body (when present) for callers that
    want to branch on them, e.g. a 402 meaning "top up the API wallet".
    """

    def __init__(self, message: str, status: int, body: Any = None):
        super().__init__(message)
        self.status = status
        self.body = body


class AffilStackClient:
    """
    >>> client = AffilStackClient("aff_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx")
    >>> me = client.me()
    """

    def __init__(self, api_key: str, base_url: str = DEFAULT_BASE_URL, session: Optional[requests.Session] = None):
        if not api_key:
            raise ValueError("AffilStackClient requires an API key.")

        self.api_key = api_key
        self.base_url = base_url.rstrip("/")
        self.session = session or requests.Session()

    def _request(
        self,
        method: str,
        path: str,
        *,
        query: Optional[Mapping[str, Any]] = None,
        json: Optional[Mapping[str, Any]] = None,
        idempotency_key: Optional[str] = None,
    ) -> Any:
        headers = {"Authorization": f"Bearer {self.api_key}", "Accept": "application/json"}
        if idempotency_key:
            headers["Idempotency-Key"] = idempotency_key

        response = self.session.request(
            method,
            self.base_url + path,
            params={k: v for k, v in (query or {}).items() if v is not None},
            json=json,
            headers=headers,
            timeout=30,
        )

        data = None
        if response.content:
            try:
                data = response.json()
            except ValueError:
                data = None

        if not response.ok:
            message = (data or {}).get("message") if isinstance(data, dict) else None
            raise AffilStackApiError(
                message or f"AffilStack API request failed with status {response.status_code}",
                response.status_code,
                data,
            )

        return data

    # --- Account ---------------------------------------------------------

    def me(self) -> dict:
        return self._request("GET", "/me")

    # --- Offers ------------------------------------------------------------

    def list_offers(self, per_page: Optional[int] = None) -> dict:
        return self._request("GET", "/offers", query={"per_page": per_page})

    def get_offer(self, offer_id: int) -> dict:
        return self._request("GET", f"/offers/{offer_id}")

    def create_offer(
        self,
        product_name: str,
        product_url: str,
        affiliate_network: str,
        affiliate_link: Optional[str] = None,
        idempotency_key: Optional[str] = None,
    ) -> dict:
        body = {
            "product_name": product_name,
            "product_url": product_url,
            "affiliate_network": affiliate_network,
        }
        if affiliate_link:
            body["affiliate_link"] = affiliate_link

        return self._request("POST", "/offers", json=body, idempotency_key=idempotency_key)

    # --- Generations -----------------------------------------------------

    def list_generations(
        self, offer_id: Optional[int] = None, module: Optional[str] = None, per_page: Optional[int] = None
    ) -> dict:
        return self._request(
            "GET", "/generations", query={"offer_id": offer_id, "module": module, "per_page": per_page}
        )

    def get_generation(self, generation_id: int) -> dict:
        return self._request("GET", f"/generations/{generation_id}")

    # --- CRM contacts ------------------------------------------------------

    def list_crm_contacts(self, per_page: Optional[int] = None) -> dict:
        return self._request("GET", "/crm-contacts", query={"per_page": per_page})

    def get_crm_contact(self, contact_id: int) -> dict:
        return self._request("GET", f"/crm-contacts/{contact_id}")

    def create_crm_contact(self, idempotency_key: Optional[str] = None, **fields: Any) -> dict:
        return self._request("POST", "/crm-contacts", json=fields, idempotency_key=idempotency_key)

    def bulk_create_crm_contacts(self, contacts: Iterable[Mapping[str, Any]]) -> dict:
        """Up to 100 contacts; see the returned ``failed`` list (by index)
        for any items that didn't validate or hit the plan's contact limit.
        """
        return self._request("POST", "/crm-contacts/bulk", json={"contacts": list(contacts)})

    def update_crm_contact(self, contact_id: int, **changes: Any) -> dict:
        return self._request("PATCH", f"/crm-contacts/{contact_id}", json=changes)

    # --- Referrals -----------------------------------------------------------

    def get_referrals_summary(self) -> dict:
        return self._request("GET", "/referrals/summary")
