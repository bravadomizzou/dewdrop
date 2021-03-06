<?php

namespace Dewdrop\Mail\View\Helper;

use Dewdrop\Mail\View\View;
use PHPUnit_Framework_TestCase;

class ParagraphTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->view = new View();
    }

    public function testSuppliedContentIsIncluded()
    {
        $this->assertContains('TEST_CONTENT', $this->view->p('TEST_CONTENT'));
    }
}

