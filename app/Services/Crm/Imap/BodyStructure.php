<?php

namespace App\Services\Crm\Imap;

/**
 * Reads a parsed IMAP BODYSTRUCTURE and finds the part worth showing as a
 * reply: the first text/plain leaf (DFS order), else the first text/html,
 * never descending into attached messages (message/rfc822).
 */
class BodyStructure
{
    /**
     * @param  array<int, mixed>  $structure
     * @return array{part: string, subtype: string, charset: string|null, encoding: string|null}|null
     */
    public static function textPart(array $structure): ?array
    {
        $leaves = self::textLeaves($structure, '');

        foreach (['plain', 'html'] as $preferred) {
            foreach ($leaves as $leaf) {
                if ($leaf['subtype'] === $preferred) {
                    return $leaf;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $node
     * @return array<int, array{part: string, subtype: string, charset: string|null, encoding: string|null}>
     */
    protected static function textLeaves(array $node, string $prefix): array
    {
        // Multipart: one or more child lists, then the subtype.
        if (isset($node[0]) && is_array($node[0])) {
            $leaves = [];
            $index = 0;

            foreach ($node as $child) {
                if (! is_array($child)) {
                    break;
                }

                $index++;
                $leaves = [...$leaves, ...self::textLeaves($child, $prefix === '' ? (string) $index : $prefix.'.'.$index)];
            }

            return $leaves;
        }

        $type = strtolower((string) ($node[0] ?? ''));
        $subtype = strtolower((string) ($node[1] ?? ''));

        if ($type !== 'text' || ! in_array($subtype, ['plain', 'html'], true)) {
            return [];
        }

        $charset = null;
        $params = $node[2] ?? null;

        if (is_array($params)) {
            for ($i = 0; $i + 1 < count($params); $i += 2) {
                if (strtolower((string) $params[$i]) === 'charset') {
                    $charset = (string) $params[$i + 1];
                }
            }
        }

        return [[
            'part' => $prefix === '' ? '1' : $prefix,
            'subtype' => $subtype,
            'charset' => $charset,
            'encoding' => isset($node[5]) ? strtolower((string) $node[5]) : null,
        ]];
    }
}
