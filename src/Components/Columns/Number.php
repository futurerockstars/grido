<?php

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2011 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido\Components\Columns;

use Grido\Grid;
use function is_numeric;
use function number_format;

/**
 * Number column.
 *
 * @property array $numberFormat
 */
class Number extends Editable
{

	/** @const keys of array $numberFormat */
	const NUMBER_FORMAT_DECIMALS = 0;

	const NUMBER_FORMAT_DECIMAL_POINT = 1;

	const NUMBER_FORMAT_THOUSANDS_SEPARATOR = 2;

	/** @var array */
	protected $numberFormat = [
		self::NUMBER_FORMAT_DECIMALS => 0,
		self::NUMBER_FORMAT_DECIMAL_POINT => '.',
		self::NUMBER_FORMAT_THOUSANDS_SEPARATOR => ',',
	];

	/**
	 * @param Grid $grid
	 * @param string $name
	 * @param string $label
	 * @param int $decimals number of decimal points
	 * @param string $decPoint separator for the decimal point
	 * @param string $thousandsSep thousands separator
	 */
	public function __construct($grid, $name, $label, $decimals = null, $decPoint = null, $thousandsSep = null)
	{
		parent::__construct($grid, $name, $label);

		$this->setNumberFormat($decimals, $decPoint, $thousandsSep);
	}

	/**
	 * Sets number format. Params are same as internal function number_format().
	 *
	 * @param int $decimals number of decimal points
	 * @param string $decPoint separator for the decimal point
	 * @param string $thousandsSep thousands separator
	 * @return Number
	 */
	public function setNumberFormat($decimals = null, $decPoint = null, $thousandsSep = null)
	{
		if ($decimals !== null) {
			$this->numberFormat[self::NUMBER_FORMAT_DECIMALS] = (int) $decimals;
		}

		if ($decPoint !== null) {
			$this->numberFormat[self::NUMBER_FORMAT_DECIMAL_POINT] = $decPoint;
		}

		if ($thousandsSep !== null) {
			$this->numberFormat[self::NUMBER_FORMAT_THOUSANDS_SEPARATOR] = $thousandsSep;
		}

		return $this;
	}

	/**
	 * @return array
	 */
	public function getNumberFormat()
	{
		return $this->numberFormat;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	protected function formatValue($value)
	{
		$value = parent::formatValue($value);

		$decimals = $this->numberFormat[self::NUMBER_FORMAT_DECIMALS];
		$decPoint = $this->numberFormat[self::NUMBER_FORMAT_DECIMAL_POINT];
		$thousandsSep = $this->numberFormat[self::NUMBER_FORMAT_THOUSANDS_SEPARATOR];

		return is_numeric($value)
			? number_format($value, $decimals, $decPoint, $thousandsSep)
			: $value;
	}

}
