<?php

namespace Luler\IdPhotoBackgroundSwitch;

class IdPhotoHelper
{
    const RED_COLOR = [255, 0, 0];
    const BLUE_COLOR = [67, 142, 219];
    const WHITE_COLOR = [255, 255, 255];
    static $ignore_piexl = 0.01; //像素横向左右存在该比例无需替换颜色的像素时，无效该像素替换
    static $distinct_piexl = 60; //rgb像素聚类增强时误差范围

    public static function transformImage(string $from_image_path, string $to_image_path, array $color = self::RED_COLOR)
    {
        $old_image = imagecreatefromstring(file_get_contents($from_image_path));
        list($old_image_width, $old_image_height) = getimagesize($from_image_path);//获取长和宽
        $new = imagecreatetruecolor($old_image_width, $old_image_height);//创建一个背景图
        $color = imagecolorallocate($new, $color[0] ?? 0, $color[1] ?? 0, $color[2] ?? 0);
        imagefill($new, 0, 0, $color);
        $background_color = self::positionBackgroupColor($old_image);
        imagecolortransparent($old_image, $background_color);//将识别到的背景颜色替换成透明
        imagecopymerge($new, $old_image, 0, 0, 0, 0, $old_image_width, $old_image_height, 100);//合并图片
        imagepng($new, $to_image_path);//保存图片
        imagedestroy($old_image);//销毁
        imagedestroy($new);
    }

    /**
     * 定位优化并返回背景色
     * @param $image
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    private static function positionBackgroupColor($image)
    {
        $background_color_rgb = self::collectBackgroupColor($image);
        $background_color = imagecolorallocate($image, $background_color_rgb[0], $background_color_rgb[1], $background_color_rgb[2]);
        $width = imagesx($image);//获取宽
        $height = imagesy($image);//获取高
        $ignore_piexl = $width * self::$ignore_piexl;
        $distinct_piexl = self::$distinct_piexl;
        for ($y = 0; $y < $height; $y++) {
            $setpixels = [];
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);  //获取一个像素rgb
                $r = ($rgb >> 16) & 0xff;//取R
                $g = ($rgb >> 8) & 0xff;//取G
                $b = $rgb & 0xff;//取B
                //类似颜色统一增强,误差像素
                if ($r - $distinct_piexl < $background_color_rgb[0] && $r + $distinct_piexl > $background_color_rgb[0] &&
                    $g - $distinct_piexl < $background_color_rgb[1] && $g + $distinct_piexl > $background_color_rgb[1] &&
                    $b - $distinct_piexl < $background_color_rgb[2] && $b + $distinct_piexl > $background_color_rgb[2]) {
                    $setpixels[] = 1;
                } else {
                    $setpixels[] = 0;
                }
            }
            //检查并填充颜色
            foreach ($setpixels as $key => $setpixel) {
                if ($setpixel == 1) {
                    $left = array_slice($setpixels, 0, $key);
                    $right = array_slice($setpixels, $key);
                    array_shift($right);
                    $left = array_count_values($left);
                    $right = array_count_values($right);
                    //如果该像素的左边像素和右边像素都存在一定数量的不需要替换的像素，那么该像素也取消替换
                    if (!(isset($left[0]) && isset($right[0]) && $left[0] > $ignore_piexl && $right[0] > $ignore_piexl)) {
                        imagesetpixel($image, $key, $y, $background_color);
                    }
                }
            }
        }
        return $background_color;
    }

    /**
     * 根据证件照特征识别获取背景色rgb
     * @param $image
     * @return int[]
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    private static function collectBackgroupColor($image)
    {
        $width = imagesx($image);//获取宽
        $height = imagesy($image);//获取高
        $collect_rgbs = [];
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                if ($y < $height * 0.1 || ($y < $height * 0.5 && ($x < $width * 0.1 || $x > $width * 0.9))) {
                    $collect_rgbs[] = imagecolorat($image, $x, $y); //取采样范围像素rgb
                }
            }
        }

        $collect_rgbs = array_count_values($collect_rgbs); //颜色出现计数
        arsort($collect_rgbs); //计数排序
        $rgb = array_key_first($collect_rgbs); //取出现最多的rgb
        $r = ($rgb >> 16) & 0xff;//取R
        $g = ($rgb >> 8) & 0xff;//取G
        $b = $rgb & 0xff;//取B
        return [$r, $g, $b];
    }
}
