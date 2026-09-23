<?php

namespace App\Http\Requests\HomeBlock;

use App\Models\HomeBlock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class HomeBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced in the controller.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'blocks' => ['array'],
            'blocks.*.id' => ['required', 'integer', 'exists:home_blocks,id'],
            'blocks.*.enabled' => ['boolean'],
            'blocks.*.title' => ['array'],
            'blocks.*.title.ru' => ['nullable', 'string', 'max:255'],
            'blocks.*.title.tg' => ['nullable', 'string', 'max:255'],
            'blocks.*.title.en' => ['nullable', 'string', 'max:255'],
            'blocks.*.limit' => ['nullable', 'integer', 'min:1', 'max:20'],

            // Показатели ведомства: значение остаётся строкой — редактор
            // пишет «86 500» или «1 318» с разделителем разрядов, и приводить
            // это к числу значило бы терять формат, который он выбрал.
            'blocks.*.items' => ['nullable', 'array', 'max:8'],
            'blocks.*.items.*.value' => ['required', 'string', 'max:20'],
            'blocks.*.items.*.label' => ['required', 'array'],
            'blocks.*.items.*.label.ru' => ['nullable', 'string', 'max:120'],
            'blocks.*.items.*.label.tg' => ['nullable', 'string', 'max:120'],
            'blocks.*.items.*.label.en' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * A block can't promise more items than its place on the public home
     * page shows (HomeBlock::MAX_ITEMS).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $rows = $this->input('blocks', []);

            if (! is_array($rows)) {
                return;
            }

            $types = HomeBlock::query()
                ->whereIn('id', collect($rows)->pluck('id')->filter()->map(fn (mixed $id): int => (int) $id))
                ->pluck('type', 'id');

            foreach ($rows as $index => $row) {
                if (! is_array($row) || ! is_numeric($row['limit'] ?? null)) {
                    continue;
                }

                $type = $types->get((int) ($row['id'] ?? 0));
                $max = is_string($type) ? (HomeBlock::MAX_ITEMS[$type] ?? null) : null;

                if ($max !== null && (int) $row['limit'] > $max) {
                    $validator->errors()->add("blocks.{$index}.limit", "В этом блоке на сайте помещается не больше {$max} материалов.");
                }
            }
        }];
    }
}
