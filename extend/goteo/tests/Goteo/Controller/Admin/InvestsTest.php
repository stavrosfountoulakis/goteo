<?php


namespace Goteo\Controller\Admin\Tests;

use Goteo\Controller\Admin\Invests;

class InvestsTest extends \PHPUnit_Framework_TestCase {

    public function testInstance() {

        $controller = new Invests();

        $this->assertInstanceOf('\Goteo\Controller\Admin\Invests', $controller);

        return $controller;
    }
}