<?php

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2011 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido\Components;

use Grido\Grid;
use Nette\Utils\Html;

/**
 * Toolbar button.
 *
 * @property-read Html $element
 * @property-write Html $elementPrototype
 * @property array $options
 * @property-read string $destination
 * @property-read array $arguments
 */
class Button extends Component
{

	const ID = 'buttons';

	/** @var string first param for method $presenter->link() */
	protected $destination;

	/** @var array second param for method $presenter->link() */
	protected $arguments = [];

	/** @var Html <a> html tag */
	protected $elementPrototype;

	/** @var array */
	protected $options = [];

	/**
	 * @param string $name
	 * @param string $label
	 * @param string $destination - first param for method $presenter->link()
	 * @param array $arguments - second param for method $presenter->link()
	 */
	public function __construct(Grid $grid, $name, $label = null, $destination = null, array $arguments = [])
	{
		$this->label = $label;
		$this->destination = $destination;
		$this->arguments = $arguments;

		$this->addComponentToGrid($grid, $name);
	}

	/**
	 * Sets name of icon.
	 *
	 * @param string $name
	 * @return static
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
	 * @return static
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
	 * Sets html element.
	 *
	 * @return Button
	 */
	public function setElementPrototype(Html $elementPrototype)
	{
		$this->elementPrototype = $elementPrototype;

		return $this;
	}

	/**
	 * @return Html
	 *
	 * @internal
	 */
	public function getElement()
	{
		$element = clone $this->getElementPrototype();

		$href = $this->presenter->link($this->getDestination(), $this->getArguments());
		$element->href($href);

		return $element;
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
				->setClass(['grid-button-' . $this->getName()])
				->setText($this->label);
		}

		if (isset($this->elementPrototype->class)) {
			$this->elementPrototype->class = (array) $this->elementPrototype->class;
		}

		return $this->elementPrototype;
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
	 * @return string
	 *
	 * @internal
	 */
	public function getDestination()
	{
		if ($this->destination === null) {
			$this->destination = $this->getName();
		}

		return $this->destination;
	}

	/**
	 * @return array
	 *
	 * @internal
	 */
	public function getArguments()
	{
		return $this->arguments;
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function render()
	{
		echo $this->getElement();
	}

}
