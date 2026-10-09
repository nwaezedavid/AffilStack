<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * The API reference reads resources/openapi/openapi.json because the YAML
 * parser is a dev-only package (absent from the production install). The
 * JSON must always match the published openapi.yaml — regenerate it with:
 *   php -r 'require "vendor/autoload.php"; file_put_contents("resources/openapi/openapi.json", json_encode(Symfony\Component\Yaml\Yaml::parseFile("resources/openapi/openapi.yaml"), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");'
 */
class OpenApiSpecTest extends TestCase
{
    public function test_the_json_spec_matches_the_published_yaml(): void
    {
        $yaml = Yaml::parseFile(resource_path('openapi/openapi.yaml'));
        $json = json_decode((string) file_get_contents(resource_path('openapi/openapi.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertEquals($yaml, $json);
    }
}
