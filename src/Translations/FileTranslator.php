<?php

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2011 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido\Translations;

use Grido\Exception;
use Nette;
use Nette\Localization\ITranslator;
use function file_exists;

/**
 * Simple file translator.
 *
 * @property string $lang
 */
class FileTranslator implements ITranslator
{

	use Nette\SmartObject;

	/** @var array */
	protected $translations = [];

	/**
	 * @param string $lang
	 * @param array $translations
	 */
	public function __construct($lang = 'en', array $translations = [])
	{
		$translations += $this->getTranslationsFromFile($lang);
		$this->translations = $translations;
	}

	/**
	 * Sets language of translation.
	 *
	 * @param string $lang
	 */
	public function setLang($lang)
	{
		$this->translations = $this->getTranslationsFromFile($lang);
	}

	/**
	 * @param string $lang
	 * @return array
	 * @throws Exception
	 */
	protected function getTranslationsFromFile($lang)
	{
		$filename = __DIR__ . "/$lang.php";
		if (!file_exists($filename)) {
			throw new Exception("Translations for language '$lang' not found.");
		}

		return include $filename;
	}

	/************************* interface \Nette\Localization\ITranslator **************************/

	public function translate($message, ...$parameters): string
	{
		return $this->translations[$message] ?? (string) $message;
	}

}
