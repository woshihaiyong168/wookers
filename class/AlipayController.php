<?php
/**
 * Created by PhpStorm.
 * User: alin
 * Date: 2016/12/26
 * Time: 21:52
 */

namespace App\Http\Controllers;


use App\Http\Controllers\Com\CommonController;
use App\Http\Models\Agency\AgencyPoint;
use App\Http\Models\Agency\AgencyUser;
use App\Http\Models\Pay\Order;
use App\Http\Models\Pay\OrderAlipay;
use App\Http\Models\Pay\OrderRecharge;
use App\Utils\ConstantUtils;
use App\Utils\PayUtils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Omnipay\Omnipay;
use Illuminate\Http\Request;
use App\Http\Requests;
use Illuminate\Support\Facades\Log;

class AlipayController extends Controller{

    /**
     * 支付宝支付
     * @param int $out_trade_no
     * @param int $total_amount
     * @param string $order_info
     */
    public static function unified($out_trade_no=0,$total_amount=0,$order_info='豆豆语音'){
        $validator = Validator::make(['out_trade_no'=>$out_trade_no,'total_amount'=>$total_amount], array('out_trade_no' => 'required|min:1', 'total_amount' => 'required|min:1'),
            array("required" => ":attribute 不能为空"));
        if ($validator->fails()) {
            return  json_encode(['code'=>-1,'msg'=>'支付参数错误']);
        }
//        $gateway = Omnipay::create('Alipay_AopApp');
//        $gateway->setAppId(PayUtils::ALI_APP_ID);
//        $gateway->setPrivateKey(PayUtils::ALI_PRIVATE_KEY);
//        $gateway->setEncryptKey(PayUtils::ALI_ENCRYPT_KEY);
//        $gateway->setAlipayPublicKey(PayUtils::ALI_PUBLIC_KEY);
//        $request = $gateway->purchase();
//        $request->setBizContent([
//            'subject'      =>$order_info,
//            'out_trade_no' =>$out_trade_no,
//            'total_amount' =>$total_amount/100,
//            'product_code' => 'QUICK_APP_PAY',
//        ]);
        $gateway = Omnipay::create('Alipay_LegacyApp');
        $gateway->setPartner(PayUtils::ALi_PARTNER_ID);
        $gateway->setSellerId(PayUtils::ALi_PARTNER_ID);
        $gateway->setPrivateKey(PayUtils::ALI_PRIVATE_KEY);
        $gateway->setAlipayPublicKey(PayUtils::ALI_PUBLIC_KEY);
        $gateway->setNotifyUrl(PayUtils::ALI_NOTIFY_URL());
        $request = $gateway->purchase([
            'subject'      => $order_info,
            'out_trade_no' => $out_trade_no,
            'total_fee' => $total_amount/100,
        ]);
        $response = $request->send();
        if($response->isSuccessful()){
            Log::info('支付宝下单成功:');
            Log::info($response->getData());
            /*
                        $aliPay['order_no']=$out_trade_no;
                        $aliPay['total_amount']=$total_amount;
                        $aliPay['alipay_sdk']=$response->getData()['alipay_sdk'];
                        $aliPay['app_id']=$response->getData()['app_id'];
                        $aliPay['biz_content']=$response->getData()['biz_content'];
                        $aliPay['charset']=$response->getData()['charset'];
                        $aliPay['format']=$response->getData()['format'];
                        $aliPay['method']=$response->getData()['method'];
                        $aliPay['notify_url']=$response->getData()['notify_url'];
                        $aliPay['sign_type']=$response->getData()['sign_type'];
                        $aliPay['timestamp']=$response->getData()['timestamp'];
                        $aliPay['version']=$response->getData()['sign_type'];
                        $aliPay['sign']=$response->getData()['sign'];
                        $aliPay['order_string']=$response->getData()['order_string'];
                        OrderAlipay::insert($aliPay);*/
            try {
                $aliPay=$response->getData();
                $data_ali=$response->getData()['order_string'];
                parse_str($data_ali,$aliPay_data);
                $aliPay['total_amount']=$aliPay_data['total_fee'];
                $aliPay['order_no']=$aliPay_data['out_trade_no'];
                $aliPay['alipay_sdk']=$aliPay_data['alipay_sdk'];
                $aliPay['biz_content']=$aliPay_data['subject'];
                $aliPay['sign']=$aliPay_data['sign'];
                $aliPay['sign_type']=$aliPay_data['sign_type'];
               // OrderAlipay::insert($aliPay);
                DB::connection('app')
                    ->table('order_alipay'.date('Ym'))
                    ->insert($aliPay);
            } catch (\Exception $e) {
                Log::info('下单异常');
            }
            echo json_encode(['code'=>1,'msg'=>'下单成功','data'=>$response->getData()]);
        }else{
            echo json_encode(['code'=>-1,'msg'=>'下单失败','data'=>$response->getData()]);
        }
    }

    /**
     * 异步通知
     * @param Request $request
     * @return string
     */
    public function notify(Request $request){
        Log::info('支付宝支付通知');
        if(count($_POST)==0){
            Log::info('异常请求');
            return 'fail';
        }
        Log::info($_POST);
        if((isset($_POST['trade_status']))&&($_POST['trade_status']=='TRADE_SUCCESS')){
            $recharge_status=Order::select('recharge_status','money','pay_status','platform')
                ->where(['id'=>$_POST['out_trade_no']])
                ->first();
            if(empty($recharge_status)){
                Log::info('订单不存在');
                return 'fail';
            }
            if($recharge_status->pay_status!=ConstantUtils::ORDER_PAY_STATUS_BEFORE){
                Log::info('支付宝订单已关闭');
                Log::info($_POST['out_trade_no']);
                Log::info('支付宝订单已关闭');
                if($recharge_status->platform!=ConstantUtils::ORDER_PLATFORM_AGENCY_POINT){
                    Log::info('_充值点数');
                }else{
                    Log::info('_充值钻石');
                }
                Log::info($_POST);
                return 'success';
            }
            if(!isset($_POST['total_fee'])){
                Log::info('参数异常,金额异常');
                return 'fail';
            }
            if($recharge_status->money==intval($_POST['total_fee'])){
                Order::where(['id'=>$_POST['out_trade_no']])
                    ->update(['status'=>ConstantUtils::ORDER_STATUS_OPEN,
                    'pay_status'=>ConstantUtils::ORDER_PAY_STATUS_OPEN,'update_time'=>date('Y-m-d H:i:s')]);
                $order=Order::select('id','amount','money','uid','platform')
                    ->where(['id'=>$_POST['out_trade_no']])
                    ->first();
                if($order->platform!=ConstantUtils::ORDER_PLATFORM_AGENCY_POINT){
                    CommonController::addPoint($order->id,$order->amount,$order->money,$order->uid,$order->platform);
                    return 'success';
                }else{
                    Log::info('支付宝代充加点');
                        Order::where(['id'=>intval($_POST['out_trade_no'])])
                            ->update(['status'=>ConstantUtils::ORDER_STATUS_OPEN,
                        'pay_status' => ConstantUtils::ORDER_PAY_STATUS_OPEN,'recharge_status'=>ConstantUtils::ORDER_RECHARGE_STATUS_OPEN,'update_time'=>date('Y-m-d H:i:s')]);
                        $userPoint=AgencyUser::select('id','point_account','point_buy')
                            ->where(['main_uid'=>intval($order->uid)])->first();
                    if($userPoint){
                        $agencyCount=AgencyPoint::where(['order_no'=>intval($_POST['out_trade_no']),'status'=>ConstantUtils::ORDER_STATUS_OPEN])->count('id');
                        if($agencyCount>0){
                            Log::info('充点状态已关闭');
                            return 'success';
                        }
                        AgencyPoint::insert(['order_no'=>intval($order->id),'money'=>$order->money,'status'=>ConstantUtils::ORDER_STATUS_OPEN,
                            'pre_point'=>intval($userPoint->point_account),'point'=>(intval($order->money)*ConstantUtils::MONET_EXCHANGE_POINT),
                            'uid'=>intval($order->uid),'agency_id'=>$userPoint->id,'create_time'=>date('Y-m-d H:i:s')]);
                        AgencyUser::where(['main_uid'=>$order->uid])
                            ->update(['point_account'=>intval($userPoint->point_account)+(intval($order->money)*ConstantUtils::MONET_EXCHANGE_POINT),
                            'point_buy'=>intval($userPoint->point_buy)+(intval($order->money)*ConstantUtils::MONET_EXCHANGE_POINT)]);
                        OrderRecharge::insert(['order_id'=>intval($order->id),'uid'=>intval($order->uid),
                            'amount'=>(intval($order->money)*ConstantUtils::MONET_EXCHANGE_POINT),'money'=>$order->money,
                            'platform'=>intval($order->platform),'productIdentifier'=>1,'status'=>1,'create_time'=>date('Y-m-d H:i:s'),'update_time'=>date('Y-m-d H:i:s')]);
                    }else{
                        Log::info('未找到该代理用户');
                    }
                    return 'success';
                }
            }else{
                Log::info('金额不对');
                return 'fail';
            }
        }else{
            Log::info('异常参数');
            return 'fail';
        }
    }
}