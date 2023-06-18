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

use Grido\Components\Filters\Condition;
use Grido\Exception;
use Latte\Runtime\Filters;
use Nette;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use function array_values;
use function call_user_func_array;
use function gettype;
use function is_callable;
use function is_string;
use function sort;

/**
 * Nette Database data source.
 *
 * @property-read Selection $selection
 * @property-read int $count
 * @property-read array $data
 */
class NetteDatabase implements IDataSource
{

	use Nette\SmartObject;

	/** @var Selection */
	protected $selection;

	public function __construct(Selection $selection)
	{
		$this->selection = $selection;
	}

	/**
	 * @return Selection
	 */
	public function getSelection()
	{
		return $this->selection;
	}

	protected function makeWhere(Condition $condition, ?Selection $selection = null)
	{
		$selection ??= $this->selection;

		if ($condition->callback) {
			call_user_func_array($condition->callback, [$condition->value, $selection]);
		} else {
			call_user_func_array([$selection, 'where'], $condition->__toArray());
		}
	}

	/********************************** inline editation helpers ************************************/

	/**
	 * Default callback for an inline editation save.
	 *
	 * @param mixed $id
	 * @param array $values
	 * @param string $idCol
	 * @return bool
	 */
	public function update($id, array $values, $idCol)
	{
		return (bool) $this->getSelection()
			->where('?name = ?', $idCol, $id)
			->update($values);
	}

	/**
	 * Default callback used when an editable column has customRender.
	 *
	 * @param mixed $id
	 * @param string $idCol
	 * @return ActiveRow|bool
	 */
	public function getRow($id, $idCol)
	{
		return $this->getSelection()
			->where('?name = ?', $idCol, $id)
			->fetch();
	}

	/********************************** interface IDataSource ************************************/

	/**
	 * @return int
	 */
	public function getCount()
	{
		return (int) $this->selection->count('*');
	}

	/**
	 * @return array
	 */
	public function getData()
	{
		return $this->selection;
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
		$this->selection->limit($limit, $offset);
	}

	/**
	 * @param array $sorting
	 */
	public function sort(array $sorting)
	{
		foreach ($sorting as $column => $sort) {
			$this->selection->order("$column $sort");
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
		$selection = clone $this->selection;
		is_string($column) && $selection->select("DISTINCT $column")->order($column);
		$selection->limit($limit);

		foreach ($conditions as $condition) {
			$this->makeWhere($condition, $selection);
		}

		$items = [];
		foreach ($selection as $row) {
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
