<?php

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2011 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido\DataSources;

use Dibi\Fluent;
use Doctrine\ORM\QueryBuilder;
use Grido\Exception;
use Nette;
use Nette\Database\Table\Selection;
use function call_user_func_array;
use function is_array;

/**
 * Model of data source.
 *
 * @property-read IDataSource $dataSource
 */
class Model
{

	use Nette\SmartObject;

	/** @var array */
	public $callback = [];

	/** @var IDataSource */
	protected $dataSource;

	/**
	 * @param mixed $model
	 * @throws Exception
	 */
	public function __construct($model)
	{
		if ($model instanceof Fluent) {
			$dataSource = new DibiFluent($model);
		} elseif ($model instanceof Selection) {
			$dataSource = new NetteDatabase($model);
		} elseif ($model instanceof QueryBuilder) {
			$dataSource = new Doctrine($model);
		} elseif (is_array($model)) {
			$dataSource = new ArraySource($model);
		} elseif ($model instanceof IDataSource) {
			$dataSource = $model;
		} else {
			throw new Exception('Model must implement \Grido\DataSources\IDataSource.');
		}

		$this->dataSource = $dataSource;
	}

	/**
	 * @return \IDataSource
	 */
	public function getDataSource()
	{
		return $this->dataSource;
	}

	public function __call($method, $args)
	{
		return isset($this->callback[$method])
			? call_user_func_array($this->callback[$method], [$this->dataSource, $args])
			: call_user_func_array([$this->dataSource, $method], $args);
	}

}
