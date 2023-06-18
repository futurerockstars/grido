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

use Grido\Components\Actions\Action;
use Grido\Components\Button;
use Grido\Components\Columns\Column;
use Grido\Components\Filters\Filter;
use Grido\Components\Paginator;
use Nette\Application\UI\Form;
use Nette\Application\UI\PresenterComponentReflection;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Forms\Controls\SubmitButton;
use Nette\Http\SessionSection;
use Nette\InvalidArgumentException;
use Nette\Localization\ITranslator;
use Nette\Templating\FileTemplate;
use Nette\Utils\Html;
use Nette\Utils\Strings;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Throwable;
use function array_combine;
use function array_keys;
use function array_merge;
use function call_user_func_array;
use function explode;
use function func_get_args;
use function in_array;
use function is_array;
use function is_numeric;
use function is_object;
use function is_string;
use function json_encode;
use function range;
use function sort;
use function strtolower;
use function strtr;
use function trigger_error;
use const E_USER_NOTICE;

/**
 * Grido - DataGrid for Nette Framework.
 *
 * @property-read int                        $count
 * @property-read mixed                      $data
 * @property-read Html $tablePrototype
 * @property-read PropertyAccessor           $propertyAccessor
 * @property-read Customization              $customization
 * @property-write string                    $templateFile
 * @property bool                            $rememberState
 * @property array                           $defaultPerPage
 * @property array                           $defaultFilter
 * @property array                           $defaultSort
 * @property array                           $perPageList
 * @property ITranslator $translator
 * @property Paginator                       $paginator
 * @property string                          $primaryKey
 * @property string                          $filterRenderType
 * @property DataSources\IDataSource         $model
 * @property callback                        $rowCallback
 * @property bool                            $strictMode
 * @method void onRegistered(Grid $grid)
 * @method void onRender(Grid $grid)
 * @method void onFetchData(Grid $grid)
 */
class Grid extends Components\Container
{

	/***** DEFAULTS ****/
	const BUTTONS = 'buttons';

	const CLIENT_SIDE_OPTIONS = 'grido-options';

	/** @var int @persistent */
	public $page = 1;

	/** @var int|null @persistent */
	public $perPage;

	/** @var array @persistent */
	public $sort = [];

	/** @var array @persistent */
	public $filter = [];

	/** @var array event on all grid's components registered */
	public $onRegistered;

	/** @var array event on render */
	public $onRender;

	/** @var array event for modifying data */
	public $onFetchData;

	/** @var callback returns tr html element; function($row, Html $tr) */
	protected $rowCallback;

	/** @var Html */
	protected $tablePrototype;

	/** @var bool */
	protected $rememberState = false;

	/** @var string */
	protected $rememberStateSectionName;

	/** @var string */
	protected $primaryKey = 'id';

	/** @var string */
	protected $filterRenderType;

	/** @var array */
	protected $perPageList = [10, 20, 30, 50, 100];

	/** @var int */
	protected $defaultPerPage = 20;

	/** @var array */
	protected $defaultFilter = [];

	/** @var array */
	protected $defaultSort = [];

	/** @var DataSources\IDataSource */
	protected $model;

	/** @var int total count of items */
	protected $count;

	/** @var mixed */
	protected $data;

	/** @var Paginator */
	protected $paginator;

	/** @var ITranslator */
	protected $translator;

	/** @var PropertyAccessor */
	protected $propertyAccessor;

	/** @var bool */
	protected $strictMode = true;

	/** @var array */
	protected $options = [
		self::CLIENT_SIDE_OPTIONS => [],
	];

	/** @var Customization */
	protected $customization;

	public function __construct()
	{
		parent::__construct();
		[$parent, $name] = func_get_args() + [null, null];
		if ($parent !== null) {
			$parent->addComponent($this, $name);

		} elseif (is_string($name)) {
			$this->name = $name;
		}
	}

	/**
	 * Sets a model that implements the interface Grido\DataSources\IDataSource or data-source object.
	 *
	 * @param mixed $model
	 * @param bool  $forceWrapper
	 * @return Grid
	 * @throws Exception
	 */
	public function setModel($model, $forceWrapper = false)
	{
		$this->model = $model instanceof DataSources\IDataSource && $forceWrapper === false
			? $model
			: new DataSources\Model($model);

		return $this;
	}

	/**
	 * Sets the default number of items per page.
	 *
	 * @param int $perPage
	 * @return Grid
	 */
	public function setDefaultPerPage($perPage)
	{
		$perPage = (int) $perPage;
		$this->defaultPerPage = $perPage;

		if (!in_array($perPage, $this->perPageList)) {
			$this->perPageList[] = $perPage;
			sort($this->perPageList);
		}

		return $this;
	}

	/**
	 * Sets default filtering.
	 *
	 * @param array $filter
	 * @return Grid
	 */
	public function setDefaultFilter(array $filter)
	{
		$this->defaultFilter = array_merge($this->defaultFilter, $filter);

		return $this;
	}

	/**
	 * Sets default sorting.
	 *
	 * @param array $sort
	 * @return Grid
	 * @throws Exception
	 */
	public function setDefaultSort(array $sort)
	{
		static $replace = ['asc' => Column::ORDER_ASC, 'desc' => Column::ORDER_DESC];

		foreach ($sort as $column => $dir) {
			$dir = strtr(strtolower($dir), $replace);
			if (!in_array($dir, $replace)) {
				throw new Exception("Dir '$dir' for column '$column' is not allowed.");
			}

			$this->defaultSort[$column] = $dir;
		}

		return $this;
	}

	/**
	 * Sets items to per-page select.
	 *
	 * @param array $perPageList
	 * @return Grid
	 */
	public function setPerPageList(array $perPageList)
	{
		$this->perPageList = $perPageList;

		if ($this->hasFilters(false) || $this->hasOperation(false)) {
			$this['form']['count']->setItems($this->getItemsForCountSelect());
		}

		return $this;
	}

	/**
	 * Sets translator.
	 *
	 * @return Grid
	 */
	public function setTranslator(ITranslator $translator)
	{
		$this->translator = $translator;

		return $this;
	}

	/**
	 * Sets type of filter rendering.
	 * Defaults inner (Filter::RENDER_INNER) if column does not exist then outer filter (Filter::RENDER_OUTER).
	 *
	 * @param string $type
	 * @return Grid
	 * @throws Exception
	 */
	public function setFilterRenderType($type)
	{
		$type = strtolower($type);
		if (!in_array($type, [Filter::RENDER_INNER, Filter::RENDER_OUTER])) {
			throw new Exception('Type must be Filter::RENDER_INNER or Filter::RENDER_OUTER.');
		}

		$this->filterRenderType = $type;

		return $this;
	}

	/**
	 * Sets custom paginator.
	 *
	 * @return Grid
	 */
	public function setPaginator(Paginator $paginator)
	{
		$this->paginator = $paginator;

		return $this;
	}

	/**
	 * Sets grid primary key.
	 * Defaults is "id".
	 *
	 * @param string $key
	 * @return Grid
	 */
	public function setPrimaryKey($key)
	{
		$this->primaryKey = $key;

		return $this;
	}

	/**
	 * Sets file name of custom template.
	 *
	 * @param string $file
	 * @return Grid
	 */
	public function setTemplateFile($file)
	{
		$this->onRender[] = function () use ($file) {
			$this->getTemplate()->add('gridoTemplate', $this->getTemplate()->getFile());
			$this->getTemplate()->setFile($file);
		};

		return $this;
	}

	/**
	 * Sets saving state to session.
	 *
	 * @param bool   $state
	 * @param string $sectionName
	 * @return Grid
	 */
	public function setRememberState($state = true, $sectionName = null)
	{
		$this->getPresenter(); //component must be attached to presenter
		$this->getRememberSession(true); //start session if not
		$this->rememberState = (bool) $state;
		$this->rememberStateSectionName = $sectionName;

		return $this;
	}

	/**
	 * Sets callback for customizing tr html object.
	 * Callback returns tr html element; function($row, Html $tr).
	 *
	 * @param callable $callback
	 * @return Grid
	 */
	public function setRowCallback($callback)
	{
		$this->rowCallback = $callback;

		return $this;
	}

	/**
	 * Sets client-side options.
	 *
	 * @param array $options
	 * @return Grid
	 */
	public function setClientSideOptions(array $options)
	{
		$this->options[self::CLIENT_SIDE_OPTIONS] = $options;

		return $this;
	}

	/**
	 * Determines whether any user error will cause a notice.
	 *
	 * @param bool $mode
	 * @return Grid
	 */
	public function setStrictMode($mode)
	{
		$this->strictMode = (bool) $mode;

		return $this;
	}

	public function setCustomization(Customization $customization)
	{
		$this->customization = $customization;
	}

	/**
	 * Returns total count of data.
	 *
	 * @return int
	 */
	public function getCount()
	{
		if ($this->count === null) {
			$this->count = $this->getModel()->getCount();
		}

		return $this->count;
	}

	/**
	 * Returns default per page.
	 *
	 * @return int
	 */
	public function getDefaultPerPage()
	{
		if (!in_array($this->defaultPerPage, $this->perPageList)) {
			$this->defaultPerPage = $this->perPageList[0];
		}

		return $this->defaultPerPage;
	}

	/**
	 * Returns default filter.
	 *
	 * @return array
	 */
	public function getDefaultFilter()
	{
		return $this->defaultFilter;
	}

	/**
	 * Returns default sort.
	 *
	 * @return array
	 */
	public function getDefaultSort()
	{
		return $this->defaultSort;
	}

	/**
	 * Returns list of possible items per page.
	 *
	 * @return array
	 */
	public function getPerPageList()
	{
		return $this->perPageList;
	}

	/**
	 * Returns primary key.
	 *
	 * @return string
	 */
	public function getPrimaryKey()
	{
		return $this->primaryKey;
	}

	/**
	 * Returns remember state.
	 *
	 * @return bool
	 */
	public function getRememberState()
	{
		return $this->rememberState;
	}

	/**
	 * Returns row callback.
	 *
	 * @return callback
	 */
	public function getRowCallback()
	{
		return $this->rowCallback;
	}

	/**
	 * Returns items per page.
	 *
	 * @return int
	 */
	public function getPerPage()
	{
		return $this->perPage ?? $this->getDefaultPerPage();
	}

	/**
	 * Returns actual filter values.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function getActualFilter($key = null)
	{
		$filter = $this->filter ? $this->filter : $this->defaultFilter;

		return $key !== null && isset($filter[$key]) ? $filter[$key] : $filter;
	}

	/**
	 * Returns fetched data.
	 *
	 * @param bool $applyPaging
	 * @param bool $useCache
	 * @param bool $fetch
	 * @return array|DataSources\IDataSource|Selection
	 * @throws Exception
	 */
	public function getData($applyPaging = true, $useCache = true, $fetch = true)
	{
		if ($this->getModel() === null) {
			throw new Exception('Model cannot be empty, please use method $grid->setModel().');
		}

		$data = $this->data;
		if ($data === null || $useCache === false) {
			$this->applyFiltering();
			$this->applySorting();

			if ($applyPaging) {
				$this->applyPaging();
			}

			if ($fetch === false) {
				return $this->getModel();
			}

			$data = $this->getModel()->getData();

			$this->data = $useCache ? $data : null;

			if ($applyPaging && !empty($data) && !in_array($this->page, range(1, $this->getPaginator()->pageCount))) {
				$this->__triggerUserNotice('Page is out of range.');
				$this->page = 1;
			}

			if (!empty($this->onFetchData)) {
				$this->onFetchData($this);
			}
		}

		return $data;
	}

	/**
	 * Returns translator.
	 *
	 * @return Translations\FileTranslator
	 */
	public function getTranslator()
	{
		if ($this->translator === null) {
			$this->setTranslator(new Translations\FileTranslator());
		}

		return $this->translator;
	}

	/**
	 * Returns remember session for set expiration, etc.
	 *
	 * @param bool $forceStart - if TRUE, session will be started if not
	 * @return SessionSection|NULL
	 */
	public function getRememberSession($forceStart = false)
	{
		$presenter = $this->getPresenter();
		$session = $presenter->getSession();

		if (!$session->isStarted() && $forceStart) {
			$session->start();
		}

		return $session->isStarted()
			? ($session->getSection($this->rememberStateSectionName ?: ($presenter->name . ':' . $this->getUniqueId())))
			: null;
	}

	/**
	 * Returns table html element of grid.
	 *
	 * @return Html
	 */
	public function getTablePrototype()
	{
		if ($this->tablePrototype === null) {
			$this->tablePrototype = Html::el('table');
			$this->tablePrototype->id($this->getName());
		}

		return $this->tablePrototype;
	}

	/**
	 * @return string
	 *
	 * @internal
	 */
	public function getFilterRenderType()
	{
		if ($this->filterRenderType !== null) {
			return $this->filterRenderType;
		}

		$this->filterRenderType = Filter::RENDER_OUTER;
		if ($this->hasColumns() && $this->hasFilters() && $this->hasActions()) {
			$this->filterRenderType = Filter::RENDER_INNER;

			$filters = $this[Filter::ID]->getComponents();
			foreach ($filters as $filter) {
				if (!$this[Column::ID]->getComponent($filter->name, false)) {
					$this->filterRenderType = Filter::RENDER_OUTER;

					break;
				}
			}
		}

		return $this->filterRenderType;
	}

	/**
	 * @return DataSources\IDataSource
	 */
	public function getModel()
	{
		return $this->model;
	}

	/**
	 * @return Paginator
	 *
	 * @internal
	 */
	public function getPaginator()
	{
		if ($this->paginator === null) {
			$this->paginator = new Paginator();
			$this->paginator->setItemsPerPage($this->getPerPage())
				->setGrid($this);
		}

		return $this->paginator;
	}

	/**
	 * A simple wrapper around symfony/property-access with Nette Database dot notation support.
	 *
	 * @param array|object $object
	 * @param string       $name
	 * @return mixed
	 *
	 * @internal
	 */
	public function getProperty($object, $name)
	{
		if ($object instanceof IRow && Strings::contains($name, '.')) {
			$parts = explode('.', $name);
			foreach ($parts as $item) {
				if (is_object($object)) {
					$object = $object->$item;
				}
			}

			return $object;
		}

		if (is_array($object)) {
			$name = "[$name]";
		}

		return $this->getPropertyAccessor()->getValue($object, $name);
	}

	/**
	 * @return PropertyAccessor
	 *
	 * @internal
	 */
	public function getPropertyAccessor()
	{
		if ($this->propertyAccessor === null) {
			$this->propertyAccessor = new PropertyAccessor(
				PropertyAccessor::MAGIC_CALL | PropertyAccessor::MAGIC_GET | PropertyAccessor::MAGIC_SET,
				PropertyAccessor::THROW_ON_INVALID_INDEX | PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH,
			);
		}

		return $this->propertyAccessor;
	}

	/**
	 * @param mixed $row item from db
	 * @return Html
	 *
	 * @internal
	 */
	public function getRowPrototype($row)
	{
		try {
			$primaryValue = $this->getProperty($row, $this->getPrimaryKey());
		} catch (Throwable $e) {
			$primaryValue = null;
		}

		$tr = Html::el('tr');
		$primaryValue ? $tr->class[] = "grid-row-$primaryValue" : null;

		if ($this->rowCallback) {
			$tr = call_user_func_array($this->rowCallback, [$row, $tr]);
		}

		return $tr;
	}

	/**
	 * Returns client-side options.
	 *
	 * @return array
	 */
	public function getClientSideOptions()
	{
		return (array) $this->options[self::CLIENT_SIDE_OPTIONS];
	}

	/**
	 * @return bool
	 */
	public function isStrictMode()
	{
		return $this->strictMode;
	}

	/**
	 * @return Customization
	 */
	public function getCustomization()
	{
		if ($this->customization === null) {
			$this->customization = new Customization($this);
		}

		return $this->customization;
	}

	/**
	 * Loads state informations.
	 *
	 * @param array $params
	 *
	 * @internal
	 */
	public function loadState(array $params)
	{
		//loads state from session
		$session = $this->getRememberSession();
		if ($session && $this->getPresenter()->isSignalReceiver($this)) {
			$session->remove();
		} elseif ($session && empty($params) && $session->params) {
			$params = (array) $session->params;
		}

		parent::loadState($params);
	}

	/**
	 * Saves state informations for next request.
	 *
	 * @param array                                              $params
	 * @param PresenterComponentReflection $reflection (internal, used by Presenter)
	 *
	 * @internal
	 */
	public function saveState(array &$params, $reflection = null)
	{
		!empty($this->onRegistered) && $this->onRegistered($this);

		return parent::saveState($params, $reflection);
	}

	/**
	 * Ajax method.
	 *
	 * @internal
	 */
	public function handleRefresh()
	{
		$this->reload();
	}

	/**
	 * @param int $page
	 *
	 * @internal
	 */
	public function handlePage($page)
	{
		$this->reload();
	}

	/**
	 * @param array $sort
	 *
	 * @internal
	 */
	public function handleSort(array $sort)
	{
		$this->page = 1;
		$this->reload();
	}

	/**
	 * @internal
	 */
	public function handleFilter(SubmitButton $button)
	{
		$values = $button->form->values[Filter::ID];
		$session = $this->rememberState //session filter
			? $this->getRememberSession(true)->params['filter'] ?? []
			: [];

		foreach ($values as $name => $value) {
			if (is_numeric($value) || !empty($value) || isset($this->defaultFilter[$name]) || isset($session[$name])) {
				$this->filter[$name] = $this->getFilter($name)->changeValue($value);
			} elseif (isset($this->filter[$name])) {
				unset($this->filter[$name]);
			}
		}

		$this->page = 1;
		$this->reload();
	}

	/**
	 * @internal
	 */
	public function handleReset(SubmitButton $button)
	{
		$this->sort = [];
		$this->filter = [];
		$this->perPage = null;

		if ($session = $this->getRememberSession()) {
			$session->remove();
		}

		$button->form->setValues([Filter::ID => $this->defaultFilter], true);

		$this->page = 1;
		$this->reload();
	}

	/**
	 * @internal
	 */
	public function handlePerPage(SubmitButton $button)
	{
		$perPage = (int) $button->form['count']->value;
		$this->perPage = $perPage == $this->defaultPerPage
			? null
			: $perPage;

		$this->page = 1;
		$this->reload();
	}

	/**
	 * Refresh wrapper.
	 *
	 * @return void
	 *
	 * @internal
	 */
	public function reload()
	{
		if ($this->presenter->isAjax()) {
			$this->presenter->payload->grido = true;
			$this->redrawControl();
		} else {
			$this->redirect('this');
		}
	}

	/**
	 * @return FileTemplate
	 *
	 * @internal
	 */
	public function createTemplate()
	{
		$template = parent::createTemplate();
		$template->setFile($this->getCustomization()->getTemplateFiles()[Customization::TEMPLATE_DEFAULT]);
		$template->getLatte()->addFilter('translate', [$this->getTranslator(), 'translate']);

		return $template;
	}

	/**
	 * @throws Exception
	 *
	 * @internal
	 */
	public function render()
	{
		if (!$this->hasColumns()) {
			throw new Exception('Grid must have defined a column, please use method $grid->addColumn*().');
		}

		$this->saveRememberState();
		$data = $this->getData();

		if (!empty($this->onRender)) {
			$this->onRender($this);
		}

		$form = $this['form'];

		$this->getTemplate()->add('data', $data);
		$this->getTemplate()->add('form', $form);
		$this->getTemplate()->add('paginator', $this->getPaginator());
		$this->getTemplate()->add('customization', $this->getCustomization());
		$this->getTemplate()->add('columns', $this->getComponent(Column::ID)->getComponents());
		$this->getTemplate()->add('actions', $this->hasActions()
			? $this->getComponent(Action::ID)->getComponents()
			: []);

		$this->getTemplate()->add('buttons', $this->hasButtons()
			? $this->getComponent(Button::ID)->getComponents()
			: []);

		$this->getTemplate()->add('formFilters', $this->hasFilters()
			? $form->getComponent(Filter::ID)->getComponents()
			: []);

		$form['count']->setValue($this->getPerPage());

		if ($options = $this->options[self::CLIENT_SIDE_OPTIONS]) {
			$this->getTablePrototype()->setAttribute('data-' . self::CLIENT_SIDE_OPTIONS, json_encode($options));
		}

		$this->getTemplate()->render();
	}

	protected function saveRememberState()
	{
		if ($this->rememberState) {
			$session = $this->getRememberSession(true);
			$params = array_keys($this->getReflection()->getPersistentParams());
			foreach ($params as $param) {
				$session->params[$param] = $this->$param;
			}
		}
	}

	protected function applyFiltering()
	{
		$conditions = $this->__getConditions($this->getActualFilter());
		$this->getModel()->filter($conditions);
	}

	/**
	 * @param array $filter
	 * @return array
	 *
	 * @internal
	 */
	public function __getConditions(array $filter)
	{
		$conditions = [];
		if (!empty($filter)) {
			try {
				$this['form']->setDefaults([Filter::ID => $filter]);
			} catch (InvalidArgumentException $e) {
				$this->__triggerUserNotice($e->getMessage());
				$filter = [];
				if ($session = $this->getRememberSession()) {
					$session->remove();
				}
			}

			foreach ($filter as $column => $value) {
				if ($component = $this->getFilter($column, false)) {
					if ($condition = $component->__getCondition($value)) {
						$conditions[] = $condition;
					}
				} else {
					$this->__triggerUserNotice("Filter with name '$column' does not exist.");
				}
			}
		}

		return $conditions;
	}

	protected function applySorting()
	{
		$sort = [];
		$this->sort = $this->sort ? $this->sort : $this->defaultSort;

		foreach ($this->sort as $column => $dir) {
			$component = $this->getColumn($column, false);
			if (!$component) {
				if (!isset($this->defaultSort[$column])) {
					$this->__triggerUserNotice("Column with name '$column' does not exist.");

					break;
				}
			} elseif (!$component->isSortable()) {
				if (isset($this->defaultSort[$column])) {
					$component->setSortable();
				} else {
					$this->__triggerUserNotice("Column with name '$column' is not sortable.");

					break;
				}
			}

			if (!in_array($dir, [Column::ORDER_ASC, Column::ORDER_DESC])) {
				if ($dir == '' && isset($this->defaultSort[$column])) {
					unset($this->sort[$column]);

					break;
				}

				$this->__triggerUserNotice("Dir '$dir' is not allowed.");

				break;
			}

			$sort[$component ? $component->column : $column] = $dir == Column::ORDER_ASC ? 'ASC' : 'DESC';
		}

		if (!empty($sort)) {
			$this->getModel()->sort($sort);
		}
	}

	protected function applyPaging()
	{
		$paginator = $this->getPaginator()
			->setItemCount($this->getCount())
			->setPage($this->page);

		$perPage = $this->getPerPage();
		if ($perPage !== null && !in_array($perPage, $this->perPageList)) {
			$this->__triggerUserNotice("The number '$perPage' of items per page is out of range.");
		}

		$this->getModel()->limit($paginator->getOffset(), $paginator->getLength());
	}

	protected function createComponentForm($name)
	{
		$form = new Form($this, $name);
		$form->setTranslator($this->getTranslator());
		$form->setMethod($form::GET);

		$buttons = $form->addContainer(self::BUTTONS);
		$buttons->addSubmit('search', 'Grido.Search')
			->onClick[] = [$this, 'handleFilter'];
		$buttons->addSubmit('reset', 'Grido.Reset')
			->onClick[] = [$this, 'handleReset'];
		$buttons->addSubmit('perPage', 'Grido.ItemsPerPage')
			->onClick[] = [$this, 'handlePerPage'];

		$form->addSelect('count', 'Count', $this->getItemsForCountSelect())
			->setTranslator(null)
			->controlPrototype->attrs['title'] = $this->getTranslator()->translate('Grido.ItemsPerPage');
	}

	/**
	 * @return array
	 */
	protected function getItemsForCountSelect()
	{
		return array_combine($this->perPageList, $this->perPageList);
	}

	/**
	 * @param string $message
	 *
	 * @internal
	 */
	public function __triggerUserNotice($message)
	{
		if ($this->getPresenter(false) && $session = $this->getRememberSession()) {
			$session->remove();
		}

		$this->strictMode && trigger_error($message, E_USER_NOTICE);
	}

}
