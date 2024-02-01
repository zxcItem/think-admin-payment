<?php

declare (strict_types=1);

namespace app\payment\service;

use app\account\service\Account;
use app\account\service\contract\AccountInterface;
use app\payment\model\PaymentConfig;
use app\payment\model\PaymentRecord;
use app\payment\model\PaymentRefund;
use app\payment\service\contract\PaymentInterface;
use app\payment\service\contract\PaymentResponse;
use app\payment\service\payment\AliPayment;
use app\payment\service\payment\BalancePayment;
use app\payment\service\payment\EmptyPayment;
use app\payment\service\payment\IntegralPayment;
use app\payment\service\payment\VoucherPayment;
use app\payment\service\payment\WechatPayment;
use think\admin\Exception;
use think\admin\extend\CodeExtend;
use think\db\Query;
use think\db\Raw;

/**
 * 支付配置调度器
 * @class Payment
 * @package app\payment\service
 */
abstract class Payment
{

    // 用户余额支付
    const EMPTY = 'empty';
    const BALANCE = 'balance';
    const VOUCHER = 'voucher';
    const INTEGRAL = 'integral';

    // 微信商户支付
    const WECHAT_APP = 'wechat_app';
    const WECHAT_GZH = 'wechat_gzh';
    const WECHAT_XCX = 'wechat_xcx';
    const WECHAT_WAP = 'wechat_wap';
    const WECHAT_QRC = 'wechat_qrc';

    // 支付宝支付参数
    const ALIAPY_APP = 'alipay_app';
    const ALIPAY_WAP = 'alipay_wap';
    const ALIPAY_WEB = 'alipay_web';

    // 已禁用的支付方式
    private static $denys = null;
    private static $cakey = 'payment.denys';

    // 支付方式配置
    private static $types = [
        // 空支付，金额为零时自动完成支付
        self::EMPTY      => [
            'name'    => '订单无需支付',
            'class'   => EmptyPayment::class,
            'status'  => 1,
            'account' => [],
        ],
        // 余额支付，使用账户余额支付
        self::BALANCE    => [
            'name'    => '账户余额支付',
            'class'   => BalancePayment::class,
            'status'  => 1,
            'account' => [
                Account::WAP,
                Account::WEB,
                Account::WXAPP,
                Account::WECHAT,
                Account::IOSAPP,
                Account::ANDROID,
            ],
        ],
        // 积分抵扣，使用账户积分抵扣
        self::INTEGRAL   => [
            'name'    => '账户积分抵扣',
            'class'   => IntegralPayment::class,
            'status'  => 1,
            'account' => [
                Account::WAP,
                Account::WEB,
                Account::WXAPP,
                Account::WECHAT,
                Account::IOSAPP,
                Account::ANDROID,
            ],
        ],
        // 凭证支付，上传凭证后台审核支付
        self::VOUCHER    => [
            'name'    => '单据凭证支付',
            'class'   => VoucherPayment::class,
            'status'  => 1,
            'account' => [
                Account::WAP,
                Account::WEB,
                Account::WXAPP,
                Account::WECHAT,
                Account::IOSAPP,
                Account::ANDROID,
            ],
        ],
        // 微信支付配置（不需要的直接注释）
        self::WECHAT_WAP => [
            'name'    => '微信WAP支付',
            'class'   => WechatPayment::class,
            'status'  => 1,
            'account' => [Account::WAP],
        ],
        self::WECHAT_APP => [
            'name'    => '微信APP支付',
            'class'   => WechatPayment::class,
            'status'  => 1,
            'account' => [Account::IOSAPP, Account::ANDROID],
        ],
        self::WECHAT_XCX => [
            'name'    => '微信小程序支付',
            'class'   => WechatPayment::class,
            'status'  => 1,
            'account' => [Account::WXAPP],
        ],
        self::WECHAT_GZH => [
            'name'    => '微信公众号支付',
            'class'   => WechatPayment::class,
            'status'  => 1,
            'account' => [Account::WECHAT],
        ],
        self::WECHAT_QRC => [
            'name'    => '微信二维码支付',
            'class'   => WechatPayment::class,
            'status'  => 1,
            'account' => [Account::WEB],
        ],
        // 支付宝支持配置（不需要的直接注释）
        self::ALIPAY_WAP => [
            'name'    => '支付宝WAP支付',
            'class'   => AliPayment::class,
            'status'  => 1,
            'account' => [Account::WAP],
        ],
        self::ALIPAY_WEB => [
            'name'    => '支付宝WEB支付',
            'class'   => AliPayment::class,
            'status'  => 1,
            'account' => [Account::WEB],
        ],
        self::ALIAPY_APP => [
            'name'    => '支付宝APP支付',
            'class'   => AliPayment::class,
            'status'  => 1,
            'account' => [Account::ANDROID, Account::IOSAPP],
        ],
    ];

    /**
     * 实例化支付配置
     * @param string $code 编号或类型
     * @return PaymentInterface
     * @throws Exception
     */
    public static function mk(string $code): PaymentInterface
    {
        if (in_array($code, [self::EMPTY, self::BALANCE, self::INTEGRAL])) {
            if (empty(self::$types[$code]['status'])) {
                throw new Exception(self::typeName($code) . '已被禁用！');
            } else {
                return self::$types[$code]['class']::mk($code, $code, []);
            }
        } else {
            [$type, $attr, $params] = self::params($code);
            if (self::typeStatus($type)) {
                return $attr['class']::mk($code, $type, $params);
            } else {
                throw new Exception(self::typeName($type) . '已被禁用！');
            }
        }
    }

    /**
     * 初始化数据状态
     * @return array[]
     */
    private static function init(): array
    {
        if (is_null(self::$denys)) try {
            self::$denys = sysdata(self::$cakey);
            foreach (self::$types as $type => &$item) {
                $item['status'] = intval(!in_array($type, self::$denys));
            }
        } catch (\Exception $exception) {
        }
        return self::$types;
    }

    /**
     * 获取支付参数
     * @param string $code 支付配置编号
     * @param array $config 支付配置参数
     * @return array [type, attr, params]
     * @throws Exception
     */
    public static function params(string $code, array $config = []): array
    {
        try {
            if (empty($config)) {
                $map = ['code' => $code, 'status' => 1, 'deleted' => 0];
                $config = PaymentConfig::mk()->where($map)->findOrEmpty()->toArray();
            }
            if (empty($config)) {
                throw new Exception("支付配置[#{$code}]参数异常！");
            }
            $params = is_string($config['content']) ? @json_decode($config['content'], true) : $config['content'];
            if (empty($params)) throw new Exception("支付配置[#{$code}]参数无效！");

            if (empty(self::$types[$config['type']]['status'])) {
                throw new Exception("支付配置[@{$config['type']}]未启用！");
            }
            return [$config['type'], self::$types[$config['type']], $params];
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * 添加支付方式
     * @param string $type 支付编码
     * @param string $name 支付名称
     * @param string $class 处理机制
     * @param array $account 绑定终端
     * @return array[]
     */
    public static function add(string $type, string $name, string $class, array $account = []): array
    {
        if (class_exists($class) && in_array(PaymentInterface::class, class_implements($class))) {
            self::$types[$type] = ['name' => $name, 'class' => $class, 'status' => 1, 'account' => $account];
        }
        return self::types();
    }

    /**
     * 设置方式状态
     * @param string $type 支付编码
     * @param integer $status 支付状态
     * @return bool
     */
    public static function set(string $type, int $status): bool
    {
        if (isset(self::$types[$type])) {
            self::$types[$type]['status'] = $status;
            return true;
        } else {
            return false;
        }
    }

    /**
     * 保存支付方式
     * @return true|integer
     * @throws Exception
     */
    public static function save()
    {
        self::$denys = [];
        foreach (self::types() as $k => $v) {
            if (empty($v['status'])) self::$denys[] = $k;
        }
        return sysdata(self::$cakey, self::$denys);
    }

    /**
     * 获取支付方式
     * @param ?integer $status
     * @return array
     */
    public static function types(?int $status = null): array
    {
        try {
            [$all, $binds] = [[], array_keys(Account::types(1))];
            foreach (self::init() as $type => $item) {
                if (is_null($status) || $status == $item['status']) {
                    if (array_intersect($item['account'], $binds)) $all[$type] = $item;
                }
            }
            return $all;
        } catch (\Exception $exception) {
            return [];
        }
    }

    /**
     * 通过接口类型筛选支付方式
     * @param string $account 指定终端
     * @param boolean $getfull 读取参数
     * @return array
     */
    public static function typesByAccess(string $account, bool $getfull = false): array
    {
        $types = [];
        foreach (self::types(1) as $type => $attr) {
            if (in_array($account, $attr['account'])) {
                $types[$type] = $attr['name'];
            }
        }
        if ($getfull) {
            $items = [];
            $query = PaymentConfig::mk()->field('type,code,name,cover,content');
            $query->where(['status' => 1, 'deleted' => 0])->whereIn('type', array_keys($types));
            foreach ($query->order('sort desc,id desc')->cursor() as $item) {
                $item['qrcode'] = $item['content']['voucher_qrcode'] ?? '';
                unset($item['content']);
                $items[] = $item->toArray();
            }
            return $items;
        } else {
            return $types;
        }
    }

    /**
     * 读取支付配置
     * @return array
     */
    public static function items(): array
    {
        $map = ['status' => 1, 'deleted' => 0];
        return PaymentConfig::mk()->where($map)->order('sort desc,id desc')->column('type,code,name', 'code');
    }

    /**
     * 获取支付类型名称
     * @param string $type
     * @return string
     */
    public static function typeName(string $type): string
    {
        return self::$types[$type]['name'] ?? $type;
    }

    /**
     * 判断支付类型状态
     * @param string $type
     * @return bool
     */
    public static function typeStatus(string $type): bool
    {
        return !empty(self::$types[$type]['status']);
    }

    /**
     * 判断是否完成支付
     * @param string $orderNo 原订单号
     * @param string $amount 需支付金额
     * @return boolean
     */
    public static function isPayed(string $orderNo, string $amount): bool
    {
        return self::paidAmount($orderNo) >= floatval($amount);
    }

    /**
     * 发起订单整体退款
     * @param string $orderNo
     * @return void
     * @throws Exception
     */
    public static function refund(string $orderNo)
    {
        $items = PaymentRecord::mq()->where(function (Query $query) {
            $query->whereOr([['payment_status', '=', 1], ['audit_status', '>', '0']]);
        })->where(['order_no' => $orderNo])->column('code,channel_code,payment_amount');
        foreach ($items as $item) static::mk($item['channel_code'])->refund($item['code'], $item['payment_amount']);
    }

    /**
     * 获取已支付金额
     * @param string $orderNo 订单单号
     * @param boolean $realtime 有效金额
     * @return float
     */
    public static function paidAmount(string $orderNo, bool $realtime = false): float
    {
        $map = ['order_no' => $orderNo, 'payment_status' => 1];
        $raw = new Raw($realtime ? 'payment_amount - refund_amount' : 'payment_amount');
        return PaymentRecord::mk()->where($map)->sum($raw);
    }

    /**
     * 订单剩余支付金额
     * @param string $orderNo
     * @param mixed $orderAmount
     * @return float
     */
    public static function leaveAmount(string $orderNo, $orderAmount): float
    {
        $diff = floatval($orderAmount) - self::paidAmount($orderNo);
        return $diff > 0 ? $diff : 0.00;
    }

    /**
     * 生成支付单号
     * @return string
     */
    public static function withPaymentCode(): string
    {
        do $data = ['code' => CodeExtend::uniqidNumber(16, 'P')];
        while (PaymentRecord::mk()->master()->where($data)->findOrEmpty()->isExists());
        return $data['code'];
    }

    /**
     * 生成退款单号
     * @return string
     */
    public static function withRefundCode(): string
    {
        do $data = ['code' => CodeExtend::uniqidNumber(16, 'R')];
        while (PaymentRefund::mk()->master()->where($data)->findOrEmpty()->isExists());
        return $data['code'];
    }

    /**
     * 创建订单空支付
     * @param AccountInterface $account
     * @param string $orderNo 订单单号
     * @param string $title 订单标题
     * @param string $remark 订单描述
     * @return PaymentResponse
     * @throws Exception
     */
    public static function emptyPayment(AccountInterface $account, string $orderNo, string $title = '商城订单支付', string $remark = '订单金额为0，无需要支付'): PaymentResponse
    {
        return self::mk(self::EMPTY)->create($account, $orderNo, $title, '0.00', '0.00', $remark);
    }
}