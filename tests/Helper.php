<?php

/**
 * This file is part of the Grido (http://grido.bugyik.cz)
 *
 * Copyright (c) 2011 Petr Bugyík (http://petr.bugyik.cz)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace Grido\Tests;

use Closure;
use Grido\Grid;
use Kdyby\Annotations\DI\AnnotationsExtension;
use Kdyby\Doctrine\DI\OrmExtension;
use Kdyby\Events\DI\EventsExtension;
use Nette\Application\IResponse;
use Nette\Application\IRouter;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\Routers\Route;
use Nette\Application\UI\Presenter;
use Nette\Application\UI\Template;
use Nette\Configurator;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;
use const E_RECOVERABLE_ERROR;
use const PHP_VERSION_ID;

/**
 * Test helper.
 */
class Helper
{

	const GRID_NAME = 'grid';

	/** @var Grid */
	public static $grid;

	/** @var TestPresenter */
	public static $presenter;

	/**
	 * @param Closure $definition of grid; function(Grid $grid, TestPresenter $presenter) { };
	 */
	public static function grid(Closure $definition)
	{
		$self = new self();

		if (self::$presenter === null) {
			self::$presenter = $self->createPresenter();
		}

		self::$presenter->onStartUp = [];
		self::$presenter->onStartUp[] = function (TestPresenter $presenter) use ($definition) {
			if (isset($presenter[self::GRID_NAME])) {
				unset($presenter[self::GRID_NAME]);
			}

			$definition(new Grid($presenter, self::GRID_NAME), $presenter);
		};

		return $self;
	}

	/**
	 * @param array $params
	 * @param string $method
	 * @return IResponse
	 */
	public static function request(array $params = [], $method = Request::GET)
	{
		$request = new \Nette\Application\Request('Test', $method, $params);
		$response = self::$presenter->run($request);

		self::$grid = self::$presenter[self::GRID_NAME];

		return $response;
	}

	/**
	 * @param array $params
	 * @param string $method
	 * @return IResponse
	 */
	public function run(array $params = [], $method = Request::GET)
	{
		return self::request($params, $method);
	}

	public static function assertTypeError($function)
	{
		if (PHP_VERSION_ID < 70000) {
			Assert::error($function, E_RECOVERABLE_ERROR);
		} else {
			Assert::exception($function, '\TypeError');
		}
	}

	private function createPresenter(): TestPresenter
	{
		$url = new UrlScript('http://localhost/', '/');

		$configurator = new Configurator();
		$configurator->addConfig(__DIR__ . '/config.neon');
		//EventsExtension::register($configurator);

		$container = $configurator
			->setTempDirectory(TEMP_DIR)
			->createContainer();
		$container->removeService('httpRequest');
		$container->addService('httpRequest', new Request($url, NULL, NULL, ['nette-samesite' => TRUE]));

		$router = $container->getByType(IRouter::class);
		$router[] = new Route('<presenter>/<action>[/<id>]', 'Dashboard:default');

		$presenter = new TestPresenter($container);
		$container->callInjects($presenter);
		$presenter->invalidLinkMode = $presenter::INVALID_LINK_WARNING;
		$presenter->autoCanonicalize = false;

		return $presenter;
	}

}

class TestPresenter extends Presenter
{

	/** @var array */
	public $onStartUp;

	/** @var bool */
	public $forceAjaxMode = false;

	public function startup()
	{
		parent::startup();

		$this->onStartUp($this);
	}

	public function sendTemplate(?Template $template = null): void
	{
		//parent::sendTemplate(); intentionally
	}

	public function sendResponse(IResponse $response): void
	{
		if ($response instanceof JsonResponse) {
			$response->send($this->getHttpRequest(), $this->getHttpResponse());
		} else {
			parent::sendResponse($response);
		}
	}

	public function isAjax(): bool
	{
		return $this->forceAjaxMode === true || parent::isAjax();
	}

	public function terminate(): void
	{
		if ($this->forceAjaxMode === false) {
			parent::terminate();
		}
	}

}
