<?php

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2011 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido\Components\Filters;

use Grido\Components\Component;
use Grido\Exception;
use Grido\Grid;
use Grido\Helpers;
use Nette\Forms\Controls\BaseControl;
use Nette\Utils\Html;
use function call_user_func_array;
use function count;
use function gettype;
use function is_array;
use function is_callable;
use function is_string;
use function sprintf;
use function str_replace;

/**
 * Data filtering.
 *
 * @property-read array $column
 * @property-read string $wrapperPrototype
 * @property-read BaseControl $control
 * @property-write string $condition
 * @property-write callable $where
 * @property-write string $formatValue
 * @property-write string $defaultValue
 */
abstract class Filter extends Component
{

	const ID = 'filters';

	const VALUE_IDENTIFIER = '%value';

	const RENDER_INNER = 'inner';

	const RENDER_OUTER = 'outer';

	/** @var mixed */
	protected $optional;

	/** @var array */
	protected $column = [];

	/** @var string */
	protected $condition = '= ?';

	/** @var callable */
	protected $where;

	/** @var string */
	protected $formatValue;

	/** @var Html */
	protected $wrapperPrototype;

	/** @var BaseControl */
	protected $control;

	/**
	 * @param Grid $grid
	 * @param string $name
	 * @param string $label
	 */
	public function __construct($grid, $name, $label)
	{
		$name = Helpers::formatColumnName($name);
		$this->addComponentToGrid($grid, $name);

		$this->label = $label;
		$this->type = static::class;

		$form = $this->getForm();
		$filters = $form->getComponent(self::ID, false);
		if ($filters === null) {
			$filters = $form->addContainer(self::ID);
		}

		$filters->addComponent($this->getFormControl(), $name);
	}

	/**
	 * Map to database column.
	 *
	 * @param string $column
	 * @param string $operator
	 * @return Filter
	 * @throws Exception
	 */
	public function setColumn($column, $operator = Condition::OPERATOR_OR)
	{
		$columnAlreadySet = count($this->column) > 0;
		if (!Condition::isOperator($operator) && $columnAlreadySet) {
			$msg = sprintf("Operator must be '%s' or '%s'.", Condition::OPERATOR_AND, Condition::OPERATOR_OR);

			throw new Exception($msg);
		}

		if ($columnAlreadySet) {
			$this->column[] = $operator;
			$this->column[] = $column;
		} else {
			$this->column[] = $column;
		}

		return $this;
	}

	/**
	 * Sets custom condition.
	 *
	 * @param $condition
	 * @return Filter
	 */
	public function setCondition($condition)
	{
		$this->condition = $condition;

		return $this;
	}

	/**
	 * Sets custom "sql" where.
	 *
	 * @param callable $callback function($value, $source) {}
	 * @return Filter
	 */
	public function setWhere($callback)
	{
		$this->where = $callback;

		return $this;
	}

	/**
	 * Sets custom format value.
	 *
	 * @param string $format for example: "%%value%"
	 * @return Filter
	 */
	public function setFormatValue($format)
	{
		$this->formatValue = $format;

		return $this;
	}

	/**
	 * Sets default value.
	 *
	 * @param string $value
	 * @return Filter
	 */
	public function setDefaultValue($value)
	{
		$this->grid->setDefaultFilter([$this->getName() => $value]);

		return $this;
	}

	/**
	 * @return array
	 *
	 * @internal
	 */
	public function getColumn()
	{
		if (empty($this->column)) {
			$column = $this->getName();
			if ($columnComponent = $this->grid->getColumn($column, false)) {
				$column = $columnComponent->column; //use db column from column compoment
			}

			$this->setColumn($column);
		}

		return $this->column;
	}

	/**
	 * @return BaseControl
	 *
	 * @internal
	 */
	public function getControl()
	{
		if ($this->control === null) {
			$this->control = $this->getForm()->getComponent(self::ID)->getComponent($this->getName());
		}

		return $this->control;
	}

	/**
	 * @throws Exception
	 */
	protected function getFormControl()
	{
		throw new Exception("Filter {$this->name} cannot be use, because it is not implement getFormControl() method.");
	}

	/**
	 * Returns wrapper prototype (<th> html tag).
	 *
	 * @return Html
	 */
	public function getWrapperPrototype()
	{
		if (!$this->wrapperPrototype) {
			$this->wrapperPrototype = Html::el('th')
				->setClass(['grid-filter-' . $this->getName()]);
		}

		return $this->wrapperPrototype;
	}

	/**
	 * @return string
	 */
	public function getCondition()
	{
		return $this->condition;
	}

	/**
	 * @param mixed $value
	 * @return Condition|bool
	 * @throws Exception
	 *
	 * @internal
	 */
	public function __getCondition($value)
	{
		if ($value === '' || $value === null) {
			return false; //skip
		}

		$condition = $this->getCondition();

		if ($this->where !== null) {
			$condition = Condition::setupFromCallback($this->where, $value);

		} elseif (is_string($condition)) {
			$condition = Condition::setup($this->getColumn(), $condition, $this->formatValue($value));

		} elseif (is_callable($condition)) {
			$condition = call_user_func_array($condition, [$value]);

		} elseif (is_array($condition)) {
			$condition = $condition[$value] ?? Condition::setupEmpty();
		}

		if (is_array($condition)) { //for user-defined condition by array or callback
			$condition = Condition::setupFromArray($condition);

		} elseif ($condition !== null && !$condition instanceof Condition) {
			$type = gettype($condition);

			throw new Exception("Condition must be array or Condition object. $type given.");
		}

		return $condition;
	}

	/**
	 * Format value for database.
	 *
	 * @param string $value
	 * @return string
	 */
	protected function formatValue($value)
	{
		return $this->formatValue !== null ? str_replace(self::VALUE_IDENTIFIER, $value, $this->formatValue) : $value;
	}

	/**
	 * Value representation in URI.
	 *
	 * @param string $value
	 * @return string
	 *
	 * @internal
	 */
	public function changeValue($value)
	{
		return $value;
	}

}
