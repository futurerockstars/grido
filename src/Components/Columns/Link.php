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
use function preg_match;
use function preg_replace;

/**
 * Link column.
 */
class Link extends Text
{

	/**
	 * @param mixed $value
	 * @return Html
	 */
	protected function formatValue($value)
	{
		return $this->getAnchor($value);
	}

	/**
	 * @param string $value
	 * @return string
	 */
	protected function formatHref($value)
	{
		if (!preg_match('~^\w+://~i', $value)) {
			$value = 'http://' . $value;
		}

		return $value;
	}

	/**
	 * @param string $value
	 * @return string
	 */
	protected function formatText($value)
	{
		return preg_replace('~^https?://~i', '', $value);
	}

	/**
	 * @param mixed $value
	 * @return Html
	 */
	protected function getAnchor($value)
	{
		$truncate = $this->truncate;
		$this->truncate = null;

		$value = parent::formatValue($value);
		$href = $this->formatHref($value);
		$text = $this->formatText($value);

		$anchor = Html::el('a')
			->setHref($href)
			->setText($text)
			->setTarget('_blank')
			->setRel('noreferrer');

		if ($truncate) {
			$anchor->setText($truncate($text))
				->setTitle($value);
		}

		return $anchor;
	}

}
