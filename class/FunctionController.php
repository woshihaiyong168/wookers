<?php
/**
 * Created by PhpStorm.
 * User: alin
 * Date: 2016/11/24
 * Time: 14:54
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Models\ApiLog;
use App\Http\Models\App\Pic;
use App\Http\Requests;
use App\Utils\ServiceUtils;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class FunctionController extends Controller
{

    /**
     * 二维数组排序
     * @param $arrUsers
     * @param string $sort_direction
     * @param string $sort_field
     * @return mixed
     */
    public static function arr_sort($arrUsers, $sort_direction = 'SORT_DESC', $sort_field)
    {
        $sort = array(
            'direction' => $sort_direction, //排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
            'field' => $sort_field    //排序字段
        );
        $arrSort = array();
        foreach ($arrUsers AS $uniqid => $row) {
            foreach ($row AS $key => $value) {
                $arrSort[$key][$uniqid] = $value;
            }
        }
        if ($sort['direction']) {
            array_multisort($arrSort[$sort['field']], constant($sort['direction']), $arrUsers);
        }
        return $arrUsers;
    }

    /**
     * 根据字段值去重
     * @param $arr
     * @param $column
     * @param $condition
     * @param $find_string
     * @return mixed
     */
    public static function string_filter($arr, $column, $condition,$find_string)
    {
        foreach ($arr as $key => &$value) {
            $value=(array)$value;
            if($condition=='>'){
                if ($value[$column] < $find_string) {
                    unset($arr[$key]);
                }
            }

            if($condition=='<'){
                if ($value[$column] > $find_string) {
                    unset($arr[$key]);
                }
            }
            if($condition=='='){
                if ($value[$column] != $find_string) {
                    unset($arr[$key]);
                }
            }
            if($condition=='!='){
                if ($value[$column] == $find_string) {
                    unset($arr[$key]);
                }
            }
        }
        return $arr;
    }

    /**
     * 返回或不存在值
     * @param $arr
     * @param $column
     * @param $find_string
     * @return mixed
     */
    public static function string_filter_empty($arr, $column, $find_string)
    {
        foreach ($arr as $key => &$value) {
            if ($find_string == 'true') {
                if (!empty($value[$column])) {
                    unset($arr[$key]);
                }
            } else {
                if (empty($value[$column])) {
                    unset($arr[$key]);
                }
            }
        }
        return $arr;
    }
    /**
     * 多维数组查找
     * 返回不匹配值
     * @param $arr
     * @param $column
     * @param $find_arr
     * @return mixed
     */
    public static function arr_filter_diff($arr, $column, $find_arr)
    {
        foreach ($arr as $key => &$value) {
            foreach ($find_arr as $k => $v) {
                if ($value[$column] == $v) {
                    unset($arr[$key]);
                }
            }
        }
        return $arr;
    }

    /**
     * 多维数组查找
     * 返回配值
     * @param $arr
     * @param $column
     * @param $find_arr
     * @return mixed
     */
    public static function arr_filter($arr, $column, $find_arr)
    {
        foreach ($arr as $key => &$value) {
            foreach ($find_arr as $k => $v) {
                if ($value[$column] != $v) {
                    unset($arr[$key]);
                }
            }
        }
        return $arr;
    }

    /**
     * 多维数组查找
     * 返回匹配值
     * @param $arr
     * @param $column
     * @param $find_string
     * @return bool
     */
    public static function  array_search_multi($arr, $column, $find_string)
    {
        foreach ($arr as $key => $value) {
            if ($value[$column] == $find_string)
                return $value;
        }
        return false;
    }

    /**
     * 多维数组去重
     * @param $array2D
     * @param bool $stkeep
     * @param bool $ndformat
     * @return mixed
     */
    function unique_arr($array2D, $stkeep = false, $ndformat = true)
    {
        // 判断是否保留一级数组键 (一级数组键可以为非数字)
        if ($stkeep) $stArr = array_keys($array2D);
        // 判断是否保留二级数组键 (所有二级数组键必须相同)
        if ($ndformat) $ndArr = array_keys(end($array2D));
        //降维,也可以用implode,将一维数组转换为用逗号连接的字符串
        foreach ($array2D as $v) {

            $v = join(",，，", $v);
            $temp[] = $v;
        }
        //去掉重复的字符串,也就是重复的一维数组
        $temp = array_unique($temp);
        //再将拆开的数组重新组装
        foreach ($temp as $k => $v) {
            if ($stkeep) $k = $stArr[$k];
            if ($ndformat) {
                $tempArr = explode(",，，", $v);
                foreach ($tempArr as $ndkey => $ndval) $output[$k][$ndArr[$ndkey]] = $ndval;
            } else $output[$k] = explode(",，，", $v);
        }
        return $output;
    }
    //数组字段替换
    public static function arrRenameKey($arr,$column){
        $column_key=array_values($column);
        foreach($arr as $key=>& $value){
            foreach($column as $k=>$v){
                $value[$v]=$value[$k];
                unset($value[$k]);
            }
            foreach(array_keys($value) as $ks=>$vs){
                if(!in_array($vs,$column_key)){
                    unset($value[$vs]);
                }
            }
        }
        return $arr;
    }
    /**
     * @author alin
     * @date 2016-11-24
     * @description success
     * @param string $action
     * @param string $msg
     */
    public static function success($action = '/', $msg = '操作成功')
    {
        echo "<script language='javascript' type='text/javascript'>
        alert('$msg');
        window.location.href='$action';
        </script> ";
    }

    /**
     * @author alin
     * @date 2016-11-24
     * @description error
     * @param string $action
     * @param string $msg
     */
    public static function error($action = '/', $msg = '操作失败')
    {
        echo "<script language='javascript' type='text/javascript'>
        alert('$msg');
        window.location.href='$action';
        </script> ";
    }

    /**
     * @author alin
     * @date 2016-11-24
     * @description 通用的页面提示与跳转
     * @param $msgTitle
     * @param $message
     * @param $jumpUrl
     */
    public static function message($msgTitle, $message, $jumpUrl)
    {
        $str = '<!DOCTYPE HTML>';
        $str .= '<html>';
        $str .= '<head>';
        $str .= '<meta charset="utf-8">';
        $str .= '<title>页面提示</title>';
        $str .= '<style type="text/css">';
        $str .= '*{margin:0; padding:0}a{color:#369; text-decoration:none;}a:hover{text-decoration:underline}body{height:100%; font:12px/18px Tahoma, Arial,  sans-serif; color:#424242; background:#fff}.message{width:450px; height:120px; margin:16% auto; border:1px solid #99b1c4; background:#ecf7fb}.message h3{height:28px; line-height:28px; background:#2c91c6; text-align:center; color:#fff; font-size:14px}.msg_txt{padding:10px; margin-top:8px}.msg_txt h4{line-height:26px; font-size:14px}.msg_txt h4.red{color:#f30}.msg_txt p{line-height:22px}';
        $str .= '</style>';
        $str .= '</head>';
        $str .= '<body>';
        $str .= '<div class="message">';
        $str .= '<h3>' . $msgTitle . '</h3>';
        $str .= '<div class="msg_txt">';
        $str .= '<h4 class="red">' . $message . '</h4>';
        $str .= "<p>系统将在 <span style='color:blue;font-weight:bold'>3</span> 秒后自动跳转,如果不想等待,直接点击<a href=$jumpUrl>这里</a> 跳转</p>";
        //$str .= "<script>setTimeout('location.replace('".$jumpUrl."')',2000)</script>";
        $str .= "<script>setTimeout(function(){window.location.href='$jumpUrl';},1000)</script>";
        $str .= '</div>';
        $str .= '</div>';
        $str .= '</body>';
        $str .= '</html>';
        echo $str;
        exit;
    }


    /**
     * curl
     * @param string $url
     * @param string $data
     * @param string $request_type
     * @param array $header
     * @return bool|mixed
     */
    public static function curl_request($url = '', $data = '', $request_type = 'get')
    {
        $ch = curl_init();
        $timeout = 10;
        if ($request_type == 'get') {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            if(!empty($data)){
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
/*        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);*/
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);//发起连接前等待
        curl_setopt($ch, CURLOPT_TIMEOUT,10);//接收数据时超时设置
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
            'Accept: application/json',
        ));
        $handles = curl_exec($ch);

        $data = json_encode($data);
        Log::info('异步接口请求:' . $url . $request_type . $data);
        // FunctionController::api_log($url,$data,$request_type,'success');
        curl_close($ch);
        if ($handles) {
            return $handles;
        } else {
            return false;
        }
    }

    /**
     * 发送短信
     * @param $mobile
     * @param $msg
     * @return bool|mixed
     */
    public static function sendSMS($mobile,$msg) {
        $postArr = array (
            'accountSid' => ServiceUtils::SMS_API_ACCOUNT,
            'smsContent' => $msg,
            'to' => $mobile,
            'timestamp'=>date('YmdHis'),
            'sig'=>md5(ServiceUtils::SMS_API_ACCOUNT.ServiceUtils::SMS_API_PASSWORD.date("YmdHis")),
        );
        $result =  FunctionController::curl_request(ServiceUtils::SMS_API_SEND_URL,$postArr,'post');
        Log::info($result);
        return $result;
    }

    /**
     * 订单号生成
     * @return bool|string
     */
    public static function createOrderCode()
    {
        return date('80ymdH');
    }

    /**
     * 苹果内支付
     * 21000 App Store不能读取你提供的JSON对象
     * 21002 receipt-data域的数据有问题
     * 21003 receipt无法通过验证
     * 21004 提供的shared secret不匹配你账号中的shared secret
     * 21005 receipt服务器当前不可用
     * 21006 receipt合法，但是订阅已过期。服务器接收到这个状态码时，receipt数据仍然会解码并一起发送
     * 21007 receipt是Sandbox receipt，但却发送至生产系统的验证服务
     * 21008 receipt是生产receipt，但却发送至Sandbox环境的验证服务
     */
    public static function getReceiptData($receipt, $isSandbox = false)
    {
        $buy_url = 'https://buy.itunes.apple.com/verifyReceipt';//真实运营地址
        $sandbox_url = 'https://sandbox.itunes.apple.com/verifyReceipt';//真实运营地址
        if ($isSandbox) {
            $endpoint = $sandbox_url;
        } else {
            $endpoint = $buy_url;
        }
        $postData = json_encode(
            array('receipt-data' => ($receipt))
        );
        $ch = curl_init();
        $timeout = 100;
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);//跟随页面的跳转
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        //$errmsg   = curl_error($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        if (($endpoint == $buy_url) && ($data['status'] == 21007)) {
            return FunctionController::getReceiptData($receipt, true);
        } else {
            if ($errno != 0) {//curl请求有错误
                return [
                    'status' => -1,
                    'msg' => '请求超时，请稍后重试',
                ];
            }
            if (!is_array($data)) {
                return [
                    'status' => -2,
                    'msg' => '无效的响应数据',
                ];
            }
            if (!isset($data['status']) || $data['status'] != 0) {
                return [
                    'status' => -3,
                    'msg' => '无效的收据',
                ];
            }
            $data = json_decode($response, true);
            if ($isSandbox) {
                $data['sand_box']=1;
            } else {
                $data['sand_box']=0;
            }
            return $data;
        }
    }

    /**
     * 创建子动态目录
     * @param $path
     * @return string
     */
    public static function mageFolder($path){
        $path = $path.date('Ym');
        if(File::exists($path)){
            if(File::exists($path.'/'.date('d'))){
                return $path.'/'.date('d').'/';
            }else{
                File::makeDirectory($path.'/'.date('d'));
                return $path.'/'.date('d').'/';
            }
        }else{
            echo $path.'/'.date('d');
            File::makeDirectory($path);
            File::makeDirectory($path.'/'.date('d'));
            return $path.'/'.date('d').'/';
        }
    }

    public static function array_diff_assoc_recursive($array1,$array2){
        $diffarray=array();
        foreach ($array1 as $key=>$value){
            //判断数组每个元素是否是数组
            if(is_array($value)){
                //判断第二个数组是否存在key
                if(!isset($array2[$key])){
                    $diffarray[$key]=$value;
                    //判断第二个数组key是否是一个数组
                }elseif(!is_array($array2[$key])){
                    $diffarray[$key]=$value;
                }else{
                    $diff=FunctionController::array_diff_assoc_recursive($value, $array2[$key]);
                    if($diff!=false){
                        $diffarray[$key]=$diff;
                    }
                }
            }elseif(!array_key_exists($key, $array2) || $value!==$array2[$key]){
                $diffarray[$key]=$value;
            }
        }
        return $diffarray;
    }

    /**
     * 获取java图片
     * @param $photo_name
     * @param $type
     * @param bool $flush
     * @return bool|string
     */
    public static function get_img($photo_name, $type,$flush=false)
    {
        $static_path = env('STATIC_HOME');
        $file_path = 'web_static/cache_pic_home/';
        $file_name = $photo_name;
        $newFolder=FunctionController::mageFolder($static_path.$file_path);
        $saveFolder=$file_path.date('Ym').'/'.date('d').'/'.$file_name;
        $result = Pic::where(['name' => $photo_name, 'type' => $type])
            ->first();
        if (empty($result)) {
            $file_url = FunctionController::curl_request(ServiceUtils::currentJavaUrl(), ['table' => 'getPic', 'name' => $photo_name, 'type' => $type]);
            if ($file_url&&(strlen($file_url)>25)) {
                $file = fopen($newFolder .$file_name, "w");//打开文件准备写入
                fwrite($file, $file_url);//写入
                fclose($file);//关闭
                 Pic::insert(['name' => $photo_name, 'file_path' => $saveFolder, 'type' => $type]);
                return $saveFolder;
            } else {
                return false;
            }
        } else {
            if($flush){
                Pic::where(['name' => $photo_name, 'type' => $type])->delete();
                $file_url = FunctionController::curl_request(ServiceUtils::currentJavaUrl(), ['table' => 'getPic', 'name' => $photo_name, 'type' => $type]);
                if ($file_url&&(strlen($file_url)>25)) {
                    $file = fopen($newFolder .$file_name, "w");//打开文件准备写入
                    fwrite($file, $file_url);//写入
                    fclose($file);//关闭
                    Pic::insert(['name' => $photo_name, 'file_path' => $saveFolder, 'type' => $type]);
                    return $saveFolder;
                } else {
                    return false;
                }
            }else{
                return $result->file_path;
            }
        }
    }

    /**
     * 数组转xml
     * @param $arr
     * @return string
     */
    public static function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
        }
        $xml .= "</xml>";
        return $xml;
    }
    /**
     * 数字转中文
     * @param $num
     * @return string
     */
    public static function numToWord($num)
    {
        $chiNum = array('零', '一', '二', '三', '四', '五', '六', '七', '八', '九');
        $chiUni = array('', '十', '百', '千', '万', '亿', '十', '百', '千');
        $chiStr = '';
        $num_str = (string)$num;
        $count = strlen($num_str);
        $last_flag = true; //上一个 是否为0
        $zero_flag = true; //是否第一个
        $temp_num = null; //临时数字

        $chiStr = '';//拼接结果
        if ($count == 2) {//两位数
            $temp_num = $num_str[0];
            $chiStr = $temp_num == 1 ? $chiUni[1] : $chiNum[$temp_num] . $chiUni[1];
            $temp_num = $num_str[1];
            $chiStr .= $temp_num == 0 ? '' : $chiNum[$temp_num];
        } else if ($count > 2) {
            $index = 0;
            for ($i = $count - 1; $i >= 0; $i--) {
                $temp_num = $num_str[$i];
                if ($temp_num == 0) {
                    if (!$zero_flag && !$last_flag) {
                        $chiStr = $chiNum[$temp_num] . $chiStr;
                        $last_flag = true;
                    }
                } else {
                    $chiStr = $chiNum[$temp_num] . $chiUni[$index % 9] . $chiStr;

                    $zero_flag = false;
                    $last_flag = false;
                }
                $index++;
            }
        } else {
            $chiStr = $chiNum[$num_str[0]];
        }
        return $chiStr;
    }


    /**
     * 接口调用日志
     * @param $url
     * @param $param
     * @param $type
     * @param $msg
     */
    protected static function api_log($url, $param, $type, $msg)
    {

    }

    /**
     * 验证分页
     * @param $page
     * @return mixed
     */
    public static function check_page($page)
    {
        $page = json_decode($page, true);
        $page_data['lastId'] = isset($page['lastId']) ? $page['lastId'] : 0;
        $page_data['pageSize'] = isset($page['pageSize']) ? $page['pageSize'] : 5;
        $page_data['sortType'] = isset($page['sortType']) ? $page['sortType'] : "DESC";
        return $page_data;
    }

    /**
     * 验证sign
     * @param string $sign
     * @param string $app_id
     * @param string $time
     * @param string $Secret_key
     * @return bool
     */
    public static function check_sign($sign = '', $app_id = '', $time = '', $Secret_key = '')
    {
        $sign = json_decode($sign, true);
        $sign_data['app_id'] = isset($sign['app_id']) ? $sign['app_id'] : "";
        $sign_data['time'] = isset($sign['time']) ? $sign['time'] : "";
        $sign_data['Secret_key'] = isset($sign['Secret_key']) ? $sign['Secret_key'] : "";
        if (md5($app_id . $time . $Secret_key) == md5($sign_data['app_id'] . $sign_data['time'] . $sign_data['Secret_key'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * db加签
     * @param array $sign
     * @return string
     */
    public static function db_sign($sign = [])
    {
        $sign_string = 'db';
        if (count($sign)) {
            foreach ($sign as $key => $value) {
                $sign_string .= $value;
            }
        }
        return md5($sign_string);
    }

    /**
     * 根据评分数，显示几颗星星
     * @param $score int 评分
     * @date 2016/07/14
     * @return int
     */
    public static function handle_score($score = 0)
    {
        $offset = 90;
        if (!is_numeric($score) || $score <= 0) {
            return $offset;
        }

        //判断是否是小数
        if (is_float($score)) {
            $score_arr = explode('.', round($score, 1));
            $score_int = $score_arr[1]; //取小数点后数字
        }

        //星星背景偏移量计算
        if ($score > 0 && $score < 1) {
            $offset = 90 - $score_int;
        } elseif ($score == 1) {
            $offset = 70 - $score_int + 5;
        } elseif ($score > 1 && $score < 2) {
            $offset = 70 - $score_int + 1;
        } elseif ($score == 2) {
            $offset = 50 - $score_int + 6;
        } elseif ($score > 2 && $score < 3) {
            $offset = 50 - $score_int + 2;
        } elseif ($score == 3) {
            $offset = 30 - $score_int + 7;
        } elseif ($score > 3 && $score < 4) {
            $offset = 30 - $score_int + 3;
        } elseif ($score == 4) {
            $offset = 10 - $score_int + 8;
        } elseif ($score >= 4 && $score < 5) {
            $offset = 10 - $score_int + 4;
        } elseif ($score >= 5) {
            $offset = 0;
        }
        return $offset;
    }

    /**
     * 对象转数组
     * @param $array
     * @return array
     */
    public static function object_array($array) {
        if(is_object($array)) {
            $array = (array)$array;
        } if(is_array($array)) {
            foreach($array as $key=>$value) {
                $array[$key] = self::object_array($value);
            }
        }
        return $array;
    }

    /**
     * 格式化文件单位
     * @param $size
     * @return string
     */
    public static function formatBytes($size) {
        $units = array(' B', ' KB', ' MB', ' GB', ' TB');
        for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
        return round($size, 2).$units[$i];
    }

}