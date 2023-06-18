<?php

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2011 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido\Components\Filters;

use Grido\Exception;
use Nette\Application\Responses\JsonResponse;
use Nette\Forms\Controls\TextInput;
use function call_user_func_array;
use function current;
use function is_array;

/**
 * Text input filter.
 *
 * @property int $suggestionLimit
 * @property-write callback $suggestionCallback
 */
class Text extends Filter
{

	/** @var string */
	protected $condition = 'LIKE ?';

	/** @var string */
	protected $formatValue = '%%value%';

	/** @var bool */
	protected $suggestion = false;

	/** @var mixed */
	protected $suggestionColumn;

	/** @var int */
	protected $suggestionLimit = 10;

	/** @var callback */
	protected $suggestionCallback;

	/**
	 * Allows suggestion.
	 *
	 * @param mixed $column
	 * @return Text
	 */
	public function setSuggestion($column = null)
	{
		$this->suggestion = true;
		$this->suggestionColumn = $column;

		$prototype = $this->getControl()->getControlPrototype();
		$prototype->attrs['autocomplete'] = 'off';
		$prototype->class[] = 'suggest';

		$this->grid->onRender[] = function () use ($prototype) {
			$replacement = '-query-';
			$prototype->setAttribute('data-grido-suggest-replacement', $replacement);
			$prototype->setAttribute('data-grido-suggest-limit', $this->suggestionLimit);
			$prototype->setAttribute('data-grido-suggest-handler', $this->link('suggest!', [
				'query' => $replacement,
			]));
		};

		return $this;
	}

	/**
	 * Sets a limit for suggestion select.
	 *
	 * @param int $limit
	 * @return Text
	 */
	public function setSuggestionLimit($limit)
	{
		$this->suggestionLimit = (int) $limit;

		return $this;
	}

	/**
	 * Sets custom data callback.
	 *
	 * @param callback $callback
	 * @return Text
	 */
	public function setSuggestionCallback($callback)
	{
		$this->suggestionCallback = $callback;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getSuggestionLimit()
	{
		return $this->suggestionLimit;
	}

	/**
	 * @return callback
	 */
	public function getSuggestionCallback()
	{
		return $this->suggestionCallback;
	}

	/**
	 * @return string
	 */
	public function getSuggestionColumn()
	{
		return $this->suggestionColumn;
	}

	/**
	 * @param string $query - value from input
	 * @throws Exception
	 *
	 * @internal
	 */
	public function handleSuggest($query)
	{
		!empty($this->grid->onRegistered) && $this->grid->onRegistered($this->grid);
		$name = $this->getName();

		if (!$this->getPresenter()->isAjax() || !$this->suggestion || $query == '') {
			$this->getPresenter()->terminate();
		}

		$actualFilter = $this->grid->getActualFilter();
		if (isset($actualFilter[$name])) {
			unset($actualFilter[$name]);
		}

		$conditions = $this->grid->__getConditions($actualFilter);

		if ($this->suggestionCallback === null) {
			$conditions[] = $this->__getCondition($query);

			$column = $this->suggestionColumn ? $this->suggestionColumn : current($this->getColumn());
			$items = $this->grid->model->suggest($column, $conditions, $this->suggestionLimit);

		} else {
			$items = call_user_func_array($this->suggestionCallback, [$query, $actualFilter, $conditions, $this]);
			if (!is_array($items)) {
				throw new Exception('Items must be an array.');
			}
		}

		$this->getPresenter()->sendResponse(new JsonResponse($items));
	}

	/**
	 * @return TextInput
	 */
	protected function getFormControl()
	{
		$control = new TextInput($this->label);
		$control->getControlPrototype()->class[] = 'text';

		return $control;
	}

}
