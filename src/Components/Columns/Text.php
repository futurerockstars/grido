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

use Closure;
use Nette\Utils\Strings;

/**
 * Text column.
 */
class Text extends Editable
{

	/** @var Closure */
	protected $truncate;

	/**
	 * @param int $maxLen
	 * @param string $append UTF-8 encoding
	 * @return Column
	 */
	public function setTruncate($maxLen, $append = "\xE2\x80\xA6")
	{
		$this->truncate = fn ($string) => Strings::truncate($string, $maxLen, $append);

		return $this;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	protected function formatValue($value)
	{
		$value = parent::formatValue($value);

		if ($this->truncate) {
			$truncate = $this->truncate;
			$value = $truncate($value);
		}

		return $value;
	}

}
