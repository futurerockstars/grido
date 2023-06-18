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

use Nette\Forms\Controls\Checkbox;

/**
 * Check box filter.
 */
class Check extends Filter
{

	/* representation TRUE in URI */
	const TRUE = '✓';

	/** @var string */
	protected $condition = 'IS NOT NULL';

	/**
	 * @return Checkbox
	 */
	protected function getFormControl()
	{
		$control = new Checkbox($this->label);
		$control->getControlPrototype()->class[] = 'checkbox';

		return $control;
	}

	/**
	 * @param string $value
	 * @return Condition|bool
	 *
	 * @internal
	 */
	public function __getCondition($value)
	{
		$value = $value == self::TRUE;

		return parent::__getCondition($value);
	}

	/**
	 * @param bool $value
	 * @return NULL
	 *
	 * @internal
	 */
	public function formatValue($value)
	{
		return null;
	}

	/**
	 * @param bool $value
	 * @return string
	 *
	 * @internal
	 */
	public function changeValue($value)
	{
		return (bool) $value === true
			? self::TRUE
			: $value;
	}

}
