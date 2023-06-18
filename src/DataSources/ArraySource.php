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
use Nette\Utils\Strings;
use function array_filter;
use function array_slice;
use function array_values;
use function call_user_func_array;
use function count;
use function current;
use function gettype;
use function implode;
use function is_callable;
use function is_string;
use function krsort;
use function ksort;
use function preg_match;
use function preg_quote;
use function sort;
use function str_replace;

/**
 * Array data source.
 *
 * @property-read array $data
 * @property-read int $count
 */
class ArraySource implements IDataSource
{

	use Nette\SmartObject;

	/** @var array */
	protected $data;

	/**
	 * @param array $data
	 */
	public function __construct(array $data)
	{
		$this->data = $data;
	}

	/**
	 * This method needs tests!
	 *
	 * @param array $data
	 * @return array
	 */
	protected function makeWhere(Condition $condition, ?array $data = null)
	{
		$data ??= $this->data;

		return array_filter($data, function ($row) use ($condition) {
			if ($condition->callback) {
				return call_user_func_array($condition->callback, [$condition->value, $row]);
			}

			$i = 0;
			$results = [];
			foreach ($condition->column as $column) {
				if (Condition::isOperator($column)) {
					$results[] = " $column ";

				} else {
					$i = count($condition->condition) > 1 ? $i : 0;
					$results[] = (int) $this->compare(
						$row[$column],
						$condition->condition[$i],
						$condition->value[$i] ?? null,
					);

					$i++;
				}
			}

			$result = implode('', $results);

			return count($condition->column) === 1
				? (bool) $result
				: eval("return $result;"); // QUESTION: How to remove this eval? hmmm?
		});
	}

	/**
	 * @param string $actual
	 * @param string $condition
	 * @param mixed $expected
	 * @return bool
	 * @throws Exception
	 */
	public function compare($actual, $condition, $expected)
	{
		$expected = (array) $expected;
		$expected = current($expected);
		$cond = str_replace(' ?', '', $condition);

		if ($cond === 'LIKE') {
			$actual = Strings::toAscii($actual);
			$expected = Strings::toAscii($expected);

			$pattern = str_replace('%', '(.|\s)*', preg_quote($expected, '/'));

			return (bool) preg_match("/^{$pattern}$/i", $actual);
		} elseif ($cond === '=') {
			return $actual == $expected;
		} elseif ($cond === '<>') {
			return $actual != $expected;
		} elseif ($cond === 'IS NULL') {
			return $actual === null;
		} elseif ($cond === 'IS NOT NULL') {
			return $actual !== null;
		} elseif ($cond === '<') {
			return (int) $actual < $expected;
		} elseif ($cond === '<=') {
			return (int) $actual <= $expected;
		} elseif ($cond === '>') {
			return (int) $actual > $expected;
		} elseif ($cond === '>=') {
			return (int) $actual >= $expected;
		} else {
			throw new Exception("Condition '$condition' is not implemented yet.");
		}
	}

	/*********************************** interface IDataSource ************************************/

	/**
	 * @return int
	 */
	public function getCount()
	{
		return count($this->data);
	}

	/**
	 * @return array
	 */
	public function getData()
	{
		return $this->data;
	}

	/**
	 * @param array $conditions
	 */
	public function filter(array $conditions)
	{
		foreach ($conditions as $condition) {
			$this->data = $this->makeWhere($condition);
		}
	}

	/**
	 * @param int $offset
	 * @param int $limit
	 */
	public function limit($offset, $limit)
	{
		$this->data = array_slice($this->data, $offset, $limit);
	}

	/**
	 * @param array $sorting
	 * @throws Exception
	 */
	public function sort(array $sorting)
	{
		if (count($sorting) > 1) {
			throw new Exception('Multi-column sorting is not implemented yet.');
		}

		foreach ($sorting as $column => $sort) {
			$data = [];
			foreach ($this->data as $item) {
				$sorter = (string) $item[$column];
				$data[$sorter][] = $item;
			}

			if ($sort === 'ASC') {
				ksort($data);
			} else {
				krsort($data);
			}

			$this->data = [];
			foreach ($data as $i) {
				foreach ($i as $item) {
					$this->data[] = $item;
				}
			}
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
		$data = $this->data;
		foreach ($conditions as $condition) {
			$data = $this->makeWhere($condition, $data);
		}

		array_slice($data, 1, $limit);

		$items = [];
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

		sort($items);

		return array_values($items);
	}

}
