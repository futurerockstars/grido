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

use Grido\Exception;
use Grido\Grid;
use Nette\Utils\Html;
use function call_user_func_array;

/**
 * Event action.
 *
 * @property callable $onClick function($id, Grido\Components\Actions\Event $event)
 */
class Event extends Action
{

	/** @var callable function($id, Grido\Components\Actions\Event $event) */
	private $onClick;

	/**
	 * @param Grid $grid
	 * @param string $name
	 * @param string $label
	 * @param callable $onClick
	 * @throws Exception
	 */
	public function __construct($grid, $name, $label, $onClick = null)
	{
		parent::__construct($grid, $name, $label);

		if ($onClick === null) {
			$grid->onRender[] = function (Grid $grid) {
				if ($this->onClick === null) {
					throw new Exception("Callback onClick in action '{$this->name}' must be set.");
				}
			};
		} else {
			$this->setOnClick($onClick);
		}
	}

	/**
	 * Sets on-click handler.
	 *
	 * @param callable $onClick function($id, Grido\Components\Actions\Event $event)
	 * @return Event
	 */
	public function setOnClick(callable $onClick)
	{
		$this->onClick = $onClick;

		return $this;
	}

	/**
	 * Returns on-click handler.
	 *
	 * @return callable
	 */
	public function getOnClick()
	{
		return $this->onClick;
	}

	/**
	 * @param mixed $row
	 * @return Html
	 *
	 * @internal
	 */
	public function getElement($row)
	{
		$element = parent::getElement($row);

		$primaryValue = $this->grid->getProperty($row, $this->getPrimaryKey());
		$element->href($this->link('click!', $primaryValue));

		return $element;
	}

	/**
	 * @param int $id
	 *
	 * @internal
	 */
	public function handleClick($id)
	{
		call_user_func_array($this->onClick, [$id, $this]);
	}

}
