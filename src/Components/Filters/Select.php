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

use Grido\Grid;
use Nette\Forms\Controls\SelectBox;

/**
 * Select box filter.
 */
class Select extends Filter
{

	/**
	 * @param Grid $grid
	 * @param string $name
	 * @param string $label
	 * @param array $items for select
	 */
	public function __construct($grid, $name, $label, ?array $items = null)
	{
		parent::__construct($grid, $name, $label);

		if ($items !== null) {
			$this->getControl()->setItems($items);
		}
	}

	/**
	 * @return SelectBox
	 */
	protected function getFormControl()
	{
		return new SelectBox($this->label);
	}

}
