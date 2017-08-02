<?php
/**
 * Created by PhpStorm.
 * User: alin
 * Date: 2016/12/2
 * Time: 13:56
 */

namespace App\Http\Controllers;
use Illuminate\Http\Request;

use App\Http\Requests;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\File;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Excel;
class UploadController extends   Controller{
    //Ajax上传图片
    public function imgUpload()
    {
        $file = Input::file('file');
        $id = Input::get('id');
        $allowed_extensions = ["png", "jpg", "gif"];
        if ($file->getClientOriginalExtension() && !in_array($file->getClientOriginalExtension(), $allowed_extensions)) {
            return ['error' => 'You may only upload png, jpg or gif.'];
        }
        $static_path = env('STATIC_HOME');
        $destinationPath ='web_static/images/';
        $extension = $file->getClientOriginalExtension();
        $fileName = date('Y-m-d').'_'.str_random(10).'.'.$extension;
        $file->move($static_path.$destinationPath, $fileName);
        return Response::json(
            [
                'success' => true,
                'src' => $destinationPath.$fileName,
                'pic' => asset($destinationPath.$fileName),
                'id' => $id
            ]
        )->header('Content-Type','application/json');

    }
    public function fileUpload()
    {
        $file = Input::file('file');
        $id = Input::get('id');
        $allowed_extensions = ["csv", "xls", "xlsx"];
        if ($file->getClientOriginalExtension() && !in_array($file->getClientOriginalExtension(), $allowed_extensions)) {
            return ['error' => '文件格式不正确.'];
        }
        $static_path = env('STATIC_HOME');
        $destinationPath = 'web_static/files/';
        $extension = $file->getClientOriginalExtension();
        $fileName = str_random(10).'.'.$extension;
        $file->move($static_path.$destinationPath, $fileName);
        return Response::json(
            [
                'success' => true,
                'file' => asset($destinationPath.$fileName),
                'id' => $id
            ]
        );
    }

    public static function readFileUpload()
    {
        $file = Input::file('file');
        $id = Input::get('id');
        $allowed_extensions = ["csv", "xls", "xlsx"];
        if ($file->getClientOriginalExtension() && !in_array($file->getClientOriginalExtension(), $allowed_extensions)) {
            return ['result'=>'error','msg' => '文件格式不正确.'];
        }
        $static_path = env('STATIC_HOME');
        $destinationPath = 'web_static/files/';
        $extension = $file->getClientOriginalExtension();
        $fileName = date('Y-m-d').'_'.str_random(10).'.'.$extension;
        $file->move($static_path.$destinationPath, $fileName);
        return (['result'=>'success','msg' => 'ok','file' =>$static_path.$destinationPath.$fileName,'id' => $id]);
    }

    /***
     *
     * @return array
     */
    public function fileUploads()
    {
        $file = Input::file('file');
        $allowed_extensions = ["csv", "xls", "xlsx",'apk','png','jpg'];
        if ($file->getClientOriginalExtension() && !in_array($file->getClientOriginalExtension(), $allowed_extensions)) {
            return ['error' => '文件格式不正确.'];
        }
        $static_path = env('STATIC_HOME');
        $destinationPath = 'web_static/files/';
        $file_size=FunctionController::formatBytes($file->getSize());
        $file_name=$file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileName =date('Ymd').'.'.$extension;
        $file->move($static_path.$destinationPath,$file_name);
        return Response::json(
            [
                'success' => true,
                'file_url' => asset($destinationPath.$fileName),
                'file_name' =>$file_name,
                'file_size' =>$file_size,
            ]
        );
    }
}