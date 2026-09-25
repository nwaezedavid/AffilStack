# AffilStack SDKs

Official client libraries for the [AffilStack API](../resources/openapi/openapi.yaml)
(API roadmap item #8). Both are hand-built to match the spec exactly (method for
method, field for field) rather than pipeline-generated, and both are fully tested —
see each package's own README for usage.

- [`js/`](./js) — `affilstack-sdk`, zero dependencies (uses the platform `fetch`).
- [`python/`](./python) — `affilstack`, depends only on `requests`.

Neither is published to npm/PyPI yet. Both are ready to publish as soon as AffilStack
wants to (`npm publish` / `python -m build && twine upload`) — that's an account/ownership
decision for AffilStack to make, not something built in this repo.
