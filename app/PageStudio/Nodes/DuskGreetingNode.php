<?php

namespace App\PageStudio\Nodes;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Lab fixture · referenced by NodeEditorTest's code-defined-node test.
 * Mirrors the package's example greeting node.
 */
class DuskGreetingNode extends NodeType
{
    public static function key(): string   { return 'custom.dusk_greeting'; }
    public static function label(): string { return 'Dusk greeting'; }
    public static function icon(): string  { return '✦'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['who' => ['label' => 'Who', 'type' => 'string']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Out', 'type' => 'string']]; }

    public static function settings(): array
    {
        return ['prefix' => ['kind' => 'text', 'label' => 'Prefix', 'default' => 'Hi']];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $prefix = (string) ($settings['prefix'] ?? 'Hi');
        $who    = (string) ($inputs['who'] ?? 'friend');
        return ['value' => "{$prefix}, {$who}!"];
    }
}
