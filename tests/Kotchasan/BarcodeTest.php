<?php

namespace Kotchasan;

use PHPUnit_Framework_TestCase;

class BarcodeTest extends PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $barcode = Barcode::create('123456');
        $this->assertInstanceOf(Barcode::class, $barcode);
        $this->assertAttributeEquals('123456', 'code', $barcode);
        $this->assertAttributeEquals(30, 'height', $barcode); // Default height
        $this->assertAttributeEquals(0, 'fontSize', $barcode); // Default font size

        $barcodeWithParams = Barcode::create('ABCDE', 50, 12);
        $this->assertInstanceOf(Barcode::class, $barcodeWithParams);
        $this->assertAttributeEquals('ABCDE', 'code', $barcodeWithParams);
        $this->assertAttributeEquals(50, 'height', $barcodeWithParams);
        $this->assertAttributeEquals(12, 'fontSize', $barcodeWithParams);
    }

    public function testToPng()
    {
        // Test with a simple code
        $barcode = Barcode::create('12345');
        $pngData = $barcode->toPng();
        $this->assertNotEmpty($pngData);
        // Check if it's valid PNG data (basic check by looking for PNG signature)
        $this->assertStringStartsWith("PNG

", $pngData);

        // Test with a code and font size
        $barcodeWithFont = Barcode::create('TESTING', 60, 10);
        // Ensure the font file exists or mock it if necessary for the test environment
        // For this example, we assume the default font path is accessible or GD handles it gracefully
        if (!file_exists(ROOT_PATH.'skin/fonts/thsarabunnew-webfont.ttf')) {
            // Create a dummy font file if it doesn't exist to prevent errors in tests
            // This is a workaround and might need adjustment based on the testing environment
            @mkdir(ROOT_PATH.'skin/fonts/', 0777, true);
            @touch(ROOT_PATH.'skin/fonts/thsarabunnew-webfont.ttf');
        }
        $pngDataWithFont = $barcodeWithFont->toPng();
        $this->assertNotEmpty($pngDataWithFont);
        $this->assertStringStartsWith("PNG

", $pngDataWithFont);

        // Test with an empty code (should generate a minimal PNG)
        $emptyBarcode = Barcode::create('');
        $emptyPngData = $emptyBarcode->toPng();
        $this->assertNotEmpty($emptyPngData);
        $this->assertStringStartsWith("PNG

", $emptyPngData);
        // You might want to check the dimensions or content of the empty barcode image
        // For example, it should be a 1xHeight white rectangle
        $img = imagecreatefromstring($emptyPngData);
        $this->assertEquals(1, imagesx($img)); // Width should be 1 for empty/error
        $this->assertEquals(30, imagesy($img)); // Default height

        // Test with a code containing characters that might cause issues
        $complexBarcode = Barcode::create('A-Za-z0-9 !@#$%^&*()_+');
        $complexPngData = $complexBarcode->toPng();
        $this->assertNotEmpty($complexPngData);
        $this->assertStringStartsWith("PNG

", $complexPngData);
    }

    /**
     * @dataProvider barcode128Provider
     */
    public function testBarcode128($input, $expectedOutputPattern)
    {
        // Use reflection to test the private static method Barcode128
        $method = new \ReflectionMethod(Barcode::class, 'Barcode128');
        $method->setAccessible(true);
        $result = $method->invoke(null, $input);

        if ($expectedOutputPattern === '') {
            $this->assertEquals('', $result);
        } else {
            // The Barcode128 output is complex and includes a checksum.
            // We'll check the start, the presence of the input (or its C-type transformation), and the stop.
            // This is a simplified check. For more robustness, one might need to decode the result.
            $this->assertStringStartsWith($expectedOutputPattern['start'], $result);
            if (isset($expectedOutputPattern['contains'])) {
                foreach ($expectedOutputPattern['contains'] as $contain) {
                    $this->assertContains($contain, $result);
                }
            }
            $this->assertStringEndsWith($expectedOutputPattern['stop'], $result);
            // Verify it's a string of 0s and 1s
            $this->assertRegExp('/^[01]+$/', $result);
        }
    }

    public function barcode128Provider()
    {
        // Expected patterns are simplified for this example
        // A full test would require calculating the exact Barcode128 string with checksum
        return [
            ['123456', ['start' => '11010011100', 'contains' => ['10011100110', '11001110010', '11001011100'], 'stop' => '1100011101011']], // Start C, some numbers, Stop
            ['ABC', ['start' => '11010010000', 'contains' => ['10100011000', '10001011000', '10001000110'], 'stop' => '1100011101011']],      // Start B, A, B, C, Stop
            ['Test01', ['start' => '11010010000', 'contains' => ['11011100010', '10110010000', '10010110000', '10011101100', '10011100110'], 'stop' => '1100011101011']], // Start B, T, e, s, t, 0, 1, Stop
            ['', ''], // Empty input
            ["", ''], // Invalid characters (control codes not in default set)
            ['ยาว', ''] // Thai characters, likely not supported by default Barcode128 char set
        ];
    }
}

