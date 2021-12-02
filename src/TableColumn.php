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
    private ?array $attributes = null;

    private array $buttons = [];

    private $value;

    private ?ORM $item;

    public function __construct(string $name, array $config = [])
    {
        $this->name = $name;

        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
    }

    public function setItem(ORM $item): void
    {
        $this->item = $item;
    }

    public function attributes(): ?array
    {
        if ($this->attributes === null) {
            return null;
        }

        return ($this->attributes instanceof \Closure)
            ? ($this->attributes)($this->item)
            : $this->attributes;
    }

    public function value(): string
    {
        if(!empty($this->buttons)) {
            return $this->renderButtons();
        }

        if ($this->value === null) {
            return e((string)$this->item->get($this->name));
        }

        if ($this->value instanceof \Closure) {
            return (string)($this->value)($this->item);
        }

        if (is_string($this->value)) {
            return e((string)$this->item->get($this->value));
        }

        return '';
    }

    public function renderButtons(): string
    {
        $result = '';
        $buttons = ($this->buttons instanceof \Closure) ? ($this->buttons)($this->item) : $this->buttons;

        foreach($buttons as $btn) {

            if(is_string($btn)) {
                $result .= $btn;
                continue;
            }

            $text = isset($btn['icon']) ? "<span class='i_icon i_icon-{$btn['icon']}'></span>" : '';

            $uri = '#';
            if(isset($btn['href'])) {
                $uri = $btn['href'];
                unset($btn['href']);
            }

            if(isset($btn['text'])) {
                $text = $btn['text'];
                unset($btn['text']);
            }

            $class = 'i_btn i_btn-solid i_miitable__btn';

            if(isset($btn['style'])) {
                $class .= ' i_btn-'.$btn['style'];
            }

            $params = array_replace([
                'class' => $class,
                'data-id' => $this->item->id
            ], $btn);

            $result .= HTML::anchor($uri, $text, $params);
        }
        return $result;
    }


}
