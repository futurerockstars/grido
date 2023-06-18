<?php

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2014 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido\Components\Columns;

use Grido\DataSources\Model;
use Grido\Exception;
use Grido\Grid;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\Responses\TextResponse;
use Nette\Forms\Controls\TextInput;
use Nette\Forms\IControl;
use Nette\Utils\Html;
use function call_user_func_array;
use function in_array;
use function is_callable;
use function is_string;
use function method_exists;
use function strpos;

/**
 * An inline editable column.
 *
 * @property IControl $editableControl
 * @property callback $editableCallback
 * @property callback $editableValueCallback
 * @property callback $editableRowCallback
 * @property bool $editable
 * @property bool $editableDisabled
 */
abstract class Editable extends Column
{

	/** @var bool */
	protected $editable = false;

	/** @var bool */
	protected $editableDisabled = false;

	/** @var IControl Custom control for inline editing */
	protected $editableControl;

	/** @var callback for custom handling with edited data; function($id, $newValue, $oldValue, Editable $column) {} */
	protected $editableCallback;

	/** @var callback for custom value; function($row, Columns\Editable $column) {} */
	protected $editableValueCallback;

	/** @var callback for getting row; function($row, Columns\Editable $column) {} */
	protected $editableRowCallback;

	/**
	 * Sets column as editable.
	 *
	 * @param callback $callback function($id, $newValue, $oldValue, Columns\Editable $column) {}
	 * @return Editable
	 */
	public function setEditable($callback = null, ?IControl $control = null)
	{
		$this->editable = true;
		$this->setClientSideOptions();

		$callback && $this->setEditableCallback($callback);
		$control && $this->setEditableControl($control);

		return $this;
	}

	/**
	 * Sets control for inline editation.
	 *
	 * @return Editable
	 */
	public function setEditableControl(IControl $control)
	{
		$this->isEditable() ?: $this->setEditable();
		$this->editableControl = $control;

		return $this;
	}

	/**
	 * Sets editable callback.
	 *
	 * @param callback $callback function($id, $newValue, $oldValue, Columns\Editable $column) {}
	 * @return Editable
	 */
	public function setEditableCallback($callback)
	{
		$this->isEditable() ?: $this->setEditable();
		$this->editableCallback = $callback;

		return $this;
	}

	/**
	 * Sets editable value callback.
	 *
	 * @param callback $callback for custom value; function($row, Columns\Editable $column) {}
	 * @return Editable
	 */
	public function setEditableValueCallback($callback)
	{
		$this->isEditable() ?: $this->setEditable();
		$this->editableValueCallback = $callback;

		return $this;
	}

	/**
	 * Sets editable row callback - it's required when used editable collumn with customRenderCallback
	 *
	 * @param callback $callback for getting row; function($id, Columns\Editable $column) {}
	 * @return Editable
	 */
	public function setEditableRowCallback($callback)
	{
		$this->isEditable() ?: $this->setEditable();
		$this->editableRowCallback = $callback;

		return $this;
	}

	/**
	 * @return Editable
	 */
	public function disableEditable()
	{
		$this->editable = false;
		$this->editableDisabled = true;

		return $this;
	}

	/**
	 * @throws Exception
	 */
	protected function setClientSideOptions()
	{
		$options = $this->grid->getClientSideOptions();
		if (!isset($options['editable'])) { //only once
			$this->grid->setClientSideOptions(['editable' => true]);
			$this->grid->onRender[] = function (Grid $grid) {
				foreach ($grid->getComponent(Column::ID)->getComponents() as $column) {
					if (!$column instanceof self || !$column->isEditable()) {
						continue;
					}

					$colDb = $column->getColumn();
					$colName = $column->getName();
					$isMissing = fn ($method) => $grid->model instanceof Model
							? !method_exists($grid->model->dataSource, $method)
							: true;

					if (($column->editableCallback === null && (!is_string($colDb) || strpos($colDb, '.'))) ||
						($column->editableCallback === null && $isMissing('update'))
					) {
						$msg = "Column '$colName' has error: You must define callback via setEditableCallback().";

						throw new Exception($msg);
					}

					if ($column->editableRowCallback === null && $column->customRender && $isMissing('getRow')) {
						$msg = "Column '$colName' has error: You must define callback via setEditableRowCallback().";

						throw new Exception($msg);
					}
				}
			};
		}
	}

	/**
	 * Returns header cell prototype (<th> html tag).
	 *
	 * @return Html
	 */
	public function getHeaderPrototype()
	{
		$th = parent::getHeaderPrototype();

		if ($this->isEditable()) {
			$th->setAttribute('data-grido-editable-handler', $this->link('editable!'));
			$th->setAttribute('data-grido-editableControl-handler', $this->link('editableControl!'));
		}

		return $th;
	}

	/**
	 * Returns cell prototype (<td> html tag).
	 *
	 * @param mixed $row
	 * @return Html
	 */
	public function getCellPrototype($row = null)
	{
		$td = parent::getCellPrototype($row);

		if ($this->isEditable() && $row !== null) {
			if (!in_array('editable', $td->class)) {
				$td->class[] = 'editable';
			}

			$value = $this->editableValueCallback === null
				? $this->getValue($row)
				: call_user_func_array($this->editableValueCallback, [$row, $this]);

			$td->setAttribute('data-grido-editable-value', $value);
		}

		return $td;
	}

	/**
	 * Returns control for editation.
	 *
	 * @returns \Nette\Forms\Controls\TextInput
	 */
	public function getEditableControl()
	{
		if ($this->editableControl === null) {
			$this->editableControl = new TextInput();
			$this->editableControl->controlPrototype->class[] = 'form-control';
		}

		return $this->editableControl;
	}

	/**
	 * @return callback
	 *
	 * @internal
	 */
	public function getEditableCallback()
	{
		return $this->editableCallback;
	}

	/**
	 * @return callback
	 *
	 * @internal
	 */
	public function getEditableValueCallback()
	{
		return $this->editableValueCallback;
	}

	/**
	 * @return callback
	 *
	 * @internal
	 */
	public function getEditableRowCallback()
	{
		return $this->editableRowCallback;
	}

	/**
	 * @return bool
	 *
	 * @internal
	 */
	public function isEditable()
	{
		return $this->editable;
	}

	/**
	 * @return bool
	 *
	 * @internal
	 */
	public function isEditableDisabled()
	{
		return $this->editableDisabled;
	}

	/**
	 * @internal
	 */
	public function handleEditable($id, $newValue, $oldValue)
	{
		$this->grid->onRender($this->grid);

		if (!$this->presenter->isAjax() || !$this->isEditable()) {
			$this->presenter->terminate();
		}

		$success = $this->editableCallback
			? call_user_func_array($this->editableCallback, [$id, $newValue, $oldValue, $this])
			: $this->grid->model->update($id, [$this->getColumn() => $newValue], $this->grid->primaryKey);

		if (is_callable($this->customRender)) {
			$row = $this->editableRowCallback
				? call_user_func_array($this->editableRowCallback, [$id, $this])
				: $this->grid->model->getRow($id, $this->grid->primaryKey);
			$html = call_user_func_array($this->customRender, [$row]);
		} else {
			$html = $this->formatValue($newValue);
		}

		$payload = ['updated' => (bool) $success, 'html' => (string) $html];
		$response = new JsonResponse($payload);
		$this->presenter->sendResponse($response);
	}

	/**
	 * @internal
	 */
	public function handleEditableControl($value)
	{
		$this->grid->onRender($this->grid);

		if (!$this->presenter->isAjax() || !$this->isEditable()) {
			$this->presenter->terminate();
		}

		$control = $this->getEditableControl();
		$control->setValue($value);

		$this->getForm()->addComponent($control, 'edit' . $this->getName());

		$response = new TextResponse($control->getControl()->render());
		$this->presenter->sendResponse($response);
	}

}
