<?php

namespace Androsamp\FilamentResourceLock\Support;

use Filament\Forms\Components\RichEditor\RichContentRenderer;

class AuditDiffRenderer
{
    /**
     * Produces a line-by-line diff for JSON / KeyValue fields.
     *
     * @return array<int, array{type: 'added'|'removed'|'unchanged', line: string}>
     */
    public static function jsonDiff(mixed $old, mixed $new): array
    {
        $old = self::normalizeJson($old);
        $new = self::normalizeJson($new);

        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
        $lines   = [];

        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            if (array_key_exists($key, $old) && ! array_key_exists($key, $new)) {
                $lines[] = ['type' => 'removed', 'line' => '"' . $key . '": "' . $oldVal . '"'];
            } elseif (! array_key_exists($key, $old) && array_key_exists($key, $new)) {
                $lines[] = ['type' => 'added', 'line' => '"' . $key . '": "' . $newVal . '"'];
            } elseif ($oldVal !== $newVal) {
                $lines[] = ['type' => 'removed', 'line' => '"' . $key . '": "' . $oldVal . '"'];
                $lines[] = ['type' => 'added',   'line' => '"' . $key . '": "' . $newVal . '"'];
            } else {
                $lines[] = ['type' => 'unchanged', 'line' => '"' . $key . '": "' . $oldVal . '"'];
            }
        }

        return $lines;
    }

    /**
     * Produces inline word-level diff for plain text / RichEditor content.
     * Returns HTML strings with <mark> tags highlighting changes.
     *
     * @return array{old: string, new: string}
     */
    public static function inlineTextDiff(string $old, string $new): array
    {
        $oldWords = self::tokenize($old);
        $newWords = self::tokenize($new);

        $lcs = self::computeLcs($oldWords, $newWords);

        $oldOut = '';
        $newOut = '';

        $i = 0;
        $j = 0;

        $styleRemoved = 'background:#fecaca;color:#991b1b;text-decoration:line-through;border-radius:3px;padding:0 2px;';
        $styleAdded   = 'background:#bbf7d0;color:#166534;border-radius:3px;padding:0 2px;';

        foreach ($lcs as [$oi, $ni]) {
            while ($i < $oi) {
                $oldOut .= '<span style="' . $styleRemoved . '">' . e($oldWords[$i]) . '</span>';
                $i++;
            }
            while ($j < $ni) {
                $newOut .= '<span style="' . $styleAdded . '">' . e($newWords[$j]) . '</span>';
                $j++;
            }
            $oldOut .= e($oldWords[$i]);
            $newOut .= e($newWords[$j]);
            $i++;
            $j++;
        }

        while ($i < count($oldWords)) {
            $oldOut .= '<span style="' . $styleRemoved . '">' . e($oldWords[$i]) . '</span>';
            $i++;
        }
        while ($j < count($newWords)) {
            $newOut .= '<span style="' . $styleAdded . '">' . e($newWords[$j]) . '</span>';
            $j++;
        }

        return ['old' => $oldOut, 'new' => $newOut];
    }

    public static function formatSelectValue(mixed $value): string
    {
        $normalized = self::normalizePossibleJson($value);

        if ($normalized === null || $normalized === '') {
            return '—';
        }

        if (is_scalar($normalized)) {
            return (string) $normalized;
        }

        if (is_array($normalized)) {
            $labels = self::flattenSelectLabels($normalized);

            return empty($labels) ? '—' : implode(', ', $labels);
        }

        if (is_object($normalized)) {
            return self::formatSelectValue((array) $normalized);
        }

        return '—';
    }

    public static function renderRichContent(mixed $value, array $customBlocks = []): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $normalized = self::normalizePossibleJson($value);

        if (is_array($normalized)) {
            $normalized = self::prepareRichContentJson($normalized, $customBlocks);
            try {
                return RichContentRenderer::make($normalized)->toUnsafeHtml();
            } catch (\Throwable) {
                // Fallback to pretty JSON
                return '<pre style="background:rgba(0,0,0,.35);border-radius:6px;padding:.7em 1em;overflow-x:auto;font-family:monospace;font-size:.82em;margin:.5em 0;"><code>' . e(json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '') . '</code></pre>';
            }
        }

        if (is_string($normalized)) {
            // If it's a string but we know it's supposed to be RichEditor, and it looks like JSON but failed to decode
            if (str_starts_with(trim($normalized), '{') || str_starts_with(trim($normalized), '[')) {
                 return '<pre style="background:rgba(0,0,0,.35);border-radius:6px;padding:.7em 1em;overflow-x:auto;font-family:monospace;font-size:.82em;margin:.5em 0;"><code>' . e($normalized) . '</code></pre>';
            }

            return $normalized;
        }

        return (string) $normalized;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private static function prepareRichContentJson(array $data, array $customBlocks = []): array
    {
        if (isset($data['type']) && $data['type'] === 'customBlock') {
            $id = $data['attrs']['id'] ?? 'Custom Block';
            $config = $data['attrs']['config'] ?? [];

            // 1. Try to use the block's native toPreviewHtml() or toHtml()
            $blockClass = null;
            foreach ($customBlocks as $block) {
                if (is_string($block) && class_exists($block) && is_subclass_of($block, \Filament\Forms\Components\RichEditor\RichContentCustomBlock::class)) {
                    if ($block::getId() === $id) {
                        $blockClass = $block;
                        break;
                    }
                }
            }

            if ($blockClass) {
                $html = null;
                try {
                    $html = $blockClass::toPreviewHtml($config) ?? $blockClass::toHtml($config, []);
                } catch (\Throwable) {}

                if ($html !== null) {
                    return [
                        'type' => 'renderedCustomBlock',
                        'html' => '<div class="fi-fo-rich-editor-custom-block-preview fi-not-prose">' . $html . '</div>',
                    ];
                }
            }

            // 2. Fallback to base64 preview from the snapshot
            $preview = $data['attrs']['preview'] ?? null;
            if ($preview) {
                // The preview might be base64 encoded (as sent from JS Editor) or raw HTML
                $decoded = preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $preview)
                    ? base64_decode($preview, true)
                    : $preview;

                if ($decoded) {
                    return [
                        'type' => 'renderedCustomBlock',
                        'html' => '<div class="fi-fo-rich-editor-custom-block-preview fi-not-prose">' . $decoded . '</div>',
                    ];
                }
            }

            // 3. Ultimate fallback: show configuration JSON
            $html = '<div class="fi-fo-rich-editor-custom-block-heading" style="font-size:.7em;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.35em;">Block: ' . e($id) . '</div>';
            if (! empty($config)) {
                $html .= '<pre style="background:rgba(0,0,0,.35);border-radius:6px;padding:.7em 1em;overflow-x:auto;font-family:monospace;font-size:.82em;margin:.5em 0;"><code>' . e(json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') . '</code></pre>';
            }

            return [
                'type' => 'renderedCustomBlock',
                'html' => $html,
            ];
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::prepareRichContentJson($value, $customBlocks);
            }
        }

        return $data;
    }

    private static function normalizeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private static function normalizePossibleJson(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        // Sometimes JSON is saved as a string that json_decode can parse.
        $decoded = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return $value;
    }

    /**
     * @param  array<int|string, mixed>  $value
     * @return array<int, string>
     */
    private static function flattenSelectLabels(array $value): array
    {
        $labels = [];

        foreach ($value as $item) {
            if (is_scalar($item) || $item === null) {
                if ($item !== null && $item !== '') {
                    $labels[] = (string) $item;
                }

                continue;
            }

            if (is_object($item)) {
                $item = (array) $item;
            }

            if (! is_array($item)) {
                continue;
            }

            foreach (['label', 'name', 'title', 'value', 'id'] as $key) {
                if (array_key_exists($key, $item) && $item[$key] !== null && $item[$key] !== '') {
                    $labels[] = (string) $item[$key];
                    continue 2;
                }
            }

            $labels = [...$labels, ...self::flattenSelectLabels($item)];
        }

        return array_values(array_unique($labels));
    }

    /**
     * Splits text into tokens (words + surrounding whitespace preserved).
     *
     * @return string[]
     */
    private static function tokenize(string $text): array
    {
        $tokens = preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        return $tokens ?: [];
    }

    /**
     * Computes the Longest Common Subsequence between two token arrays.
     * Returns pairs of [old_index, new_index] for matching positions.
     *
     * Uses O(n*m) DP limited to sequences ≤ 2000 tokens each for performance.
     *
     * @param  string[]  $a
     * @param  string[]  $b
     * @return array<int, array{int, int}>
     */
    private static function computeLcs(array $a, array $b): array
    {
        $m = min(count($a), 2000);
        $n = min(count($b), 2000);

        if ($m === 0 || $n === 0) {
            return [];
        }

        // Build DP table (only two rows at a time to save memory).
        $dp   = array_fill(0, $n + 1, 0);
        $full = [];

        for ($i = 1; $i <= $m; $i++) {
            $prev = $dp;
            $full[$i] = [];

            for ($j = 1; $j <= $n; $j++) {
                if ($a[$i - 1] === $b[$j - 1]) {
                    $dp[$j] = $prev[$j - 1] + 1;
                } else {
                    $dp[$j] = max($dp[$j - 1], $prev[$j]);
                }

                $full[$i][$j] = $dp[$j];
            }
        }

        // Backtrack to find the actual LCS pairs.
        $pairs = [];
        $i     = $m;
        $j     = $n;

        while ($i > 0 && $j > 0) {
            if ($a[$i - 1] === $b[$j - 1]) {
                array_unshift($pairs, [$i - 1, $j - 1]);
                $i--;
                $j--;
            } elseif (($full[$i][$j - 1] ?? 0) > ($full[$i - 1][$j] ?? 0)) {
                $j--;
            } else {
                $i--;
            }
        }

        return $pairs;
    }
}
