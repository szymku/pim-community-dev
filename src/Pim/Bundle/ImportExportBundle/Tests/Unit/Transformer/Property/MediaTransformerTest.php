<?php

namespace Pim\Bundle\ImportExportBundle\Tests\Unit\Transformer\Property;

use Pim\Bundle\ImportExportBundle\Transformer\Property\MediaTransformer;
use Symfony\Component\HttpFoundation\File\File;
use Oro\Bundle\FlexibleEntityBundle\Entity\Media;

/**
 * Tests related class
 *
 * @author    Antoine Guigan <antoine@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MediaTransformerTest extends \PHPUnit_Framework_TestCase
{
    public function testTransform()
    {
        $transformer = new MediaTransformer;
        $this->assertEquals(null, $transformer->transform(''));
        $this->assertEquals(null, $transformer->transform(' '));
        $d = tempnam('/tmp', 'pim-media-transformer-test');
        unlink($d);
        mkdir($d);
        $f = $d . '/file';
        touch($f);
        $this->assertEquals(null, $transformer->transform(' ' . $d . ' '));
        $this->assertEquals(new File($f), $transformer->transform(' ' . $f . ' '));
        unlink($f);
        rmdir($d);
    }

    public function getUpdateProductValueData()
    {
        return array(
            'no_file_no_media' => array(false, false),
            'file_no_media' => array(true, false),
            'no_file_media' => array(false, true),
            'file_media' => array(true, true),
        );
    }

    /**
     * @dataProvider getUpdateProductValueData
     */
    public function testUpdateProductValue($hasFile, $mediaExists)
    {
        $test = $this;
        $f = tempnam('/tmp', 'pim-media-transformer-test');
        $this->media = $mediaExists ? new Media : null;
        $file = $hasFile ? new File($f) : null;
        $transformer = new MediaTransformer;
        $productValue = $this->getMockBuilder('Pim\Bundle\CatalogBundle\Model\ProductValueInterface')
            ->setMethods(array('getMedia', 'setMedia', '__toString'))
            ->getMock();
        if ($hasFile) {
            $productValue
                ->expects($this->once())
                ->method('getMedia')
                ->will($this->returnValue($this->media));
            if (!$mediaExists) {
                $productValue
                    ->expects($this->once())
                    ->method('setMedia')
                    ->with($this->isInstanceOf('Oro\Bundle\FlexibleEntityBundle\Entity\Media'))
                    ->will(
                        $this->returnCallback(
                            function ($createdMedia) use ($test) {
                                $test->media = $createdMedia;
                            }
                        )
                    );
            }
        } else {
            $productValue
                ->expects($this->never())
                ->method('getMedia');
        }
        $transformer->updateProductValue($productValue, $file);
        if ($hasFile) {
            $this->assertEquals($file, $this->media->getFile());
        }
        unlink($f);
    }
}
