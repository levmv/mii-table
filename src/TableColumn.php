<?php declare(strict_types=1);

namespace mii\table;

use mii\db\ORM;
use mii\util\HTML;

/**
 * Class TableColumn
 */
class TableColumn
{
    public string $name;

    public string $title;

    public bool $sort = true;
    public bool $sortBy = false;
    private bool $lock = false;
    private $attributes = null;

    private $value;

    private ?ORM $item;

    public function __construct(string $name, array $config = [])
    {
        $this->name = $name;

        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
        /*    $defaults = [
                'title' => '',
                'sort' => false,
                'lock' => false,
                'value' => null
            ];*/
    }

    public function setItem(ORM $item): void
    {
        $this->item = $item;
    }

    public function attributes(): string
    {
        if ($this->attributes === null) {
            return '';
        }

        $attrs = ($this->attributes instanceof \Closure)
            ? ($this->attributes)($this->item)
            : $this->attributes;

        return HTML::attributes($attrs);
    }

    public function value(): string
    {
        if ($this->value === null) {
            return (string)$this->item->get($this->name);
        }

        if ($this->value instanceof \Closure) {
            return (string)($this->value)($this->item);
        }

        if (is_string($this->value)) {
            return (string)$this->item->get($this->value);
        }

        return '';
    }


}
