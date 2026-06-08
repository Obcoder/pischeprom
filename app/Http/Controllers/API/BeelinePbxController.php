<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Telephony\BeelinePbxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleXMLElement;

class BeelinePbxController extends Controller
{
    public function __invoke(Request $request, BeelinePbxService $service): JsonResponse
    {
        if (!$this->hasValidToken($request)) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $result = $service->handle($this->payload($request));

        return response()->json($result['data'], $result['status']);
    }

    protected function hasValidToken(Request $request): bool
    {
        $expected = config('services.beeline_pbx.crm_token')
            ?: config('services.beeline_pbx.api_token');

        if (!$expected) {
            return false;
        }

        $actual = $request->input('crm_token')
            ?: $request->input('token')
            ?: $request->bearerToken()
            ?: $request->header('X-Beeline-Token')
            ?: $request->header('X-MPBX-API-AUTH-TOKEN');

        return is_string($actual) && hash_equals((string) $expected, $actual);
    }

    protected function payload(Request $request): array
    {
        $payload = $request->all();
        $raw = trim($request->getContent());

        if ($raw === '' || !str_starts_with($raw, '<')) {
            return $payload;
        }

        $xmlPayload = $this->xmlPayload($raw);

        if ($xmlPayload === []) {
            return $payload;
        }

        return array_replace_recursive($payload, $xmlPayload, [
            'raw_xml' => Str::limit($raw, 20000, ''),
        ]);
    }

    protected function xmlPayload(string $raw): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw, SimpleXMLElement::class, LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$xml instanceof SimpleXMLElement) {
            return [];
        }

        $tree = [$xml->getName() => $this->xmlNodeToArray($xml)];

        return array_replace_recursive($tree, $this->flattenXml($tree));
    }

    protected function xmlNodeToArray(SimpleXMLElement $node): array|string|null
    {
        $result = [];

        foreach ($this->xmlAttributes($node) as $name => $value) {
            $result[$name] = $value;
        }

        foreach ($this->xmlChildren($node) as $name => $child) {
            $value = $this->xmlNodeToArray($child);

            if (array_key_exists($name, $result)) {
                $result[$name] = is_array($result[$name]) && array_is_list($result[$name])
                    ? [...$result[$name], $value]
                    : [$result[$name], $value];
                continue;
            }

            $result[$name] = $value;
        }

        if ($result !== []) {
            return $result;
        }

        $value = trim((string) $node);

        return $value === '' ? null : $value;
    }

    /**
     * SimpleXML only exposes one namespace at a time; collect all children by local name.
     */
    protected function xmlChildren(SimpleXMLElement $node): array
    {
        $children = [];
        $namespaces = [null, ...array_values($node->getNamespaces(true))];

        foreach ($namespaces as $namespace) {
            foreach ($node->children($namespace) as $name => $child) {
                $children[$name] = $child;
            }
        }

        return $children;
    }

    protected function xmlAttributes(SimpleXMLElement $node): array
    {
        $attributes = [];
        $namespaces = [null, ...array_values($node->getNamespaces(true))];

        foreach ($namespaces as $namespace) {
            foreach ($node->attributes($namespace) as $name => $value) {
                $attributes[$name] = trim((string) $value);
            }
        }

        return $attributes;
    }

    protected function flattenXml(array $data): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            if (is_array($value) && !array_is_list($value)) {
                $flat = array_replace_recursive($flat, $this->flattenXml($value));
                continue;
            }

            if (!array_key_exists($key, $flat)) {
                $flat[$key] = $value;
            }
        }

        return $flat;
    }
}
