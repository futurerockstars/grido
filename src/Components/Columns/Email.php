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

use Nette\Utils\Html;

/**
 * Email column.
 */
class Email extends Link
{

	/**
	 * @param string $value
	 * @return string
	 */
	protected function formatHref($value)
	{
		return 'mailto:' . $value;
	}

	/**
	 * @param mixed $value
	 * @return Html
	 */
	protected function getAnchor($value)
	{
		$anchor = parent::getAnchor($value);
		unset($anchor->attrs['target']);
		unset($anchor->attrs['rel']);

		return $anchor;
	}

}
