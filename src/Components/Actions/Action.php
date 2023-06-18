<?php

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2011 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido\Components\Actions;

use Grido\Components\Component;
use Grido\Exception;
use Grido\Grid;
use Nette\Utils\Html;
use function array_shift;
use function call_user_func_array;
use function is_array;
use function is_callable;
use function vsprintf;

/**
 * Action on one row.
 *
 * @property-read Html $element
 * @property-write callable $customRender
 * @property-write callable $disable
 * @property Html $elementPrototype
 * @property string $primaryKey
 * @property array $options
 */
abstract class Action extends Component
{

	const ID = 'actions';

	/** @var Html <a> html tag */
	protected $elementPrototype;

	/** @var callable for custom rendering */
	protected $customRender;

	/** @var string - name of primary key f.e.: link->('Article:edit', array($primaryKey => 1)) */
	protected $primaryKey;

	/** @var callable for disabling */
	protected $disable;

	/** @var array */
	protected $options;

	/**
	 * @param Grid $grid
	 * @param string $name
	 * @param string $label
	 */
	public function __construct($grid, $name, $label)
	{
		$this->addComponentToGrid($grid, $name);

		$this->type = static::class;
		$this->label = $this->translate($label);
	}

	/**
	 * Sets html element.
	 *
	 * @return Action
	 */
	public function setElementPrototype(Html $elementPrototype)
	{
		$this->elementPrototype = $elementPrototype;

		return $this;
	}

	/**
	 * Sets callback for custom rendering.
	 *
	 * @param callable $callback
	 * @return Action
	 */
	public function setCustomRender($callback)
	{
		$this->customRender = $callback;

		return $this;
	}

	/**
	 * Sets primary key.
	 *
	 * @param string $primaryKey
	 * @return Action
	 */
	public function setPrimaryKey($primaryKey)
	{
		$this->primaryKey = $primaryKey;

		return $this;
	}

	/**
	 * Sets callback for disable.
	 * Callback should return TRUE if the action is not allowed for current item.
	 *
	 * @param callable $callback
	 * @return Action
	 */
	public function setDisable($callback)
	{
		$this->disable = $callback;

		return $this;
	}

	/**
	 * Sets client side confirm.
	 *
	 * @param string|callback $confirm
	 * @return Action
	 */
	public function setConfirm($confirm)
	{
		$this->setOption('confirm', $confirm);

		return $this;
	}

	/**
	 * Sets name of icon.
	 *
	 * @param string $name
	 * @return Action
	 */
	public function setIcon($name)
	{
		$this->setOption('icon', $name);

		return $this;
	}

	/**
	 * Sets user-specific option.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return Action
	 */
	public function setOption($key, $value)
	{
		if ($value === null) {
			unset($this->options[$key]);

		} else {
			$this->options[$key] = $value;
		}

		return $this;
	}

	/**
	 * Returns element prototype (<a> html tag).
	 *
	 * @return Html
	 * @throws Exception
	 */
	public function getElementPrototype()
	{
		if ($this->elementPrototype === null) {
			$this->elementPrototype = Html::el('a')
				->setClass(['grid-action-' . $this->getName()])
				->setText($this->label);
		}

		if (isset($this->elementPrototype->class)) {
			$this->elementPrototype->class = (array) $this->elementPrototype->class;
		}

		return $this->elementPrototype;
	}

	/**
	 * @return string
	 *
	 * @internal
	 */
	public function getPrimaryKey()
	{
		if ($this->primaryKey === null) {
			$this->primaryKey = $this->grid->getPrimaryKey();
		}

		return $this->primaryKey;
	}

	/**
	 * @param mixed $row
	 * @return Html
	 *
	 * @internal
	 */
	public function getElement($row)
	{
		$element = clone $this->getElementPrototype();

		if ($confirm = $this->getOption('confirm')) {
			$confirm = is_callable($confirm)
				? call_user_func_array($confirm, [$row])
				: $confirm;

			$value = is_array($confirm)
				? vsprintf($this->translate(array_shift($confirm)), $confirm)
				: $this->translate($confirm);

			$element->setAttribute('data-grido-confirm', $value);
		}

		return $element;
	}

	/**
	 * Returns user-specific option.
	 *
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function getOption($key, $default = null)
	{
		return $this->options[$key] ?? $default;
	}

	/**
	 * Returns user-specific options.
	 *
	 * @return array
	 */
	public function getOptions()
	{
		return $this->options;
	}

	/**
	 * @param mixed $row
	 * @return void
	 * @throws Exception
	 */
	public function render($row)
	{
		if (!$row || ($this->disable && call_user_func_array($this->disable, [$row]))) {
			return;
		}

		$element = $this->getElement($row);

		if ($this->customRender) {
			echo call_user_func_array($this->customRender, [$row, $element]);

			return;
		}

		echo $element->render();
	}

}
