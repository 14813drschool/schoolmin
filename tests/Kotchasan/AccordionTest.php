<?php

namespace Kotchasan;

use PHPUnit_Framework_TestCase;

class AccordionTest extends PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        // Test with default items and onetab = false
        $accordion = new Accordion('test-accordion');
        $this->assertInstanceOf(Accordion::class, $accordion);
        $this->assertAttributeEquals('test-accordion', 'id', $accordion);
        $this->assertAttributeEquals([], 'datas', $accordion);
        $this->assertAttributeEquals('checkbox', 'type', $accordion);

        // Test with items and onetab = true
        $items = [
            'Title 1' => ['detail' => 'Detail 1', 'select' => false, 'className' => 'article'],
            'Title 2' => ['detail' => 'Detail 2', 'select' => true, 'className' => 'custom-class'],
        ];
        $accordion = new Accordion('accordion2', $items, true);
        $this->assertInstanceOf(Accordion::class, $accordion);
        $this->assertAttributeEquals('accordion2', 'id', $accordion);
        $this->assertAttributeEquals($items, 'datas', $accordion);
        $this->assertAttributeEquals('radio', 'type', $accordion);
    }

    public function testAdd()
    {
        $accordion = new Accordion('test-accordion');

        // Add a new item
        $accordion->add('New Title', 'New Detail', true, 'new-class');
        $expectedDatas = [
            'New Title' => ['detail' => 'New Detail', 'select' => true, 'className' => 'new-class']
        ];
        $this->assertAttributeEquals($expectedDatas, 'datas', $accordion);

        // Add another item
        $accordion->add('Another Title', 'Another Detail');
        $expectedDatas['Another Title'] = ['detail' => 'Another Detail', 'select' => false, 'className' => 'article'];
        $this->assertAttributeEquals($expectedDatas, 'datas', $accordion);
    }

    public function testRender()
    {
        // Test with no items
        $accordion = new Accordion('test-accordion');
        $expectedHtml = '<div class="accordion"></div>';
        $this->assertEquals($expectedHtml, $accordion->render());

        // Test with items and onetab = false (checkbox)
        $items = [
            'Title 1' => ['detail' => 'Detail 1', 'select' => false, 'className' => 'article'],
            'Title 2' => ['detail' => 'Detail 2', 'select' => true, 'className' => 'custom-class'],
        ];
        $accordion = new Accordion('accordion-checkbox', $items, false);
        $expectedHtml = '<div class="accordion">';
        $expectedHtml .= '<div class="item">';
        $expectedHtml .= '<input id="accordion-checkbox1" name="accordion-checkbox" type="checkbox">';
        $expectedHtml .= '<label for="accordion-checkbox1">Title 1</label>';
        $expectedHtml .= '<div class="body"><div class="article">Detail 1</div></div>';
        $expectedHtml .= '</div>';
        $expectedHtml .= '<div class="item">';
        $expectedHtml .= '<input id="accordion-checkbox2" name="accordion-checkbox" type="checkbox" checked>';
        $expectedHtml .= '<label for="accordion-checkbox2">Title 2</label>';
        $expectedHtml .= '<div class="body"><div class="custom-class">Detail 2</div></div>';
        $expectedHtml .= '</div>';
        $expectedHtml .= '</div>';
        $this->assertEquals($expectedHtml, $accordion->render());

        // Test with items and onetab = true (radio)
        $itemsRadio = [
            'Radio Title 1' => ['detail' => 'Radio Detail 1', 'select' => true, 'className' => 'article'],
            'Radio Title 2' => ['detail' => 'Radio Detail 2', 'select' => false, 'className' => 'another-class'],
        ];
        $accordionRadio = new Accordion('accordion-radio', $itemsRadio, true);
        $expectedHtmlRadio = '<div class="accordion">';
        $expectedHtmlRadio .= '<div class="item">';
        $expectedHtmlRadio .= '<input id="accordion-radio1" name="accordion-radio" type="radio" checked>';
        $expectedHtmlRadio .= '<label for="accordion-radio1">Radio Title 1</label>';
        $expectedHtmlRadio .= '<div class="body"><div class="article">Radio Detail 1</div></div>';
        $expectedHtmlRadio .= '</div>';
        $expectedHtmlRadio .= '<div class="item">';
        $expectedHtmlRadio .= '<input id="accordion-radio2" name="accordion-radio" type="radio">';
        $expectedHtmlRadio .= '<label for="accordion-radio2">Radio Title 2</label>';
        $expectedHtmlRadio .= '<div class="body"><div class="another-class">Radio Detail 2</div></div>';
        $expectedHtmlRadio .= '</div>';
        $expectedHtmlRadio .= '</div>';
        $this->assertEquals($expectedHtmlRadio, $accordionRadio->render());
    }
}
