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
use Nette\Forms\IControl;

/**
 * Filter with custom form control.
 *
 * @property-read IControl $formControl
 */
class Custom extends Filter
{

	/** @var IControl */
	protected $formControl;

	/**
	 * @param Grid $grid
	 * @param string $name
	 * @param string $label
	 */
	public function __construct($grid, $name, $label, IControl $formControl)
	{
		$this->formControl = $formControl;

		parent::__construct($grid, $name, $label);
	}

	/**
	 * @return IControl
	 *
	 * @internal
	 */
	public function getFormControl()
	{
		return $this->formControl;
	}

}
