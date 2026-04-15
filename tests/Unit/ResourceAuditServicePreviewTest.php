<?php

namespace Androsamp\FilamentResourceLock\Tests\Unit;

use Androsamp\FilamentResourceLock\Services\ResourceAuditService;
use PHPUnit\Framework\TestCase;

class ResourceAuditServicePreviewTest extends TestCase
{
    public function test_compute_changes_includes_preview_html_from_snapshots(): void
    {
        $service = new ResourceAuditService;

        $oldSnapshot = [
            'coords' => [
                'value' => ['lat' => 1, 'lng' => 2],
                'label' => 'Coords',
                'type' => 'MapPicker',
                'audit_diff_preview' => '<p>1, 2</p>',
            ],
        ];

        $newSnapshot = [
            'coords' => [
                'value' => ['lat' => 3, 'lng' => 4],
                'label' => 'Coords',
                'type' => 'MapPicker',
                'audit_diff_preview' => '<p>3, 4</p>',
            ],
        ];

        $changes = $service->computeChanges($oldSnapshot, $newSnapshot);

        self::assertCount(1, $changes);
        self::assertSame('<p>1, 2</p>', $changes[0]['old_preview_html']);
        self::assertSame('<p>3, 4</p>', $changes[0]['new_preview_html']);
    }
}
