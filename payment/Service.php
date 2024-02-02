<?php

declare (strict_types=1);

namespace app\payment;

use app\payment\model\PaymentRecord;
use app\payment\service\Payment;
use app\shop\model\ShopOrder;
use app\payment\command\Trans;
use app\shop\service\UserOrder;
use think\admin\extend\CodeExtend;
use think\admin\Plugin;
use think\Request;

/**
 * 组件注册服务
 * @class Service
 * @package app\payment
 */
class Service extends Plugin
{
    /**
     * 定义插件名称
     * @var string
     */
    protected $appName = '支付管理';

    /**
     * 定义安装包名
     * @var string
     */
    protected $package = 'xiaochao/think-plugs-payment';

    /**
     * 插件服务注册
     * @return void
     */
    public function register(): void
    {
        $this->commands([Trans::class]);

        // 注册支付通知路由
        $this->app->route->any('/plugin-payment-notify/:vars', function (Request $request) {
            try {
                $data = json_decode(CodeExtend::deSafe64($request->param('vars')), true);
                return Payment::mk($data['channel'])->notify($data);
            } catch (\Exception|\Error $exception) {
                return 'Error: ' . $exception->getMessage();
            }
        });

        // 注册支付审核事件
        $this->app->event->listen('PluginPaymentAudit', function (PaymentRecord $payment) {
            $this->app->log->notice("Event PluginPaymentAudit {$payment->getAttr('order_no')}");
            $order = ShopOrder::mk()->where(['order_no' => $payment->getAttr('order_no')])->findOrEmpty();
            $order->isExists() && UserOrder::payment($order, $payment);
        });

        // 注册支付拒审事件
        $this->app->event->listen('PluginPaymentRefuse', function (PaymentRecord $payment) {
            $this->app->log->notice("Event PluginPaymentRefuse {$payment->getAttr('order_no')}");
            $order = ShopOrder::mk()->where(['order_no' => $payment->getAttr('order_no')])->findOrEmpty();
            $order->isExists() && UserOrder::payment($order, $payment);
        });

        // 注册支付完成事件
        $this->app->event->listen('PluginPaymentSuccess', function (PaymentRecord $payment) {
            $this->app->log->notice("Event PluginPaymentSuccess {$payment->getAttr('order_no')}");
            $order = ShopOrder::mk()->where(['order_no' => $payment->getAttr('order_no')])->findOrEmpty();
            $order->isExists() && UserOrder::payment($order, $payment);
        });

        // 注册支付取消事件
        $this->app->event->listen('PluginPaymentCancel', function (PaymentRecord $payment) {
            $this->app->log->notice("Event PluginPaymentCancel {$payment->getAttr('order_no')}");
            $order = ShopOrder::mk()->where(['order_no' => $payment->getAttr('order_no')])->findOrEmpty();
            $order->isExists() && UserOrder::payment($order, $payment);
        });

        // 注册订单确认事件
        $this->app->event->listen('PluginPaymentConfirm', function (array $data) {
            $this->app->log->notice("Event PluginPaymentConfirm {$data['order_no']}");

        });
    }

    /**
     * 用户模块菜单配置
     * @return array[]
     */
    public static function menu(): array
    {
        // 设置插件菜单
        return [
            [
                'name' => '支付管理',
                'subs' => [
                    ['name' => '支付配置管理', 'icon' => 'layui-icon layui-icon-user', 'node' => "payment/config/index"],
                    ['name' => '支付行为管理', 'icon' => 'layui-icon layui-icon-edge', 'node' => "payment/record/index"],
                    ['name' => '支付退款管理', 'icon' => 'layui-icon layui-icon-firefox', 'node' => "payment/refund/index"],
                ],
            ]
        ];
    }
}