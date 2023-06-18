<?php

/**
 * Test: DataSources test case.
 */

namespace Grido\Tests;

use Grido\Components\Columns\Column;
use Grido\Components\Columns\Editable;
use Nette\Http\Response;
use Tester\Assert;
use Tester\TestCase;
use function file_get_contents;
use function mock;
use function ob_get_clean;
use function ob_start;

require_once __DIR__ . '/../bootstrap.php';

abstract class DataSourceTestCase extends TestCase
{

	const EDITABLE_TEST_ID = 1;

	const EDITABLE_TEST_VALUE = 'New value';

	const EDITABLE_TEST_VALUE_OLD = 'Old value';

	/** @var array GET parameters to request */
	private $params = [
		'grid-page' => 2,
		'grid-sort' => ['country' => Column::ORDER_ASC],
		'grid-filter' => [
			'name' => 'a',
			'male' => true,
			'country' => 'au',
		]];

	public function testRender()
	{
		Helper::request($this->params);

		ob_start();
			Helper::$grid->render();
		$output = ob_get_clean();

		Assert::matchFile(__DIR__ . '/files/render.expect', $output);
	}

	public function testSuggest()
	{
		Helper::$presenter->forceAjaxMode = true;
		$params = $this->params + ['grid-filters-country-query' => 'and', 'do' => 'grid-filters-country-suggest'];
		ob_start();
			Helper::request($params);
		$output = ob_get_clean();
		Assert::same('["Finland","Poland"]', $output);

		$params = ['grid-filters-name-query' => 't', 'do' => 'grid-filters-name-suggest'];
		ob_start();
			Helper::request($params);
		$output = ob_get_clean();
		Assert::same('["Awet","Caitlin","Dragotina","Katherine","Satu","Trommler"]', $output);
	}

	public function testSetWhere()
	{
		Helper::request(['grid-filter' => ['tall' => true]]);
		Helper::$grid->getData(false);
		Assert::same(10, Helper::$grid->count);
	}

	public function testEditable()
	{
		Helper::$presenter->forceAjaxMode = true;

		$params = [
			'do' => 'grid-columns-firstname-editable',
			'grid-columns-firstname-id' => self::EDITABLE_TEST_ID,
			'grid-columns-firstname-newValue' => self::EDITABLE_TEST_VALUE,
			'grid-columns-firstname-oldValue' => self::EDITABLE_TEST_VALUE_OLD,
		];
		ob_start();
			Helper::request($params);
		$output = ob_get_clean();

		Assert::same('{"updated":true,"html":"' . self::EDITABLE_TEST_VALUE . '"}', $output);
	}

	public function editableCallbackTest($id, $newValue, $oldValue, Editable $column)
	{
		Assert::same(self::EDITABLE_TEST_ID, $id);
		Assert::same(self::EDITABLE_TEST_VALUE, $newValue);
		Assert::same(self::EDITABLE_TEST_VALUE_OLD, $oldValue);
		Assert::same('firstname', $column->name);

		return true;
	}

	public function testNullableForeignKey()
	{
		Helper::request();

		ob_start();
			Helper::$grid->render();
		ob_get_clean();

		Assert::same(Helper::$grid->count, 50);
	}

	public function testExport()
	{
		Helper::$presenter->forceAjaxMode = false;
		$params = $this->params + ['do' => 'grid-export-export'];

		ob_start();
			Helper::request($params)->send(mock('\Nette\Http\IRequest'), new Response());
		$output = ob_get_clean();

		Assert::same(file_get_contents(__DIR__ . '/files/export.expect'), $output);
	}

	public function tearDown()
	{
		//cleanup
		Helper::$presenter->forceAjaxMode = false;
	}

}
