<?php

declare (strict_types=1);

namespace app\payment\controller;

use app\account\model\AccountUser;
use app\payment\model\PaymentRecord;
use app\payment\service\Payment;
use think\admin\Controller;
use think\admin\extend\CodeExtend;
use think\admin\helper\QueryHelper;
use think\admin\service\AdminService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\db\Query;
use think\exception\HttpResponseException;

/**
 * 支付行为管理
 * @class Record
 * @package app\payment\controller
 */
class Record extends Controller
{
    /**
     * 支付行为管理
     * @auth true
     * @menu true
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        $this->mode = $this->get['open_type'] ?? 'index';
        PaymentRecord::mQuery()->layTable(function () {
            if ($this->mode === 'index') $this->title = '支付行为管理';
        }, static function (QueryHelper $query) {
            $db = AccountUser::mQuery()->like('email|nickname|username|phone#userinfo')->db();
            if ($db->getOptions('where')) $query->whereRaw("unid in {$db->field('id')->buildSql()}");
            $query->with(['user'])->like('order_no|order_name#orderinfo')->dateBetween('create_time');
        });
    }

    /**
     * 单据凭证审核
     * @auth true
     * @return void
     */
    public function audit()
    {
        if ($this->request->isGet()) {
            PaymentRecord::mForm('audit');
        } else {
            $data = $this->_vali([
                'id.require'      => '支付号不能为空！',
                'status.in:0,1,2' => '审核状态数值异常！',
                'status.require'  => '审核状态不能为空！',
                'remark.default'  => '',
            ]);
            if (intval($data['status']) === 1) {
                $this->error('请选择通过或驳回！');
            }
            $action = PaymentRecord::mk()->findOrEmpty($data['id']);
            if ($action->isEmpty()) $this->error('支付记录不存在！');
            if ($action->getAttr('channel_type') !== Payment::VOUCHER) {
                $this->error('无需审核操作！');
            }
            if ($action->getAttr('payemnt_status') === 1) {
                $this->success('该凭证已审核！');
            }
            $data['audit_user'] = AdminService::getUserId();
            $data['audit_time'] = date('Y-m-d H:i:s');
            $data['audit_remark'] = $data['remark'];
            $data['payment_time'] = date('Y-m-d H:i:s');
            $data['payment_trade'] = CodeExtend::uniqidNumber(18, 'AUD');
            if (empty($data['status'])) {
                $data['audit_status'] = 0;
                $data['payment_status'] = 0;
                $data['payment_remark'] = $data['remark'] ?: '后台支付凭证被驳回';
            } else {
                $data['audit_status'] = 2;
                $data['payment_status'] = 1;
                $data['payment_remark'] = $data['remark'] ?: '后台支付凭证已通过';
            }
            if ($action->save($data)) {
                if (empty($data['status'])) {
                    $this->app->event->trigger('PluginPaymentRefuse', $action->refresh());
                    $this->success('凭证审核驳回！');
                } else {
                    $this->app->event->trigger('PluginPaymentSuccess', $action->refresh());
                    $this->success('凭证审核通过！');
                }
            } else {
                $this->error('凭证审核失败！');
            }
        }
    }

    /**
     * 取消支付订单
     * @auth true
     * @return void
     */
    public function cancel()
    {
        try {
            $data = $this->_vali(['code.require' => '支付单号不能为空！']);
            $items = PaymentRecord::mq()->where(function (Query $query) {
                $query->whereOr([['payment_status', '=', 1], ['audit_status', '>', '0']]);
            })->where($data)->column('code,channel_code,payment_amount');
            foreach ($items as $item) Payment::mk($item['channel_code'])->refund($item['code'], $item['payment_amount']);
            $this->success('退款申请成功！');
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * 重新触发支付行为
     * @auth true
     * @return void
     */
    public function notify()
    {
        $data = $this->_vali(['code.require' => '支付单号不能为空！']);
        $record = PaymentRecord::mk()->where(['code' => $data['code']])->findOrEmpty();
        if ($record->isEmpty()) $this->error('支付单号异常！');
        if (empty($record->getAttr('payment_status'))) $this->error('未完成支付！');
        $this->app->event->trigger('PluginPaymentSuccess', $record);
        $this->success('重新触发支付行为！');
    }
}
