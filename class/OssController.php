<?php
/**
 * Created by PhpStorm.
 * User: alin
 * Date: 2016/12/26
 * Time: 21:52
 */

namespace App\Http\Controllers;


use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Http\Requests;
use Illuminate\Support\Facades\Log;
use OSS\OssClient;

class OssController extends Controller{


    /**
     * 上传对象
     * @param $object
     * @param $content
     * @return bool|null
     */
    public static function uploadObj($object,$content)
    {
        if(empty($object)||empty($content)){
            return false;
        }
        $accessKeyId=config('oss.AccessKeyId');
        $accessKeySecret=config('oss.AccessKeySecret');
        $bucketName = false ? config('oss.ossServerInternal') : config('oss.ossServer');
        $endpoint = 'http://oss-cn-beijing.aliyuncs.com';
        $ossClient = new OssClient($accessKeyId, $accessKeySecret,$endpoint ,false);
        $bucket = 'doudouyuyin';
        return $ossClient->putObject($bucket, $object, $content);
    }

    /**
     * 上传文件
     * @param $content
     * @param $filePath
     * @return null
     * @throws \OSS\Core\OssException
     */
    public static function upload($content, $filePath)
    {
        if(empty($content)||empty($filePath)){
            return false;
        }
        $accessKeyId=config('oss.AccessKeyId');
        $accessKeySecret=config('oss.AccessKeySecret');
        $bucketName = false ? config('oss.ossServerInternal') : config('oss.ossServer');
        $endpoint = 'http://oss-cn-beijing.aliyuncs.com';
        $ossClient = new OssClient($accessKeyId, $accessKeySecret,$endpoint ,false);
        $bucket = 'doudouyuyin';
        return $ossClient->uploadFile($bucket,$content,$filePath);
    }


    public static function uploadContent($content)
    {

    }
}