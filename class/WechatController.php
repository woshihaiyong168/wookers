<?php
/**
 * Created by PhpStorm.
 * User: alin
 * Date: 2016/12/26
 * Time: 14:54
 */

namespace App\Http\Controllers;


use App\Http\Controllers\Com\CommonController;
use App\Http\Models\Agency\AgencyPoint;
use App\Http\Models\Agency\AgencyUser;
use App\Http\Models\Pay\Order;
use App\Http\Models\Pay\OrderRecharge;
use App\Http\Models\Pay\OrderWechat;
use App\Utils\ConstantUtils;
use App\Utils\PayUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Omnipay\Omnipay;

class WechatController extends Controller{


    /**
     * 微信支付
     * @param int $out_trade_no
     * @param int $total_amount
     * @param string $order_info
     */
    public static function unified($out_trade_no=0,$total_amount=0,$order_info='豆豆语音')
    {
        $validator = Validator::make(['out_trade_no'=>$out_trade_no,'total_amount'=>$total_amount], array('out_trade_no' => 'required|min:1', 'total_amount' => 'required|min:1'),
            array("required" => ":attribute 不能为空"));
        if ($validator->fails()) {
            echo json_encode(['code'=>-1,'msg'=>'支付参数错误']);exit;
        }
        $gateway    = Omnipay::create('WechatPay');
        $gateway->setAppId(PayUtils::WX_APP_ID);
        $gateway->setMchId(PayUtils::WX_MACH_ID);
        $gateway->setApiKey(PayUtils::WX_API_KEY);
        $gateway->setNotifyUrl(PayUtils::WX_NOTIFY_URL());
        $gateway->setTradeType('APP');
        $order = array (
            'body'             => $order_info, //Your order ID
            'out_trade_no'     => $out_trade_no, //Should be format 'YmdHis'
            'total_fee'        => intval($total_amount), //Order Title
            'spbill_create_ip' => '114.119.110.120', //Order Total Fee
        );
        $response = $gateway->purchase($order)->send();
        /*        Log::info('微信支付');
                Log::info($response->getData());
                $response->getData(); //For debug
                $response->getAppOrderData(); //For WechatPay_App
                $response->getJsOrderData(); //For WechatPay_Js
                $response->getCodeUrl(); //For Native Trade Type
                QrCode::size(300)->generate($response->getCodeUrl()
        */
        if($response->isSuccessful()){
            Log::info('微信支付下单成功');
            Log::info($response->getAppOrderData());
            try{
                $wx['order_no']=$out_trade_no;
                $wx['total_fee']=$total_amount;
                $wx['appid']=$response->getAppOrderData()['appid'];
                $wx['partnerid']=$response->getAppOrderData()['partnerid'];
                $wx['prepayid']=$response->getAppOrderData()['prepayid'];
                $wx['package']=$response->getAppOrderData()['package'];
                $wx['noncestr']=$response->getAppOrderData()['noncestr'];
                $wx['timestamp']=$response->getAppOrderData()['timestamp'];
                $wx['sign']=$response->getAppOrderData()['sign'];
                //OrderWechat::insert($wx);
                DB::connection('app')
                    ->table('order_wechat'.date('Ym'))
                    ->insert($wx);
            } catch (\Exception $e) {
                Log::info('下单保存异常');
            }
            echo  json_encode(['code'=>1,'msg'=>'下单成功','data'=>$response->getAppOrderData()]);
        }else{
            echo json_encode(['code'=>-1,'msg'=>'下单失败']);
        }
    }

    /**
     * 微信支付通知
     * @param Request $request
     * @return string
     */
    public function notify(Request $request){
        $gateway    = Omnipay::create('WechatPay');
        $gateway->setAppId(PayUtils::WX_APP_ID);
        $gateway->setMchId(PayUtils::WX_MACH_ID);
        $gateway->setApiKey(PayUtils::WX_API_KEY);
        $gateway->setNotifyUrl(PayUtils::WX_NOTIFY_URL());
        $response = $gateway->completePurchase([
            'request_params' => file_get_contents('php://input')
        ])->send();
        if ($response->isPaid()) {
            $data=PublicController::xmlToArray(file_get_contents('php://input'));
            Log::info($data);
            if($data['result_code']=='SUCCESS'){
                Log::info('微信支付通知:');
                $recharge_status=Order::select('id','pay_status','platform')
                    ->where(['id'=>$data['out_trade_no']])
                    ->first();
                if(empty($recharge_status)){
                    Log::info('微信订单不存在:');
                    return 'SUCCESS';
                }
                if($recharge_status->pay_status==ConstantUtils::ORDER_PAY_STATUS_BEFORE){
                    Order::where(['id'=>$data['out_trade_no']])->update(['status'=>ConstantUtils::ORDER_STATUS_OPEN,
                        'pay_status' => ConstantUtils::ORDER_PAY_STATUS_OPEN,'update_time'=>date('Y-m-d H:i:s')]);
                    $order=Order::select('id','amount','money','uid','platform')
                        ->where(['id'=>$data['out_trade_no']])
                        ->first();
                    if($order->platform!=ConstantUtils::ORDER_PLATFORM_AGENCY_POINT){
                        CommonController::addPoint($order->id,$order->amount,$order->money,$order->uid,$order->platform);
                        return 'SUCCESS';
                    }else{
                        //代充加点
                        Log::info('微信代充加点');
                             Order::where(['id'=>intval($data['out_trade_no'])])
                                 ->update(['status'=>ConstantUtils::ORDER_STATUS_OPEN,
                            'pay_status' => ConstantUtils::ORDER_PAY_STATUS_OPEN,'update_time'=>date('Y-m-d H:i:s')]);
                        $userPoint=AgencyUser::select('id','point_account','point_buy')
                            ->where(['main_uid'=>intval($order->uid)])->first();
                        if(!empty($userPoint)){
                            $agencyCount=AgencyPoint::where(['order_no'=>intval($data['out_trade_no']),'status'=>ConstantUtils::ORDER_STATUS_OPEN])->count('id');
                            if($agencyCount>0){
                                Log::info('充点状态已关闭');
                                return 'SUCCESS';
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
                        }
                        return 'SUCCESS';
                    }
                }else{
                    Log::info('微信平台订单已关闭');
                    Log::info(intval($recharge_status->id));
                    if($recharge_status->platform!=ConstantUtils::ORDER_PLATFORM_AGENCY_POINT){
                        Log::info('_充值点数');
                    }else{
                        Log::info('_充值钻石');
                    }
                    Log::info($data);
                    return 'SUCCESS';
                }
            }else{
                Log::info('微信支付失败:');
                Order::where(['id'=>$data['out_trade_no']])
                    ->update(['status'=>ConstantUtils::ORDER_STATUS_CLOSE,
                    'pay_status'=>ConstantUtils::ORDER_PAY_STATUS_CLOSE,'update_time'=>date('Y-m-d H:i:s')]);
                return 'SUCCESS';
            }
        }else{
            return 'SUCCESS';
        }
    }

    /**
     * 订单查询
     */
    private function query(){
        $gateway    = Omnipay::create('WechatPay');
        $gateway->setAppId(PayUtils::WX_APP_ID);
        $gateway->setMchId(PayUtils::WX_MACH_ID);
        $gateway->setApiKey(PayUtils::WX_API_KEY);
        $gateway->setNotifyUrl(PayUtils::WX_NOTIFY_URL());
        $response = $gateway->query([
            'out_trade_no' => '20161228203543', //自定义订单号
            /*          'transaction_id'=>'4010162001201701024933178150'*/
        ])->send();
        if($response->isSuccessful()){
            return $response->getData();
        }else{
            return false;
        }
    }

    /**
     * 关闭订单
     */
    public function close(){
        $gateway    = Omnipay::create('WechatPay');
        $gateway->setAppId(PayUtils::WX_APP_ID);
        $gateway->setMchId(PayUtils::WX_MACH_ID);
        $gateway->setApiKey(PayUtils::WX_API_KEY);
        $gateway->setNotifyUrl(PayUtils::WX_NOTIFY_URL());
        $response = $gateway->close([
            'out_trade_no' => '201602011315231245', //The merchant trade no
        ])->send();
        if($response->isSuccessful()){
        }else{

        }
    }

    /**
     * 申请退款
     */
    public function refund(){
        $gateway    = Omnipay::create('WechatPay');
        $gateway->setAppId(PayUtils::WX_APP_ID);
        $gateway->setMchId(PayUtils::WX_MACH_ID);
        $gateway->setApiKey(PayUtils::WX_API_KEY);
        $gateway->setNotifyUrl(PayUtils::WX_NOTIFY_URL());
        $certPath=1;
        $keyPath=2;
        $outRefundNo=3;
        $gateway->setCertPath($certPath);
        $gateway->setKeyPath($keyPath);

        $response = $gateway->refund([
            'transaction_id' => '1217752501201407033233368018', //The wechat trade no
            'out_refund_no' => $outRefundNo,
            'total_fee' => 1, //=0.01
            'refund_fee' => 1, //=0.01
        ])->send();
        if($response->isSuccessful()){
            Log::info($response->getData());
        }else{

        }
    }
}