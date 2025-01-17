<?php

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2011 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido\Components\Columns;

use Grido\Components\Component;
use Grido\Components\Filters\Check;
use Grido\Components\Filters\Custom;
use Grido\Components\Filters\Date;
use Grido\Components\Filters\DateRange;
use Grido\Components\Filters\Number;
use Grido\Components\Filters\Select;
use Grido\Components\Filters\Text;
use Grido\Exception;
use Grido\Grid;
use Grido\Helpers;
use Latte\Runtime\Filters;
use Nette\Forms\IControl;
use Nette\Utils\Html;
use function call_user_func_array;
use function is_callable;
use function is_scalar;
use function is_string;
use function str_replace;
use function strip_tags;

/**
 * Column grid.
 *
 * @property-read string $sort
 * @property-read Html $cellPrototype
 * @property-read Html $headerPrototype
 * @property-write callback $cellCallback
 * @property-write string $defaultSorting
 * @property mixed $customRender
 * @property-write array $customRenderVariables
 * @property-write mixed $customRenderExport
 * @property-write array $replacements
 * @property-write bool $sortable
 * @property string $column
 */
abstract class Column extends Component
{

	const ID = 'columns';

	const VALUE_IDENTIFIER = '%value';

	const ORDER_ASC = 'asc';

	const ORDER_DESC = 'desc';

	/** @var string */
	protected $sort;

	/** @var string */
	protected $column;

	/** @var Html<td> html tag */
	protected $cellPrototype;

	/** @var callback returns td html element; function($row, Html $td) */
	protected $cellCallback;

	/** @var Html<th> html tag */
	protected $headerPrototype;

	/** @var mixed custom rendering */
	protected $customRender;

	/** @var array custom rendering template variables */
	protected $customRenderVariables = [];

	/** @var mixed custom export rendering */
	protected $customRenderExport;

	/** @var bool */
	protected $sortable = false;

	/** @var array of arrays('pattern' => 'replacement') */
	protected $replacements = [];

	/**
	 * @param Grid $grid
	 * @param string $name
	 * @param string $label
	 */
	public function __construct($grid, $name, $label)
	{
		$this->addComponentToGrid($grid, Helpers::formatColumnName($name));

		$this->type = static::class;
		$this->label = $label;
	}

	/**
	 * @param bool $sortable
	 * @return Column
	 */
	public function setSortable($sortable = true)
	{
		$this->sortable = (bool) $sortable;

		return $this;
	}

	/**
	 * @param array $replacement array('pattern' => 'replacement')
	 * @return Column
	 */
	public function setReplacement(array $replacement)
	{
		$this->replacements += $replacement;

		return $this;
	}

	/**
	 * @param mixed $column
	 * @return Column
	 */
	public function setColumn($column)
	{
		$this->column = $column;

		return $this;
	}

	/**
	 * @param string $dir
	 * @return Column
	 */
	public function setDefaultSort($dir)
	{
		$this->grid->setDefaultSort([$this->getName() => $dir]);

		return $this;
	}

	/**
	 * @param mixed $callback callback or string for name of template filename
	 * @param array $variables - template variables
	 * @return Column
	 */
	public function setCustomRender($callback, $variables = [])
	{
		$this->customRender = $callback;
		$this->customRenderVariables = $variables;

		return $this;
	}

	/**
	 * @param mixed $callback |
	 * @return Column
	 */
	public function setCustomRenderExport($callback)
	{
		$this->customRenderExport = $callback;

		return $this;
	}

	/**
	 * @param callback $callback
	 * @return Column
	 */
	public function setCellCallback($callback)
	{
		$this->cellCallback = $callback;

		return $this;
	}

	/**
	 * Returns cell prototype (<td> html tag).
	 *
	 * @param mixed $row
	 * @return Html
	 */
	public function getCellPrototype($row = null)
	{
		$td = $this->cellPrototype;

		if ($td === null) { //cache
			$td = $this->cellPrototype = Html::el('td')
				->setClass(['grid-cell-' . $this->getName()]);
		}

		if ($this->cellCallback && $row !== null) {
			$td = clone $td;
			$td = call_user_func_array($this->cellCallback, [$row, $td]);
		}

		return $td;
	}

	/**
	 * Returns header cell prototype (<th> html tag).
	 *
	 * @return Html
	 */
	public function getHeaderPrototype()
	{
		if ($this->headerPrototype === null) {
			$this->headerPrototype = Html::el('th')
				->setClass(['column', 'grid-header-' . $this->getName()]);
		}

		if ($this->isSortable() && $this->getSort()) {
			$this->headerPrototype->class[] = $this->getSort() == self::ORDER_DESC
				? 'desc'
				: 'asc';
		}

		return $this->headerPrototype;
	}

	/**
	 * @return mixed
	 *
	 * @internal
	 */
	public function getColumn()
	{
		return $this->column ? $this->column : $this->getName();
	}

	/**
	 * @return string
	 *
	 * @internal
	 */
	public function getSort()
	{
		if ($this->sort === null) {
			$name = $this->getName();

			$sort = $this->grid->sort[$name] ?? null;

			$this->sort = $sort ?? null;
		}

		return $this->sort;
	}

	/**
	 * @return mixed
	 *
	 * @internal
	 */
	public function getCustomRender()
	{
		return $this->customRender;
	}

	/**
	 * @return array
	 *
	 * @internal
	 */
	public function getCustomRenderVariables()
	{
		return $this->customRenderVariables;
	}

	/**
	 * @return mixed
	 *
	 * @internal
	 */
	public function getLabel()
	{
		return is_string($this->label)
			? $this->translate($this->label)
			: $this->label;
	}

	/**
	 * @return bool
	 *
	 * @internal
	 */
	public function isSortable()
	{
		return $this->sortable;
	}

	/**
	 * @return bool
	 *
	 * @internal
	 */
	public function hasFilter()
	{
		return (bool) $this->grid->getFilter($this->getName(), false);
	}

	/**
	 * @param mixed $row
	 * @return string
	 *
	 * @internal
	 */
	public function render($row)
	{
		if (is_callable($this->customRender)) {
			return call_user_func_array($this->customRender, [$row, $this->customRenderVariables]);
		}

		$value = $this->getValue($row);

		return $this->formatValue($value);
	}

	/**
	 * @param mixed $row
	 * @return string
	 *
	 * @internal
	 */
	public function renderExport($row)
	{
		if (is_callable($this->customRenderExport)) {
			return call_user_func_array($this->customRenderExport, [$row]);
		}

		$value = $this->getValue($row);

		return strip_tags($this->applyReplacement($value));
	}

	/**
	 * @param mixed $row
	 * @return mixed
	 * @throws Exception
	 */
	protected function getValue($row)
	{
		$column = $this->getColumn();
		if (is_string($column)) {
			return $this->grid->getProperty($row, Helpers::unformatColumnName($column));
		} elseif (is_callable($column)) {
			return call_user_func_array($column, [$row]);
		} else {
			throw new Exception('Column must be string or callback.');
		}
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	protected function applyReplacement($value)
	{
		if ((is_scalar($value) || $value === null) && isset($this->replacements[$value])) {
			$replaced = $this->replacements[$value];
			if (is_scalar($replaced)) {
				$replaced = $this->translate($replaced);
			}

			$value = is_string($value)
				? str_replace(self::VALUE_IDENTIFIER, $value, $replaced)
				: $replaced;
		}

		return $value;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	protected function formatValue($value)
	{
		$value = is_string($value)
			? Filters::escapeHtml($value)
			: $value;

		return $this->applyReplacement($value);
	}

	/******************************* Aliases for filters ******************************************/

	/**
	 * @return Text
	 */
	public function setFilterText()
	{
		return $this->grid->addFilterText($this->getName(), $this->label);
	}

	/**
	 * @return Date
	 */
	public function setFilterDate()
	{
		return $this->grid->addFilterDate($this->getName(), $this->label);
	}

	/**
	 * @return DateRange
	 */
	public function setFilterDateRange()
	{
		return $this->grid->addFilterDateRange($this->getName(), $this->label);
	}

	/**
	 * @return Check
	 */
	public function setFilterCheck()
	{
		return $this->grid->addFilterCheck($this->getName(), $this->label);
	}

	/**
	 * @param array $items
	 * @return Select
	 */
	public function setFilterSelect(?array $items = null)
	{
		return $this->grid->addFilterSelect($this->getName(), $this->label, $items);
	}

	/**
	 * @return Number
	 */
	public function setFilterNumber()
	{
		return $this->grid->addFilterNumber($this->getName(), $this->label);
	}

	/**
	 * @return Custom
	 */
	public function setFilterCustom(IControl $formControl)
	{
		return $this->grid->addFilterCustom($this->getName(), $formControl);
	}

}
