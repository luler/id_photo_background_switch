<?php

namespace Luler\IdPhotoBackgroundSwitch;

class IdPhotoHelper
{
    const RED_COLOR = [255, 0, 0];
    const BLUE_COLOR = [67, 142, 219];
    const WHITE_COLOR = [255, 255, 255];
    static $ignore_piexl = 0.01; //像素横向左右存在该比例无需替换颜色的像素时，无效该像素替换
    static $distinct_piexl = 80; //rgb像素整体偏差范围

    /**
     * 证件照背景颜色替换
     * @param string $from_image_path //来源图片路径
     * @param string $to_image_path //目标图片路径
     * @param array|int[] $color //rgb颜色，示例:[255,0,0]
     * @param array $return_size // 宽高，示例：[400,300]
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    public static function transformImage(string $from_image_path, string $to_image_path, array $color = self::RED_COLOR, array $return_size = [])
    {
        $old_image = imagecreatefromstring(file_get_contents($from_image_path));
        if (!empty($return_size)) {
            $old_image = self::resetImage($old_image, $return_size);
        }
        $old_image_width = imagesx($old_image);//获取长和宽
        $old_image_height = imagesy($old_image);
        $new = imagecreatetruecolor($old_image_width, $old_image_height);//创建一个背景图
        $color = imagecolorallocate($new, $color[0] ?? 0, $color[1] ?? 0, $color[2] ?? 0);
        imagefill($new, 0, 0, $color);
        $background_color = self::positionBackgroupColor($old_image);
        imagecolortransparent($old_image, $background_color);//将识别到的背景颜色替换成透明
        imagecopymerge($new, $old_image, 0, 0, 0, 0, $old_image_width, $old_image_height, 100);//合并图片
        imagejpeg($new, $to_image_path);//保存图片
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
                //类似颜色统一增强,小于误差要求的像素都要增强
                $distinct_piexl_result = abs($r - $background_color_rgb[0]) + abs($g - $background_color_rgb[1]) + abs($b - $background_color_rgb[2]);
                if ($distinct_piexl_result < $distinct_piexl) {
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
        $rgb = key($collect_rgbs); //默认key数组指针位置的键名，默认数组指针在第一位，取出现最多的rgb
        $r = ($rgb >> 16) & 0xff;//取R
        $g = ($rgb >> 8) & 0xff;//取G
        $b = $rgb & 0xff;//取B
        return [$r, $g, $b];
    }

    /**
     * 重置图片（可实现低损压缩）
     * @param $image
     * @param array $return_size //示例：[400,300] 宽高
     * @return false|\GdImage|resource
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    private static function resetImage($image, $return_size = [])
    {
        $width = imagesx($image); //获取原图尺寸
        $height = imagesy($image); //获取原图尺寸
        //缩放尺寸
        $new_width = $return_size[0] ?? $width;
        $new_height = $return_size[1] ?? $height;
        $dst_image = imagecreatetruecolor($new_width, $new_height);
        imagecopyresized($dst_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        imagedestroy($image);
        return $dst_image;
    }
}
