<?php

namespace Androsamp\FilamentResourceLock\Http\Livewire;

use Androsamp\FilamentResourceLock\Support\AuditDiffRenderer;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AuditDiffRichTextSnapshots extends Component implements HasForms
{
    use InteractsWithForms;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public string $editorType = 'RichEditor';

    /**
     * @var array<int, string>
     */
    public array $customBlockClasses = [];

    public function mount(
        string $editorType = 'RichEditor',
        mixed $oldValue = null,
        mixed $newValue = null,
        array $customBlockClasses = [],
    ): void {
        $this->editorType = $editorType;
        $this->customBlockClasses = array_values(array_filter(
            $customBlockClasses,
            static fn (mixed $class): bool => is_string($class)
                && $class !== ''
                && class_exists($class)
                && is_subclass_of($class, RichContentCustomBlock::class),
        ));

        if ($this->editorType === 'MarkdownEditor') {
            $this->form->fill([
                'snapshot_old' => AuditDiffRenderer::normalizeMarkdownEditorStateForForm($oldValue),
                'snapshot_new' => AuditDiffRenderer::normalizeMarkdownEditorStateForForm($newValue),
            ]);

            return;
        }

        $this->form->fill([
            'snapshot_old' => AuditDiffRenderer::normalizeRichEditorStateForForm($oldValue),
            'snapshot_new' => AuditDiffRenderer::normalizeRichEditorStateForForm($newValue),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $schema = $schema->statePath('data');

        if ($this->editorType === 'MarkdownEditor') {
            return $schema->components([
                Section::make(__('filament-resource-lock::resource-lock.audit.diff.was'))
                    ->compact()
                    ->schema([
                        MarkdownEditor::make('snapshot_old')
                            ->hiddenLabel()
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->extraAttributes([
                        'style' => 'padding:12px 16px;background:rgba(239,68,68,.07);border-bottom:1px solid rgba(148,163,184,.15);',
                    ]),
                Section::make(__('filament-resource-lock::resource-lock.audit.diff.became'))
                    ->compact()
                    ->schema([
                        MarkdownEditor::make('snapshot_new')
                            ->hiddenLabel()
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->extraAttributes([
                        'style' => 'padding:12px 16px;background:rgba(34,197,94,.07);',
                    ]),
            ]);
        }

        $blocks = $this->customBlockClasses;

        return $schema->components([
            Section::make(__('filament-resource-lock::resource-lock.audit.diff.was'))
                ->compact()
                ->schema([
                    RichEditor::make('snapshot_old')
                        ->hiddenLabel()
                        ->disabled()
                        ->dehydrated(false)
                        ->customBlocks($blocks),
                ])
                ->extraAttributes([
                    'style' => 'padding:12px 16px;background:rgba(239,68,68,.07);border-bottom:1px solid rgba(148,163,184,.15);',
                ]),
            Section::make(__('filament-resource-lock::resource-lock.audit.diff.became'))
                ->compact()
                ->schema([
                    RichEditor::make('snapshot_new')
                        ->hiddenLabel()
                        ->disabled()
                        ->dehydrated(false)
                        ->customBlocks($blocks),
                ])
                ->extraAttributes([
                    'style' => 'padding:12px 16px;background:rgba(34,197,94,.07);',
                ]),
        ]);
    }

    public function render(): View
    {
        return view('filament-resource-lock::livewire.audit-diff-rich-text-snapshots');
    }
}
