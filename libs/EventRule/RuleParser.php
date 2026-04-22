<?php

namespace F3CMS\EventRule;

class RuleParser
{
    public static function parse($payload)
    {
        return self::parseNode($payload, '$');
    }

    private static function parseNode($node, $path)
    {
        if (isset($node['rules'])) {
            $parsedRules = [];
            foreach ($node['rules'] as $index => $childNode) {
                $parsedRules[] = self::parseNode($childNode, $path . '.rules[' . $index . ']');
            }

            return [
                'node_kind' => 'group',
                'path' => $path,
                'operator' => strtoupper(trim((string) $node['operator'])),
                'rules' => $parsedRules,
            ];
        }

        $parsedLeaf = [
            'node_kind' => 'leaf',
            'path' => $path,
            'type' => strtoupper(trim((string) $node['type'])),
        ];

        if (isset($node['target'])) {
            $parsedLeaf['target'] = trim((string) $node['target']);
        }

        if (isset($node['operator'])) {
            $parsedLeaf['operator'] = trim((string) $node['operator']);
        }

        if (array_key_exists('value', $node)) {
            $parsedLeaf['value'] = $node['value'];
        }

		if (array_key_exists('row_id', $node)) {
			$parsedLeaf['row_id'] = (int) $node['row_id'];
		}

        return $parsedLeaf;
    }
}