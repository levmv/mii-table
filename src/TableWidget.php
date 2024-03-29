<?php declare(strict_types=1);

namespace mii\table;

use mii\db\ORM;
use mii\db\SelectQuery;
use mii\util\Arr;
use mii\util\HTML;
use mii\web\Pagination;

/**
 * Class TableWidget
 */
class TableWidget
{
    public string $id = '';

    public string $settings_url = '/admin/settings/table';

    public array $active_columns = [];
    public array $columns = [];
    public $row_attributes = [];
    public array $table_attributes = [
        'class' => 'i_table',
    ];

    /**
     * @var array
     *
     * [
     *  'id' => [
     *      'title' => Arbitrary filter name
     *      'type' => Filters type. One of const FilterForm::FILTER_TYPE_...'
     *      'value' => Filters initial (default) value. It'll be rewritten from $_GET
     *      'data' => Source array of filter or closure. Used for FILTER_TYPE_LIST
     *      'action' => function($query, $value). Function that applies filter. Not required.
     *  ]
     * ]
     *
     */
    public array $filters = [];
    public array $active_filters = [];

    public ?string $defaultSortColumn;
    public string $defaultSortDir;

    private FilterForm $form;

    private SelectQuery $query;
    protected ?array $items = null;
    private int $count;
    protected int $rows_per_page = 50;
    private Pagination $pagination;
    protected ?string $base_uri = null;

    /**
     * @var TableColumn[] $cols
     */
    protected array $cols = [];

    public function __construct(SelectQuery $query, $config = [])
    {
        $this->setup($config);

        $this->form = new FilterForm();

        $this->form->set('sort_column', $this->defaultSortColumn);
        $this->form->set('dir', $this->defaultSortDir);

        $this->form->load($_GET);

        $this->query = $query;

        foreach ($this->form->fetchFilter() as $filter_data) {
            $this->applyFilter($filter_data['name'], $filter_data['value']);
        }
    }


    public function applyFilter($name, $value): void
    {
        if (!isset($this->filters[$name])) {
            return;
        }

        $this->active_filters[$name] = $value;

        if (isset($this->filters[$name]['action']) && \is_callable($this->filters[$name]['action'])) {
            \call_user_func($this->filters[$name]['action'], $this->query, $value);
        } else {
            $this->query->andFilter($name, '=', $value);
        }
    }

    public function setupDefaultSort($column = null, $dir = 'desc'): void
    {
        $this->defaultSortColumn = $column;
        $this->defaultSortDir = $dir;

        if($this->defaultSortColumn === null) {
            $this->defaultSortColumn = \key($this->columns);
        }
    }

    public function setupColumns() : array
    {
        return [];
    }

    public function setupFilters() : array
    {
        return [];
    }

    public function setupPagination()
    {
        $this->pagination = new Pagination([
            'total_items' => $this->count,
            'block' => null,// 'table_pagination',
            'base_class' => 'table_pagination',
            'base_uri' => $this->base_uri,
            'items_per_page' => $this->rows_per_page
        ]);
    }


    public function setup($config = []): void
    {
        if(empty($this->columns)) {
            $this->columns = $config['columns'] ?? $this->setupColumns();
        }

        if(empty($this->filters)) {
            $this->filters = $config['filters'] ?? $this->setupFilters();
        }

        if (isset($config['active_columns'])) {
            $this->active_columns = $config['active_columns'];
        }

        if (empty($this->active_columns)) {
            $this->active_columns = \array_keys($this->columns);
        }

        if (isset($config['row_attributes'])) {
            $this->row_attributes = $config['row_attributes'];
        }

        $this->setupDefaultSort();
    }

    public function sortParam(string $name)
    {
        return $this->form->get($name);
    }

    public function items(): ?array
    {
        return $this->items;
    }

    public function totalCount(): int
    {
        return $this->count;
    }

    public function count(): int
    {
        return \count($this->items);
    }


    public function tableAttributes(): array
    {
        $attrs = [
            'id' => 'mia_table_id' . $this->id,
            'class' => 'i_table'
        ];

        if (!empty($this->table_attributes)) {
            $attrs = \array_merge($attrs, \is_callable($this->table_attributes)
                ? ($this->table_attributes)()
                : $this->table_attributes
            );
        }

        return $attrs;
    }

    public function rowAttributes(ORM $item): array
    {
        if (empty($this->row_attributes)) {
            return [
                'class' => "t__row t__row{$item->id}",
                'data-id' => $item->id
            ];
        }

        return \is_callable($this->row_attributes)
            ? ($this->row_attributes)($item)
            : $this->row_attributes;
    }

    /**
     * @return \Generator|TableColumn[]
     */
    public function headColumns(): \Generator
    {
        $curSortCol = $this->form->get('sort_column');

        foreach ($this->cols as $col) {
            if ($curSortCol === $col->name) {
                $col->sortBy = true;
            }
            yield $col;
        }
    }

    /**
     * @param $item
     * @return TableColumn[]
     */
    public function rowColumns($item): array
    {
        foreach ($this->cols as $col) {
            $col->setItem($item);
        }
        return $this->cols;
    }


    protected function prepare()
    {
        $this->columns = Arr::overwrite(\array_flip($this->active_columns), $this->columns);

        foreach ($this->columns as $name => $column) {
            if (is_string($column)) {
                $column = ['title' => $column];
            }
            $this->cols[] = new TableColumn($name, $column);
        }

        $this->count = $this->query->count();

        $this->setupPagination();

        if ($this->pagination) {
            $this->query
                ->offset($this->pagination->getOffset())
                ->limit($this->pagination->getLimit());
        }
        $sortColumn = $this->sortParam('sort_column');

        if($sortColumn) {
            $this->applySorting($this->query, $sortColumn, $this->sortParam('dir'));
        }

        if ($this->items === null) {
            $this->items = $this->query->all();
        }
    }

    protected function applySorting(SelectQuery $query, $column, $dir)
    {
        $query->orderBy([[$this->sortParam('sort_column'), $this->sortParam('dir')]]);
    }


    public function render(): string
    {
        $this->prepare();

        $filters = \json_encode([
            'filters' => $this->filters,
            'active' => $this->active_filters
        ], \mii\util\Text::JSON_FLAGS);

        $filtersData = "<script type=\"application/json\" id=\"filters_data\" data-id=\"$this->id\">$filters</script>";

        $filtersSceleton = <<<EOF
            <div class="t__h">
                <div class="t__h_filters">
                    <div class="t__h_filters_selected"></div>
                    <div class="t__h_filters_ctrl">
                        <div class="t__sel_f_name" data-dropdown="#t__h_filters_list"></div>
                        <div class="t__sel_f_value"></div>
                        <div class="t__sel_f_add">
                            <button class="i_btn t__filter_button"><span></span></button>
                            <a href="#" class="i_btn t__filter_add_button" style="display: none"
                               title="Добавить еще один фильтр">
                                <i class="i_icon i_icon-add"></i>
                            </a>
                        </div>
                        <div class="dropdown dropdown-relative" id="t__h_filters_list"></div>
                    </div>
                </div>
            </div>
            EOF;

        $form = $this->form->open(null, ['method' => 'GET', 'id' => 'mia_table_form_' . $this->id]);
        $form .= '<input type="hidden" name="sort_column" value="'.$this->form->get('sort_column') . '"/>';
        $form .= '<input type="hidden" name="dir" value="'. $this->form->get('dir') .'"/>';
        $form .= $filtersSceleton;
        $form .= $this->form->close();


        $headContent = '';
        foreach ($this->headColumns() as $column) {
            $th = '';
            if ($column->sort === false) {
                $th = $column->title;
            } else {
                $th = '<a href="' . $this->form->sortLink($column->name) . '">' . $column->title . '</a>';
                if ($column->sortBy) {
                    $th .= $this->form->get('dir') === 'asc' ? ' &uarr;' : ' &darr;';
                }
            }

            $headContent .= HTML::tag('th', $th, [
                'class' => $column->sort ? 'admin_table__sort_column' : ''
            ]);
        }

        $head = "<thead><tr>$headContent</tr></thead>";

        $bodyContent = '';
        foreach ($this->items() as $item) {
            $row = '';
            foreach ($this->rowColumns($item) as $column) {
                $row .= HTML::tag('td', $column->value(), $column->attributes());
            }
            $bodyContent .= HTML::tag('tr', $row, $this->rowAttributes($item));
        }

        $result = $filtersData .  $form;
        $result .= HTML::tag('table', $head . $bodyContent, $this->tableAttributes());
        $result .= $this->renderFooter();

        return $result;
    }

    public function renderFooter(): string
    {
        return (string) $this->pagination();
    }

    public function pagination(): Pagination
    {
        return $this->pagination;
    }
}
