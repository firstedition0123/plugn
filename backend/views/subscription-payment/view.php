<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\SubscriptionPayment */

$this->title = $model->payment_uuid;
$this->params['breadcrumbs'][] = ['label' => 'Subscription Payments', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="subscription-payment-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Update', ['update', 'id' => $model->payment_uuid], ['class' => 'btn btn-primary btn-update']) ?>
        <?= Html::a('Delete', ['delete', 'id' => $model->payment_uuid], [
            'class' => 'btn btn-danger btn-delete',
            'data' => [
                'confirm' => 'Are you sure you want to delete this item?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'payment_uuid',
            'restaurant_uuid',
            [
                'label' => 'Store Name',
                'format' => 'text',
                'value' => function ($data) {
                    return $data->restaurant->name ;
                }
            ],
            'subscription_uuid',
            'payment_gateway_order_id',
            'payment_gateway_transaction_id',
            'payment_mode',
            'payment_current_status:ntext',
            [
                'attribute' => 'payment_amount_charged',
                'format' => 'raw',
                'value' => function ($data) {
                    if ($data->currency) {
                        return Yii::$app->formatter->asCurrency($data->payment_amount_charged, $data->currency_code, [
                            \NumberFormatter::MIN_FRACTION_DIGITS => $data->currency->decimal_place,
                            \NumberFormatter::MAX_FRACTION_DIGITS => $data->currency->decimal_place
                        ]);
                    }  else {
                        return $data->payment_amount_charged;
                    }
                }
            ],
            [
                'attribute' => 'payment_net_amount',
                'format' => 'raw',
                'value' => function ($data) {
                    if ($data->currency) {
                        return Yii::$app->formatter->asCurrency($data->payment_net_amount, $data->currency_code, [
                            \NumberFormatter::MIN_FRACTION_DIGITS => $data->currency->decimal_place,
                            \NumberFormatter::MAX_FRACTION_DIGITS => $data->currency->decimal_place
                        ]);
                    } else {
                        return $data->payment_net_amount;
                    }
                }
            ],
            [
                'attribute' => 'payment_gateway_fee',
                'format' => 'raw',
                'value' => function ($data) {
                    if ($data->currency) {
                        return Yii::$app->formatter->asCurrency($data->payment_gateway_fee, $data->currency_code, [
                            \NumberFormatter::MIN_FRACTION_DIGITS => $data->currency->decimal_place,
                            \NumberFormatter::MAX_FRACTION_DIGITS => $data->currency->decimal_place
                        ]);
                    } else {
                        return $data->payment_gateway_fee;
                    }
                }
            ],

            'payment_created_at',
            'payment_updated_at',
            'received_callback',
            'response_message'
        ],
    ]) ?>

</div>
