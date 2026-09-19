<?php

declare(strict_types=1);

namespace App\View\Components\Form;

use App\Support\DatabaseEnum;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * A dropdown whose options come from the PostgreSQL enum type behind the column.
 *
 * Every constrained column gets its list from the database rather than from a
 * hand-kept array, so a value added by a migration appears here immediately and
 * a value the column would reject can never be offered.
 */
class EnumSelect extends Component
{
    public function __construct(
        public string $name,
        public string $label,
        public string $column,
        public bool $required = false,
        public ?string $placeholder = null,
        public ?string $hint = null,
    ) {}

    /**
     * Options as value => translated label.
     *
     * The stored value is Arabic already, so it doubles as the display text
     * unless a translation is registered under `enums.<column>.<value>`.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach (DatabaseEnum::for($this->column) as $value) {
            $key = "enums.{$this->column}.{$value}";
            $translated = __($key);

            $options[$value] = is_string($translated) && $translated !== $key
                ? $translated
                : $value;
        }

        return $options;
    }

    public function render(): View
    {
        return view('components.form.enum-select');
    }
}
