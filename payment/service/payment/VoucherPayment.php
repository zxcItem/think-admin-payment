<?php


declare (strict_types=1);

namespace app\payment\service\payment;

use app\account\service\contract\AccountInterface;
use app\payment\service\contract\PaymentInterface;
use app\payment\service\contract\PaymentResponse;
use app\payment\service\contract\PaymentUsageTrait;
use app\payment\service\Payment;
use think\admin\Exception;
use think\Response;

/**
 * 单据凭证支付方式
 * @class Voucher
 * @package app\payment\service\payment
 */
class VoucherPayment implements PaymentInterface
{
    use PaymentUsageTrait;

    /**
     * 初始化支付方式
     * @return PaymentInterface
     */
    public function init(): PaymentInterface
    {
        return $this;
    }

    /**
     * 订单数据查询
     * @param string $pcode
     * @return array
     */
    public function query(string $pcode): array
    {
        return [];
    }

    /**
     * 支付通知处理
     * @param array $data
     * @param ?array $notify
     * @return Response
     */
    public function notify(array $data = [], ?array $notify = null): Response
    {
        return response('SUCCESS');
    }

    /**
     * 发起支付退款
     * @param string $pcode 支付单号
     * @param string $amount 退款金额
     * @param string $reason 退款原因
     * @return array [状态, 消息]
     */
    public function refund(string $pcode, string $amount, string $reason = ''): array
    {
        try {
            $this->app->db->transaction(static function () use ($pcode, $amount, $reason) {
                static::syncRefund($pcode, $rcode, $amount, $reason);
            });
            return [1, '发起退款成功！'];
        } catch (\Exception $exception) {
            return [$exception->getCode(), $exception->getMessage()];
        }
    }

    /**
     * 创建支付订单
     * @param AccountInterface $account 支付账号
     * @param string $orderNo 交易订单单号
     * @param string $orderTitle 交易订单标题
     * @param string $orderAmount 订单支付金额（元）
     * @param string $payAmount 本次交易金额
     * @param string $payRemark 交易订单描述
     * @param string $payReturn 支付回跳地址
     * @param string $payImages 支付凭证图片
     * @return PaymentResponse
     * @throws Exception
     */
    public function create(AccountInterface $account, string $orderNo, string $orderTitle, string $orderAmount, string $payAmount, string $payRemark = '', string $payReturn = '', string $payImages = ''): PaymentResponse
    {
        // 订单及凭证检查
        if (empty($payImages)) throw new Exception('凭证不能为空！');
        $this->checkLeaveAmount($orderNo, $payAmount, $orderAmount);
        // 生成新的待审核记录
        [$payCode] = [Payment::withPaymentCode(), $this->withUserUnid($account)];
        $data = $this->createAction($orderNo, $orderTitle, $orderAmount, $payCode, $payAmount, $payImages);
        return $this->res->set(true, '上传成功！', $data);
    }
}