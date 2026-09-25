import json

import pytest
import responses

from affilstack import AffilStackApiError, AffilStackClient

BASE_URL = "https://example.test/api/v1"


@pytest.fixture
def client():
    return AffilStackClient("aff_test123", base_url=BASE_URL)


@responses.activate
def test_me_sends_a_get_with_the_bearer_token(client):
    responses.add(responses.GET, f"{BASE_URL}/me", json={"id": 1, "name": "Jordan"}, status=200)

    result = client.me()

    assert result == {"id": 1, "name": "Jordan"}
    sent = responses.calls[0].request
    assert sent.headers["Authorization"] == "Bearer aff_test123"


@responses.activate
def test_create_offer_sends_a_post_with_a_json_body_and_idempotency_key(client):
    responses.add(responses.POST, f"{BASE_URL}/offers", json={"id": 42, "status": "queued"}, status=201)

    offer = client.create_offer(
        product_name="Widget",
        product_url="https://a.example",
        affiliate_network="ShareASale",
        idempotency_key="key-1",
    )

    assert offer["id"] == 42
    sent = responses.calls[0].request
    assert sent.headers["Idempotency-Key"] == "key-1"
    assert json.loads(sent.body) == {
        "product_name": "Widget",
        "product_url": "https://a.example",
        "affiliate_network": "ShareASale",
    }


@responses.activate
def test_list_offers_forwards_per_page_as_a_query_parameter(client):
    responses.add(responses.GET, f"{BASE_URL}/offers", json={"data": []}, status=200)

    client.list_offers(per_page=10)

    assert responses.calls[0].request.url == f"{BASE_URL}/offers?per_page=10"


@responses.activate
def test_bulk_create_crm_contacts_posts_the_list_under_a_contacts_key(client):
    responses.add(
        responses.POST, f"{BASE_URL}/crm-contacts/bulk", json={"created": [{"id": 1}], "failed": []}, status=200
    )

    result = client.bulk_create_crm_contacts([{"name": "A"}, {"name": "B"}])

    assert len(result["created"]) == 1
    sent = responses.calls[0].request
    assert json.loads(sent.body) == {"contacts": [{"name": "A"}, {"name": "B"}]}


@responses.activate
def test_a_non_2xx_response_raises_affilstack_api_error_with_the_parsed_body(client):
    responses.add(
        responses.POST,
        f"{BASE_URL}/offers",
        json={"message": "Insufficient API wallet balance for this request."},
        status=402,
    )

    with pytest.raises(AffilStackApiError) as exc_info:
        client.create_offer(product_name="A", product_url="https://a.example", affiliate_network="ShareASale")

    assert exc_info.value.status == 402
    assert str(exc_info.value) == "Insufficient API wallet balance for this request."


def test_constructing_without_an_api_key_raises_immediately():
    with pytest.raises(ValueError):
        AffilStackClient("")
