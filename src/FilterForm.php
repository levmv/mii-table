<?php declare(strict_types=1);

namespace mii\table;

use mii\util\Url;
use mii\web\Form;

class FilterForm extends Form
{
    public const FILTER_TYPE_NUMBER = 1;
    public const FILTER_TYPE_STRING = 2;
    public const FILTER_TYPE_LIST = 3;

    public function fields(): array
    {
        return [
            'dir' => 'asc',
            'sort_column' => 'created',
            'filters' => [],
            'values' => [],
        ];
    }

    public function fill($settings_name)
    {
        $search_columns = config('admin.table.' . $settings_name . '.search') ?? [];
        foreach ($search_columns as $name) {
            $this->set($name, '');
        }
        return $search_columns;
    }

    public function sortLink($column)
    {
        $dir = $this->get('dir') === 'asc' ? 'desc' : 'asc';
        return Url::query(['sort_column' => $column, 'dir' => $dir], true);
    }


    public function fetchFilter(): \Generator
    {
        for ($i = 0, $iMax = \count($this->filters); $i < $iMax; $i++) {
            yield [
                'name' => $this->filters[$i],
                'value' => $this->values[$i],
            ];
        }
    }

    public function rules(): array
    {
        return [];
    }

}
