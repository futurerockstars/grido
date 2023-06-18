<?php

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2011 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido;

use function str_replace;

/**
 * Helpers.
 */
class Helpers
{

	/**
	 * @param string $name
	 * @return string
	 */
	public static function formatColumnName($name)
	{
		return str_replace('.', '__', $name);
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public static function unformatColumnName($name)
	{
		return str_replace('__', '.', $name);
	}

}
