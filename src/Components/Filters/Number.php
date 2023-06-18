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

use Exception;
use Nette\Forms\Controls\TextInput;
use function preg_match;
use function rand;
use function sprintf;
use function str_replace;

/**
 * Number input filter.
 */
class Number extends Text
{

	/** @var string */
	protected $condition;

	/**
	 * @return TextInput
	 */
	protected function getFormControl()
	{
		$control = parent::getFormControl();
		$hint = 'Grido.HintNumber';
		$control->getControlPrototype()->title = sprintf($this->translate($hint), rand(1, 9));
		$control->getControlPrototype()->class[] = 'number';

		return $control;
	}

	/**
	 * @param string $value
	 * @return Condition|bool
	 * @throws Exception
	 *
	 * @internal
	 */
	public function __getCondition($value)
	{
		$condition = parent::__getCondition($value);

		if ($condition === null) {
			$condition = Condition::setupEmpty();

			if (preg_match('/(<>|[<|>]=?)?([-0-9,|.]+)/', $value, $matches)) {
				$value = str_replace(',', '.', $matches[2]);
				$operator = $matches[1]
					? $matches[1]
					: '=';

				$condition = Condition::setup($this->getColumn(), $operator . ' ?', $value);
			}
		}

		return $condition;
	}

}
