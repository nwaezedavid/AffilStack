<?php

namespace App\Services\ApiDocs;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Turns the hand-authored OpenAPI spec (resources/openapi/openapi.yaml —
 * the same file OpenApiSpecController serves) into the endpoint reference
 * shown on the dashboard's API & Integrations page, so the page can never
 * drift from the spec: every path/method in the spec becomes one entry,
 * with $refs resolved, an example request/response built from its
 * schemas, and the scope, wallet cost and idempotency support worked out
 * from the live route table and config('api_billing').
 */
class OpenApiReference
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $spec = null;

    /**
     * Request bodies where a hand-picked example reads better than one
     * generated from the schema (a PATCH should show a partial update).
     *
     * @var array<string, array<string, mixed>>
     */
    protected const BODY_EXAMPLES = [
        'updateCrmContact' => ['status' => 'qualified', 'notes' => 'Booked a demo call for Friday.'],
    ];

    /**
     * Plain-language notes for endpoints with something worth knowing.
     *
     * @var array<string, string>
     */
    protected const NOTES = [
        'createOffer' => 'Starts the same AI offer research as the dashboard. On a live token it also uses your plan\'s research credits; on a sandbox token it returns canned research instantly and costs nothing.',
        'bulkCreateCrmContacts' => 'Partial success: every valid contact is created even if others fail. Retry only the items listed in "failed" (by index).',
        'listGenerations' => 'Filter by offer_id and module (e.g. blog_article, linkedin_post, youtube_script) to pull exactly the content you need — perfect for sending new blog articles to WordPress.',
        'createZapierSubscription' => 'Account owners only. The new subscription appears on the Webhooks tab, where you can pause or remove it.',
        'getMe' => 'The best first call: it confirms your token works and tells you its scope and whether it is a sandbox token.',
    ];

    public function __construct(protected Router $router) {}

    /**
     * @return array<string, mixed>
     */
    public function spec(): array
    {
        return $this->spec ??= Yaml::parseFile(resource_path('openapi/openapi.yaml'));
    }

    /**
     * @return array<int, array{id: string, anchor: string, method: string, path: string, spec_path: string, tag: string, summary: string, description: ?string, note: ?string, path_params: array<int, array<string, mixed>>, query_params: array<int, array<string, mixed>>, body_fields: array<int, array<string, mixed>>, body_example: mixed, responses: array<int, array{status: string, description: string}>, success_status: int, response_example: mixed, scope: string, cost_cents: int, route_name: ?string, idempotent: bool, safe: bool, example_path: string, example_query: array<string, mixed>}>
     */
    public function endpoints(): array
    {
        $endpoints = [];

        foreach ($this->spec()['paths'] ?? [] as $specPath => $operations) {
            foreach ($operations as $method => $operation) {
                if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }

                $endpoints[] = $this->buildEndpoint((string) $specPath, strtoupper($method), $operation);
            }
        }

        return $endpoints;
    }

    /**
     * Endpoints grouped by their spec tag, in the spec's tag order.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function grouped(): array
    {
        $order = collect($this->spec()['tags'] ?? [])->pluck('name')->flip();

        return collect($this->endpoints())
            ->groupBy('tag')
            ->sortBy(fn ($items, $tag) => $order[$tag] ?? 99)
            ->map(fn ($items) => $items->values()->all())
            ->all();
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    protected function buildEndpoint(string $specPath, string $method, array $operation): array
    {
        $parameters = collect($operation['parameters'] ?? [])->map(fn ($p) => $this->resolve($p));
        $route = $this->findRoute($method, $specPath);
        $routeName = $route?->getName();
        $id = (string) ($operation['operationId'] ?? Str::camel($method.' '.$specPath));

        $bodySchema = $this->resolve($operation['requestBody']['content']['application/json']['schema'] ?? null);
        $bodySchema = $bodySchema ? $this->flatten($bodySchema) : null;

        $successStatus = collect(array_keys($operation['responses'] ?? []))->map(fn ($s) => (int) $s)->first(fn ($s) => $s >= 200 && $s < 300) ?? 200;
        $successSchema = $this->resolve($this->resolve($operation['responses'][(string) $successStatus] ?? [])['content']['application/json']['schema'] ?? null);

        $pathParams = $parameters->where('in', 'path')->map(fn ($p) => $this->describeParameter($p))->values()->all();
        $queryParams = $parameters->where('in', 'query')->map(fn ($p) => $this->describeParameter($p))->values()->all();

        $examplePath = preg_replace_callback('/\{(\w+)\}/', fn () => '42', $this->relativePath($specPath));

        return [
            'id' => $id,
            'anchor' => 'endpoint-'.Str::kebab($id),
            'method' => $method,
            'path' => $this->relativePath($specPath),
            'spec_path' => $specPath,
            'tag' => (string) ($operation['tags'][0] ?? 'Other'),
            'summary' => (string) ($operation['summary'] ?? ''),
            'description' => isset($operation['description']) ? trim((string) $operation['description']) : null,
            'note' => self::NOTES[$id] ?? null,
            'path_params' => $pathParams,
            'query_params' => $queryParams,
            'body_fields' => $bodySchema ? $this->describeFields($bodySchema) : [],
            'body_example' => $bodySchema ? (self::BODY_EXAMPLES[$id] ?? $this->example($bodySchema)) : null,
            'responses' => collect($operation['responses'] ?? [])
                ->map(fn ($response, $status) => ['status' => (string) $status, 'description' => (string) ($this->resolve($response)['description'] ?? '')])
                ->values()->all(),
            'success_status' => $successStatus,
            'response_example' => $successSchema ? $this->example($this->flatten($successSchema)) : null,
            'scope' => in_array($method, ['GET', 'HEAD'], true) ? 'read' : 'write',
            'cost_cents' => (int) (config('api_billing.costs')[$routeName] ?? config('api_billing.default_cost_cents', 0)),
            'route_name' => $routeName,
            'idempotent' => $parameters->contains(fn ($p) => strcasecmp((string) ($p['name'] ?? ''), 'Idempotency-Key') === 0),
            'safe' => $method === 'GET',
            'example_path' => $examplePath,
            'example_query' => collect($queryParams)->mapWithKeys(fn ($p) => [$p['name'] => $p['example']])->all(),
        ];
    }

    /**
     * The spec's paths start at /api (its "servers" entry) — the page shows
     * them relative to the user's /api/v1 base URL.
     */
    protected function relativePath(string $specPath): string
    {
        return Str::startsWith($specPath, '/v1/') ? Str::after($specPath, '/v1') : $specPath;
    }

    protected function findRoute(string $method, string $specPath): ?Route
    {
        $uri = 'api'.$specPath;

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parameter
     * @return array{name: string, type: string, required: bool, description: ?string, example: mixed}
     */
    protected function describeParameter(array $parameter): array
    {
        $schema = $parameter['schema'] ?? [];

        return [
            'name' => (string) $parameter['name'],
            'type' => (string) ($schema['type'] ?? 'string'),
            'required' => (bool) ($parameter['required'] ?? false),
            'description' => $parameter['description'] ?? (isset($schema['maximum']) ? "Max {$schema['maximum']}" : null),
            'example' => match ($parameter['name']) {
                'per_page' => 25,
                'module' => 'blog_article',
                default => ($schema['type'] ?? null) === 'integer' ? 42 : 'example',
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, array{name: string, type: string, required: bool, description: ?string, enum: ?array<int, mixed>, max: ?int}>
     */
    protected function describeFields(array $schema): array
    {
        $required = $schema['required'] ?? [];

        return collect($schema['properties'] ?? [])->map(function ($property, $name) use ($required) {
            $property = $this->flatten($this->resolve($property));
            $type = (string) ($property['type'] ?? 'object');

            if ($type === 'array') {
                $items = $this->flatten($this->resolve($property['items'] ?? []));
                $type = 'array of '.($items['type'] ?? 'object').'s';
            }

            if (isset($property['format'])) {
                $type .= " ({$property['format']})";
            }

            return [
                'name' => (string) $name,
                'type' => $type,
                'required' => in_array($name, $required, true),
                'description' => $property['description'] ?? null,
                'enum' => $property['enum'] ?? null,
                'max' => $property['maxLength'] ?? $property['maxItems'] ?? null,
            ];
        })->values()->all();
    }

    /**
     * Follows a local "$ref" (and refs to refs) to the referenced node.
     */
    protected function resolve(mixed $node): mixed
    {
        $depth = 0;

        while (is_array($node) && isset($node['$ref']) && $depth++ < 10) {
            $segments = explode('/', ltrim(Str::after($node['$ref'], '#'), '/'));
            $node = data_get($this->spec(), implode('.', $segments));
        }

        return $node;
    }

    /**
     * Merges an "allOf" composition into one object schema.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function flatten(array $schema): array
    {
        if (! isset($schema['allOf'])) {
            return $schema;
        }

        $merged = ['type' => 'object', 'properties' => [], 'required' => []];

        foreach ($schema['allOf'] as $part) {
            $part = $this->flatten($this->resolve($part));
            $merged['properties'] = [...$merged['properties'], ...($part['properties'] ?? [])];
            $merged['required'] = [...$merged['required'], ...($part['required'] ?? [])];
        }

        return $merged;
    }

    /**
     * A realistic example value for a schema, using the property name to
     * pick something a reader recognises over a bare "string".
     */
    public function example(mixed $schema, ?string $name = null, int $depth = 0): mixed
    {
        $schema = is_array($schema) ? $this->flatten($this->resolve($schema)) : [];

        if ($depth > 6) {
            return null;
        }

        if (isset($schema['example'])) {
            return $schema['example'];
        }

        $named = $this->exampleForName($name);

        if ($named !== null) {
            return $named;
        }

        if (isset($schema['enum'])) {
            return $schema['enum'][0];
        }

        $type = $schema['type'] ?? (isset($schema['properties']) ? 'object' : null);

        return match ($type) {
            'object' => isset($schema['properties'])
                ? collect($schema['properties'])->map(fn ($property, $key) => $this->example($property, (string) $key, $depth + 1))->all()
                : (object) [],
            'array' => [$this->example($schema['items'] ?? [], Str::singular((string) $name), $depth + 1)],
            'integer' => Str::endsWith((string) $name, '_cents') ? 2500 : (Str::endsWith((string) $name, ['id', '_id']) ? 42 : 1),
            'number' => 1.5,
            'boolean' => false,
            'string' => match ($schema['format'] ?? null) {
                'email' => 'jane@acme.example',
                'uri' => 'https://acme.example',
                'date-time' => '2026-10-01T09:30:00+00:00',
                default => 'string',
            },
            default => null,
        };
    }

    protected function exampleForName(?string $name): mixed
    {
        return match ($name) {
            'id' => 42,
            'product_name' => 'Acme Widget',
            'product_url' => 'https://acme.example/widget',
            'affiliate_network' => 'ShareASale',
            'affiliate_link' => 'https://shareasale.com/r.cfm?b=123&u=456&m=789',
            'name' => 'Jane Doe',
            'company' => 'Acme Inc.',
            'title' => 'Head of Growth',
            'email' => 'jane@acme.example',
            'phone' => '+234 803 000 0000',
            'website' => 'https://acme.example',
            'location' => 'Lagos, Nigeria',
            'notes' => 'Met at the Lagos marketing meetup.',
            'source' => 'api',
            'module' => 'blog_article',
            'output' => 'Your generated article, script or post text…',
            'output_meta' => ['title' => '7 Ways Acme Widget Saves Small Teams Time'],
            'input' => ['offer_id' => 42],
            'ideal_customer_summary' => 'Small e-commerce teams who manage stock by hand.',
            'where_to_find' => 'Shopify community forums, r/ecommerce, LinkedIn groups for D2C founders.',
            'recommended_channel' => 'LinkedIn',
            'recommended_angle' => 'Save four hours a week on stock counts.',
            'research_data' => ['pain_points' => ['Manual stock counts', 'Overselling']],
            'target_url' => 'https://hooks.zapier.com/hooks/catch/123456/abcdef/',
            'event' => 'crm_contact.created',
            'referral_code' => 'JANE42',
            'referral_link' => 'https://affilstack.com/?ref=JANE42',
            'events_by_status' => ['pending' => 2, 'approved' => 5],
            'errors' => ['email' => ['The email field must be a valid email address.']],
            'message' => 'Human-readable explanation of what went wrong.',
            'plan' => 'Pro',
            'scope' => 'full',
            default => null,
        };
    }
}
