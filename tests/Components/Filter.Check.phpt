<?php

/**
 * Test: Filter.
 *
 * @author     Petr Bugyík
 * @package    Grido\Tests
 */

namespace Grido\Tests;

use Tester\Assert,
    Grido\Grid;

require_once __DIR__ . '/../bootstrap.php';

class FilterCheckTest extends \Tester\TestCase
{
    public function testFormControl()
    {
        $grid = new Grid;
        $filter = $grid->addFilterCheck('check', 'Check');
        Assert::type('\Nette\Forms\Controls\Checkbox', $filter->control);
    }

    public function testGetCondition()
    {
        $grid = new Grid;
        $filter = $grid->addFilterCheck('check', 'Check');
        Assert::same(['check IS NOT NULL'], $filter->__getCondition(TRUE)->__toArray());
    }

    public function testChangeValue()
    {
        $grid = new Grid;
        $filter = $grid->addFilterCheck('check', 'Check');
        Assert::same(\Grido\Components\Filters\Check::TRUE, $filter->changeValue(TRUE));
        Assert::same(\Grido\Components\Filters\Check::TRUE, $filter->changeValue(1));
        Assert::same(FALSE, $filter->changeValue(FALSE));
    }
}

run(__FILE__);
