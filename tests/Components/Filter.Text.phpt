<?php

/**
 * Test: Filter.
 *
 * @author     Petr Bugyík
 * @package    Grido\Tests
 */

namespace Grido\Tests;

require_once __DIR__ . '/../bootstrap.php';

use Tester\Assert,
    Grido\Grid;

class FilterTextTest extends \Tester\TestCase
{
    public function testSetSuggestion()
    {
        Helper::grid(function(Grid $grid) {
            $grid->setModel([
                ['name' => 'AAtest'],
                ['name' => 'AAxxx'],
                ['name' => 'BBtest'],
                ['name' => 'BBxxx'],
                ['name' => 'CC <script>alert("XSS")</script>'],
            ]);
            $grid->addColumnText('name', 'Name');
            $filter = $grid->addFilterText('name', 'Name')->setSuggestion();

            Assert::same('off', $filter->control->controlPrototype->attrs['autocomplete']);
            Assert::same(['text', 'suggest'], $filter->control->controlPrototype->class);

            $grid->addFilterText('test', 'Test')
                ->setSuggestion()
                    ->setSuggestionCallback(function($query, $filter, $conditions, $filterComp) use ($grid) {
                        Assert::same('QUERY', $query);
                        Assert::same(['name' => 'aa'], $filter);
                        Assert::same($grid->getFilter('test'), $filterComp);

                        $cond = new \Grido\Components\Filters\Condition('name', 'LIKE ?', '%aa%');
                        Assert::same($cond->__toArray(), $conditions[0]->__toArray());

                        return ['test1', 'test2'];
                    });
        })->run();

        ob_start();
            Helper::$grid->render();
        ob_clean();

        $prototype = Helper::$grid->getFilter('name')->control->controlPrototype;
        Assert::same('-query-', $prototype->getAttribute('data-grido-suggest-replacement'));

        $url = '/test/?grid-filters-name-query=-query-&do=grid-filters-name-suggest';
        Assert::same($url, $prototype->getAttribute('data-grido-suggest-handler'));

        Helper::$presenter->forceAjaxMode = TRUE;
        Helper::request();

        ob_start();
            Helper::$grid->getFilter('name')->handleSuggest('aa');
        $output = ob_get_clean();
        Assert::same('["AAtest","AAxxx"]', $output);

        ob_start();
            Helper::$grid->getFilter('name')->handleSuggest('xx');
        $output = ob_get_clean();
        Assert::same('["AAxxx","BBxxx"]', $output);

        ob_start();
            Helper::$grid->getFilter('name')->handleSuggest('###');
        $output = ob_get_clean();
        Assert::same('[]', $output);

        ob_start();
            Helper::$grid->getFilter('name')->handleSuggest('cc');
        $output = ob_get_clean();
        Assert::same('["CC &lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;"]', $output);

        ob_start();
            Helper::request(['grid-filter' => ['name' => 'aa'], 'do' => 'grid-filters-test-suggest', 'grid-filters-test-query' => 'QUERY']);
        $output = ob_get_clean();

        Assert::same('["test1","test2"]', $output);
    }

    public function testFormControl()
    {
        $grid = new Grid;
        $filter = $grid->addFilterText('text', 'Text');
        Assert::type('Nette\Forms\Controls\TextInput', $filter->control);
    }

    public function testGetCondition()
    {
        $grid = new Grid;
        $filter = $grid->addFilterText('text', 'Text');
        Assert::same(['text LIKE ?', '%value%'], $filter->__getCondition('value')->__toArray());
    }
}

run(__FILE__);
