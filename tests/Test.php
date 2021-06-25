<?php
/**
 * Created by PhpStorm.
 * User: LinZhou <1207032539@qq.com>
 * Date: 2020/1/4
 * Time: 14:51
 */

namespace Test;

use Luler\IdPhotoBackgroundSwitch\IdPhotoHelper;
use PHPUnit\Framework\TestCase;

class Test extends TestCase
{
    public function testIdPhotoHelper()
    {
        IdPhotoHelper::transformImage('test.jpg', 'res.jpg', IdPhotoHelper::WHITE_COLOR, [413, 295]);
    }
}
