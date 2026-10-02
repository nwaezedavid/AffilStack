<?php

namespace App\Services\ApiDocs;

use Illuminate\Support\Str;

/**
 * Ready-to-paste code for one endpoint from OpenApiReference, in every
 * language the API & Integrations page offers — built from the endpoint's
 * own example request so samples always match the spec, and pointed at
 * the user's own base URL.
 */
class CodeSampleGenerator
{
    public const TOKEN_PLACEHOLDER = 'YOUR_API_TOKEN';

    /**
     * @var array<string, string>
     */
    public const LANGUAGES = [
        'curl' => 'cURL',
        'javascript' => 'JavaScript',
        'node_sdk' => 'Node SDK',
        'python' => 'Python',
        'python_sdk' => 'Python SDK',
        'php' => 'PHP',
    ];

    /**
     * JS SDK method + how its arguments are passed, per operationId.
     * 'body' passes the request body object, 'path' the path id, 'query'
     * camelCased query options.
     *
     * @var array<string, array{js: string, python: string, args: array<int, string>}>
     */
    protected const SDK_METHODS = [
        'getMe' => ['js' => 'me', 'python' => 'me', 'args' => []],
        'listOffers' => ['js' => 'listOffers', 'python' => 'list_offers', 'args' => ['query']],
        'createOffer' => ['js' => 'createOffer', 'python' => 'create_offer', 'args' => ['body', 'idempotency']],
        'getOffer' => ['js' => 'getOffer', 'python' => 'get_offer', 'args' => ['path']],
        'listGenerations' => ['js' => 'listGenerations', 'python' => 'list_generations', 'args' => ['query']],
        'getGeneration' => ['js' => 'getGeneration', 'python' => 'get_generation', 'args' => ['path']],
        'listCrmContacts' => ['js' => 'listCrmContacts', 'python' => 'list_crm_contacts', 'args' => ['query']],
        'createCrmContact' => ['js' => 'createCrmContact', 'python' => 'create_crm_contact', 'args' => ['body', 'idempotency']],
        'bulkCreateCrmContacts' => ['js' => 'bulkCreateCrmContacts', 'python' => 'bulk_create_crm_contacts', 'args' => ['contacts']],
        'getCrmContact' => ['js' => 'getCrmContact', 'python' => 'get_crm_contact', 'args' => ['path']],
        'updateCrmContact' => ['js' => 'updateCrmContact', 'python' => 'update_crm_contact', 'args' => ['path', 'body']],
        'getReferralsSummary' => ['js' => 'getReferralsSummary', 'python' => 'get_referrals_summary', 'args' => []],
    ];

    public function __construct(protected string $baseUrl, protected string $token = self::TOKEN_PLACEHOLDER) {}

    /**
     * @param  array<string, mixed>  $endpoint
     * @return array<string, string>
     */
    public function forEndpoint(array $endpoint): array
    {
        return [
            'curl' => $this->curl($endpoint),
            'javascript' => $this->javascript($endpoint),
            'node_sdk' => $this->nodeSdk($endpoint),
            'python' => $this->python($endpoint),
            'python_sdk' => $this->pythonSdk($endpoint),
            'php' => $this->php($endpoint),
        ];
    }

    public function hasSdkMethod(string $operationId): bool
    {
        return isset(self::SDK_METHODS[$operationId]);
    }

    /**
     * @param  array<string, mixed>  $endpoint
     */
    protected function url(array $endpoint, bool $withQuery = true): string
    {
        $url = rtrim($this->baseUrl, '/').$endpoint['example_path'];

        return $withQuery && $endpoint['example_query'] ? $url.'?'.http_build_query($endpoint['example_query']) : $url;
    }

    protected function idempotencyKey(): string
    {
        return 'sync-run-1042';
    }

    protected function json(mixed $value, int $indent = 0): string
    {
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $indent ? str_replace("\n", "\n".str_repeat(' ', $indent), $json) : $json;
    }

    /**
     * @param  array<string, mixed>  $endpoint
     */
    public function curl(array $endpoint): string
    {
        $lines = ['curl -X '.$endpoint['method'].' "'.$this->url($endpoint).'"'];
        $lines[] = '  -H "Authorization: Bearer '.$this->token.'"';
        $lines[] = '  -H "Accept: application/json"';

        if ($endpoint['body_example'] !== null) {
            $lines[] = '  -H "Content-Type: application/json"';
        }

        if ($endpoint['idempotent']) {
            $lines[] = '  -H "Idempotency-Key: '.$this->idempotencyKey().'"';
        }

        if ($endpoint['body_example'] !== null) {
            $lines[] = "  -d '".str_replace("'", "'\\''", $this->json($endpoint['body_example'], 2))."'";
        }

        return implode(" \\\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $endpoint
     */
    public function javascript(array $endpoint): string
    {
        $headers = ["    Authorization: 'Bearer {$this->token}',", "    Accept: 'application/json',"];

        if ($endpoint['body_example'] !== null) {
            $headers[] = "    'Content-Type': 'application/json',";
        }

        if ($endpoint['idempotent']) {
            $headers[] = "    'Idempotency-Key': '{$this->idempotencyKey()}',";
        }

        $code = "const response = await fetch('{$this->url($endpoint)}', {\n";
        $code .= "  method: '{$endpoint['method']}',\n";
        $code .= "  headers: {\n".implode("\n", $headers)."\n  },\n";

        if ($endpoint['body_example'] !== null) {
            $code .= '  body: JSON.stringify('.$this->json($endpoint['body_example'], 2)."),\n";
        }

        $code .= "});\n\nconst data = await response.json();\nif (!response.ok) {\n  throw new Error(data.message);\n}\nconsole.log(data);";

        return "// Server-side only (Node 18+, Deno, Cloudflare Workers…) — never ship your token to a browser.\n// In Node, save as example.mjs and run: node example.mjs\n".$code;
    }

    /**
     * @param  array<string, mixed>  $endpoint
     */
    public function nodeSdk(array $endpoint): string
    {
        $setup = "// Download affilstack.js from the API hub (Code tab), next to this file.\nconst { AffilStackClient } = require('./affilstack');\n\n"
            ."const client = new AffilStackClient(process.env.AFFILSTACK_TOKEN, {\n  baseUrl: '{$this->baseUrl}',\n});\n\n";

        $method = self::SDK_METHODS[$endpoint['id']] ?? null;

        if (! $method) {
            return $setup.'// '.$endpoint['method'].' '.$endpoint['path']." isn't wrapped by the SDK yet — use fetch (see the JavaScript tab).";
        }

        $args = [];

        foreach ($method['args'] as $arg) {
            $args[] = match ($arg) {
                'path' => '42',
                'body' => $this->json($endpoint['body_example']),
                'contacts' => $this->json($endpoint['body_example']['contacts'] ?? []),
                'query' => $endpoint['example_query']
                    ? '{ '.collect($endpoint['example_query'])->map(fn ($v, $k) => Str::camel($k).': '.json_encode($v))->implode(', ').' }'
                    : null,
                'idempotency' => "{ idempotencyKey: '{$this->idempotencyKey()}' }",
                default => null,
            };
        }

        $call = 'client.'.$method['js'].'('.implode(', ', array_filter($args, fn ($a) => $a !== null)).')';

        return $setup."async function main() {\n  const result = await ".str_replace("\n", "\n  ", $call).";\n  console.log(result);\n}\n\nmain().catch(console.error);";
    }

    /**
     * @param  array<string, mixed>  $endpoint
     */
    public function python(array $endpoint): string
    {
        $headers = '{"Authorization": "Bearer '.$this->token.'", "Accept": "application/json"';
        $headers .= $endpoint['idempotent'] ? ', "Idempotency-Key": "'.$this->idempotencyKey().'"}' : '}';

        $code = "import requests\n\nresponse = requests.".strtolower($endpoint['method'])."(\n";
        $code .= '    "'.$this->url($endpoint, withQuery: false)."\",\n";
        $code .= "    headers={$headers},\n";

        if ($endpoint['example_query']) {
            $code .= '    params='.$this->pythonLiteral($endpoint['example_query'], 4).",\n";
        }

        if ($endpoint['body_example'] !== null) {
            $code .= '    json='.$this->pythonLiteral($endpoint['body_example'], 4).",\n";
        }

        return $code."    timeout=30,\n)\nresponse.raise_for_status()\nprint(response.json())";
    }

    /**
     * @param  array<string, mixed>  $endpoint
     */
    public function pythonSdk(array $endpoint): string
    {
        $setup = "# Download affilstack.py from the API hub (Code tab), next to this file.\n# Needs: pip install requests\nfrom affilstack import AffilStackClient\n\n"
            ."client = AffilStackClient(\"{$this->token}\", base_url=\"{$this->baseUrl}\")\n\n";

        $method = self::SDK_METHODS[$endpoint['id']] ?? null;

        if (! $method) {
            return $setup.'# '.$endpoint['method'].' '.$endpoint['path']." isn't wrapped by the SDK yet — use requests (see the Python tab).";
        }

        $args = [];

        foreach ($method['args'] as $arg) {
            match ($arg) {
                'path' => $args[] = '42',
                'body' => array_push($args, ...collect($endpoint['body_example'])->map(fn ($v, $k) => $k.'='.$this->pythonLiteral($v))->values()->all()),
                'contacts' => $args[] = $this->pythonLiteral($endpoint['body_example']['contacts'] ?? []),
                'query' => array_push($args, ...collect($endpoint['example_query'])->map(fn ($v, $k) => $k.'='.$this->pythonLiteral($v))->values()->all()),
                'idempotency' => $args[] = 'idempotency_key="'.$this->idempotencyKey().'"',
                default => null,
            };
        }

        $call = 'client.'.$method['python'];
        $joined = implode(', ', $args);

        if (strlen($joined) > 60 && ! str_contains($joined, "\n")) {
            $joined = "\n    ".implode(",\n    ", $args).",\n";
        }

        return $setup.'result = '.$call.'('.$joined.")\nprint(result)";
    }

    /**
     * @param  array<string, mixed>  $endpoint
     */
    public function php(array $endpoint): string
    {
        $headers = ["        'Authorization: Bearer {$this->token}',", "        'Accept: application/json',"];

        if ($endpoint['body_example'] !== null) {
            $headers[] = "        'Content-Type: application/json',";
        }

        if ($endpoint['idempotent']) {
            $headers[] = "        'Idempotency-Key: {$this->idempotencyKey()}',";
        }

        $code = "<?php\n\n\$ch = curl_init('{$this->url($endpoint)}');\n\ncurl_setopt_array(\$ch, [\n";
        $code .= "    CURLOPT_CUSTOMREQUEST => '{$endpoint['method']}',\n    CURLOPT_RETURNTRANSFER => true,\n    CURLOPT_TIMEOUT => 30,\n";
        $code .= "    CURLOPT_HTTPHEADER => [\n".implode("\n", $headers)."\n    ],\n";

        if ($endpoint['body_example'] !== null) {
            $code .= '    CURLOPT_POSTFIELDS => json_encode('.$this->phpLiteral($endpoint['body_example'], 4)."),\n";
        }

        return $code."]);\n\n\$data = json_decode(curl_exec(\$ch), true);\n\$status = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);\ncurl_close(\$ch);\n\nif (\$status >= 400) {\n    throw new RuntimeException(\$data['message'] ?? \"AffilStack API error {\$status}\");\n}\n\nprint_r(\$data);";
    }

    /**
     * Renders a JSON-like value as a Python literal (True/False/None).
     */
    public function pythonLiteral(mixed $value, int $indent = 0): string
    {
        $pad = str_repeat(' ', $indent);

        if (is_array($value) && array_is_list($value)) {
            if ($value === []) {
                return '[]';
            }

            return "[\n".collect($value)->map(fn ($v) => $pad.'    '.$this->pythonLiteral($v, $indent + 4))->implode(",\n").",\n{$pad}]";
        }

        if (is_array($value) || is_object($value)) {
            $value = (array) $value;

            if ($value === []) {
                return '{}';
            }

            return "{\n".collect($value)->map(fn ($v, $k) => $pad.'    '.json_encode((string) $k).': '.$this->pythonLiteral($v, $indent + 4))->implode(",\n").",\n{$pad}}";
        }

        return match (true) {
            $value === true => 'True',
            $value === false => 'False',
            $value === null => 'None',
            default => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };
    }

    /**
     * Renders a JSON-like value as a PHP short-array literal.
     */
    public function phpLiteral(mixed $value, int $indent = 0): string
    {
        $pad = str_repeat(' ', $indent);

        if (is_array($value) || is_object($value)) {
            $value = (array) $value;

            if ($value === []) {
                return '[]';
            }

            $isList = array_is_list($value);

            return "[\n".collect($value)->map(fn ($v, $k) => $pad.'    '.($isList ? '' : var_export((string) $k, true).' => ').$this->phpLiteral($v, $indent + 4))->implode(",\n").",\n{$pad}]";
        }

        return $value === null ? 'null' : var_export($value, true);
    }
}
