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
use Nette\Application\UI\Form;
use Nette\ComponentModel\Container;
use function call_user_func_array;
use function func_get_args;

/**
 * Base of grid components.
 *
 * @property-read string $label
 * @property-read string $type
 * @property-read Grid $grid
 * @property-read Form $form
 */
abstract class Component extends \Nette\Application\UI\Component
{

	/** @var string */
	protected $label;

	/** @var string */
	protected $type;

	/** @var Grid */
	protected $grid;

	/** @var Form */
	protected $form;

	/**
	 * @return Grid
	 */
	public function getGrid()
	{
		return $this->grid;
	}

	/**
	 * @return Form
	 */
	public function getForm()
	{
		if ($this->form === null) {
			$this->form = $this->grid->getComponent('form');
		}

		return $this->form;
	}

	/**
	 * @return string
	 *
	 * @internal
	 */
	public function getLabel()
	{
		return $this->label;
	}

	/**
	 * @return string
	 *
	 * @internal
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * @param Grid $grid
	 * @param string $name
	 * @return Container
	 */
	protected function addComponentToGrid($grid, $name)
	{
		$this->grid = $grid;

		//check container exist
		$container = $this->grid->getComponent($this::ID, false);
		if (!$container) {
			$this->grid->addComponent(new Container(), $this::ID);
			$container = $this->grid->getComponent($this::ID);
		}

		return $container->addComponent($this, $name);
	}

	/**
	 * @param  string $message
	 * @return string
	 */
	protected function translate($message)
	{
		return call_user_func_array([$this->grid->getTranslator(), 'translate'], func_get_args());
	}

}
