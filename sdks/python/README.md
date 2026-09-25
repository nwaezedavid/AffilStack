# affilstack

Official Python client for the [AffilStack API](../../resources/openapi/openapi.yaml).

Not yet published to PyPI — this package is ready to publish (`python -m build && twine
upload`) whenever AffilStack decides to, or install it directly from this path in the
meantime (`pip install -e sdks/python`).

## Install (once published)

```bash
pip install affilstack
```

## Usage

```python
from affilstack import AffilStackClient, AffilStackApiError

client = AffilStackClient(
    "aff_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    base_url="https://your-affilstack-domain.example/api/v1",
)

me = client.me()
print(f"Wallet balance: ${me['api_wallet_balance_cents'] / 100:.2f}")

try:
    offer = client.create_offer(
        product_name="Acme Widget",
        product_url="https://acme.example/widget",
        affiliate_network="ShareASale",
        idempotency_key="acme-widget-2026-09-25",  # safe to retry with the same key
    )
    print("Created offer", offer["id"])
except AffilStackApiError as err:
    if err.status == 402:
        print("Not enough API wallet balance — top up from the dashboard.")
    else:
        raise

result = client.bulk_create_crm_contacts([
    {"name": "Jane Doe", "email": "jane@example.com"},
    {"name": "John Doe", "email": "john@example.com"},
])
print(f"{len(result['created'])} contacts created, {len(result['failed'])} failed")
```

## Sandbox mode

Pass a token created in sandbox mode (the API Access page's "Sandbox token" checkbox) to
develop against fake data with zero cost — see the `token.is_sandbox` field on `me()`.

## Testing this package

```bash
pip install -e ".[dev]"
pytest
```
