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
use Dibi\Row;
use Grido\Components\Filters\Condition;
use Grido\Exception;
use Latte\Runtime\Filters;
use Nette;
use function array_values;
use function call_user_func_array;
use function gettype;
use function is_callable;
use function is_string;
use function sort;

/**
 * Dibi Fluent data source.
 *
 * @property-read Fluent $fluent
 * @property-read int $limit
 * @property-read int $offset
 * @property-read int $count
 * @property-read array $data
 */
class DibiFluent implements IDataSource
{

	use Nette\SmartObject;

	/** @var Fluent */
	protected $fluent;

	/** @var int */
	protected $limit;

	/** @var int */
	protected $offset;

	public function __construct(Fluent $fluent)
	{
		$this->fluent = $fluent;
	}

	/**
	 * @return Fluent
	 */
	public function getFluent()
	{
		return $this->fluent;
	}

	/**
	 * @return int
	 */
	public function getLimit()
	{
		return $this->limit;
	}

	/**
	 * @return int
	 */
	public function getOffset()
	{
		return $this->offset;
	}

	protected function makeWhere(Condition $condition, ?Fluent $fluent = null)
	{
		$fluent ??= $this->fluent;

		if ($condition->callback) {
			call_user_func_array($condition->callback, [$condition->value, $fluent]);
		} else {
			call_user_func_array([$fluent, 'where'], $condition->__toArray('[', ']'));
		}
	}

	/********************************** inline editation helpers ************************************/

	/**
	 * Default callback used when an editable column has customRender.
	 *
	 * @param mixed $id
	 * @param string $idCol
	 * @return Row
	 */
	public function getRow($id, $idCol)
	{
		$fluent = clone $this->fluent;

		return $fluent
			->where('%n = %s', $idCol, $id)
			->fetch();
	}

	/*********************************** interface IDataSource ************************************/

	/**
	 * @return int
	 */
	public function getCount()
	{
		$fluent = clone $this->fluent;

		return $fluent->count();
	}

	/**
	 * @return array
	 */
	public function getData()
	{
		return $this->fluent->fetchAll($this->offset, $this->limit);
	}

	/**
	 * @param array $conditions
	 */
	public function filter(array $conditions)
	{
		foreach ($conditions as $condition) {
			$this->makeWhere($condition);
		}
	}

	/**
	 * @param int $offset
	 * @param int $limit
	 */
	public function limit($offset, $limit)
	{
		$this->offset = $offset;
		$this->limit = $limit;
	}

	/**
	 * @param array $sorting
	 */
	public function sort(array $sorting)
	{
		foreach ($sorting as $column => $sort) {
			$this->fluent->orderBy('%n', $column, $sort);
		}
	}

	/**
	 * @param mixed $column
	 * @param array $conditions
	 * @param int $limit
	 * @return array
	 * @throws Exception
	 */
	public function suggest($column, array $conditions, $limit)
	{
		$fluent = clone $this->fluent;
		if (is_string($column)) {
			$fluent->removeClause('SELECT')->select('DISTINCT %n', $column)->orderBy('%n', $column, 'ASC');
		}

		foreach ($conditions as $condition) {
			$this->makeWhere($condition, $fluent);
		}

		$items = [];
		$data = $fluent->fetchAll(0, $limit);
		foreach ($data as $row) {
			if (is_string($column)) {
				$value = (string) $row[$column];
			} elseif (is_callable($column)) {
				$value = (string) $column($row);
			} else {
				$type = gettype($column);

				throw new Exception("Column of suggestion must be string or callback, $type given.");
			}

			$items[$value] = Filters::escapeHtml($value);
		}

		is_callable($column) && sort($items);

		return array_values($items);
	}

}
