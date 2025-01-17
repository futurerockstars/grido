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

use DateTime;
use Exception;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\Strings;
use function array_pop;
use function is_string;
use function trim;

/**
 * Date-range input filter.
 *
 * @property string $mask
 */
class DateRange extends Date
{

	/** @var string */
	protected $condition = 'BETWEEN ? AND ?';

	/** @var string */
	protected $mask = '/(.*)\s?-\s?(.*)/';

	/** @var array */
	protected $dateFormatOutput = ['Y-m-d', 'Y-m-d G:i:s'];

	/**
	 * @param string $formatFrom
	 * @param string $formatTo
	 * @return DateRange
	 */
	public function setDateFormatOutput($formatFrom, $formatTo = null)
	{
		$formatTo ??= $formatFrom;

		$this->dateFormatOutput = [$formatFrom, $formatTo];

		return $this;
	}

	/**
	 * Sets mask by regular expression.
	 *
	 * @param string $mask
	 * @return DateRange
	 */
	public function setMask($mask)
	{
		$this->mask = $mask;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getMask()
	{
		return $this->mask;
	}

	/**
	 * @return TextInput
	 */
	protected function getFormControl()
	{
		$control = parent::getFormControl();

		$prototype = $control->getControlPrototype();
		array_pop($prototype->class); //remove "date" class
		$prototype->class[] = 'daterange';

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
		if ($this->where === null && is_string($this->condition)) {

			[, $from, $to] = Strings::match($value, $this->mask);
			$from = DateTime::createFromFormat($this->dateFormatInput, trim($from));
			$to = DateTime::createFromFormat($this->dateFormatInput, trim($to));

			if ($to && !Strings::match($this->dateFormatInput, '/G|H/i')) { //input format haven't got hour option
				Strings::contains($this->dateFormatOutput[1], 'G') || Strings::contains($this->dateFormatOutput[1], 'H')
					? $to->setTime(23, 59, 59)
					: $to->setTime(11, 59, 59);
			}

			$values = $from && $to
				? [$from->format($this->dateFormatOutput[0]), $to->format($this->dateFormatOutput[1])]
				: null;

			return $values
				? Condition::setup($this->getColumn(), $this->condition, $values)
				: Condition::setupEmpty();
		}

		return parent::__getCondition($value);
	}

}
