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

use DirectoryIterator;
use Nette;
use function implode;
use function is_array;
use function realpath;

/**
 * Customization.
 *
 * @property string|array $buttonClass
 * @property string|array $iconClass
 * @property array $templateFiles
 */
class Customization
{

	use Nette\SmartObject;

	const TEMPLATE_DEFAULT = 'default';

	const TEMPLATE_BOOTSTRAP = 'bootstrap';

	/** @var Grid */
	protected $grid;

	/** @var string|array */
	protected $buttonClass;

	/** @var string|array */
	protected $iconClass;

	/** @var array */
	protected $templateFiles = [];

	public function __construct(Grid $grid)
	{
		$this->grid = $grid;
	}

	/**
	 * @param string|array $class
	 * @return Customization
	 */
	public function setButtonClass($class)
	{
		$this->buttonClass = $class;

		return $this;
	}

	/**
	 * @param string|array $class
	 * @return Customization
	 */
	public function setIconClass($class)
	{
		$this->iconClass = $class;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getButtonClass()
	{
		return is_array($this->buttonClass)
			? implode(' ', $this->buttonClass)
			: $this->buttonClass;
	}

	/**
	 * @param string $icon
	 * @return string
	 */
	public function getIconClass($icon = null)
	{
		if ($icon === null) {
			$class = $this->iconClass;
		} else {
			$this->iconClass = (array) $this->iconClass;
			$classes = [];
			foreach ($this->iconClass as $fontClass) {
				$classes[] = "{$fontClass} {$fontClass}-{$icon}";
			}

			$class = implode(' ', $classes);
		}

		return $class;
	}

	/**
	 * @return array
	 */
	public function getTemplateFiles()
	{
		if (empty($this->templateFiles)) {
			foreach (new DirectoryIterator(__DIR__ . '/templates') as $file) {
				if ($file->isFile()) {
					$this->templateFiles[$file->getBasename('.latte')] = realpath($file->getPathname());
				}
			}
		}

		return $this->templateFiles;
	}

	/**
	 * Default theme.
	 *
	 * @return Customization
	 */
	public function useTemplateDefault()
	{
		$this->grid->setTemplateFile($this->getTemplateFiles()[self::TEMPLATE_DEFAULT]);

		return $this;
	}

	/**
	 * Twitter Bootstrap theme.
	 *
	 * @return Customization
	 */
	public function useTemplateBootstrap()
	{
		$this->grid->setTemplateFile($this->getTemplateFiles()[self::TEMPLATE_BOOTSTRAP]);

		return $this;
	}

}
