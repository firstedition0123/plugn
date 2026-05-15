<?php

namespace common\models;

use agent\models\Currency;
use agent\models\Plan;
use agent\models\Subscription;
use agent\models\SubscriptionPayment;
use common\components\TapPayments;
use Yii;
use yii\behaviors\AttributeBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * This is the model class for table "invoice_payment".
 *
 * @property string $payment_uuid
 * @property string|null $restaurant_uuid
 * @property string|null $invoice_uuid
 * @property string|null $payment_gateway_transaction_id
 * @property string|null $payment_mode
 * @property string|null $payment_current_status
 * @property float $payment_amount_charged
 * @property float|null $payment_net_amount
 * @property float|null $payment_gateway_fee
 * @property int $received_callback
 * @property boolean $is_sandbox
 * @property string|null $payment_created_at
 * @property string|null $payment_updated_at
 *
 * @property RestaurantInvoice $invoice
 * @property Restaurant $restaurant
 */
class InvoicePayment extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'invoice_payment';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['payment_amount_charged'], 'required'],
            [['payment_current_status', 'currency_code'], 'string'],
            [['is_sandbox'], 'boolean'],
            [['payment_amount_charged', 'payment_net_amount', 'payment_gateway_fee'], 'number'],
            [['received_callback'], 'integer'],
            [['payment_created_at', 'payment_updated_at'], 'safe'],
            [['payment_uuid', 'restaurant_uuid', 'invoice_uuid'], 'string', 'max' => 60],
            [['payment_gateway_transaction_id', 'payment_mode'], 'string', 'max' => 255],
            [['payment_uuid'], 'unique'],
            [['invoice_uuid'], 'exist', 'skipOnError' => true, 'targetClass' => RestaurantInvoice::className(), 'targetAttribute' => ['invoice_uuid' => 'invoice_uuid']],
            [['restaurant_uuid'], 'exist', 'skipOnError' => true, 'targetClass' => Restaurant::className(), 'targetAttribute' => ['restaurant_uuid' => 'restaurant_uuid']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            [
                'class' => AttributeBehavior::className(),
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => 'payment_uuid',
                ],
                'value' => function () {
                    if (!$this->payment_uuid)
                        $this->payment_uuid = Yii::$app->db->createCommand('SELECT uuid()')->queryScalar();

                    return $this->payment_uuid;
                }
            ],
            [
                'class' => TimestampBehavior::className(),
                'createdAtAttribute' => 'payment_created_at',
                'updatedAtAttribute' => 'payment_updated_at',
                'value' => new Expression('NOW()'),
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'payment_uuid' => Yii::t('app', 'Payment Uuid'),
            'restaurant_uuid' => Yii::t('app', 'Restaurant Uuid'),
            'invoice_uuid' => Yii::t('app', 'Invoice Uuid'),
            'payment_gateway_transaction_id' => Yii::t('app', 'Payment Gateway Transaction ID'),
            'payment_mode' => Yii::t('app', 'Payment Mode'),
            'payment_current_status' => Yii::t('app', 'Payment Current Status'),
            'payment_amount_charged' => Yii::t('app', 'Payment Amount Charged'),
            'payment_net_amount' => Yii::t('app', 'Payment Net Amount'),
            'payment_gateway_fee' => Yii::t('app', 'Payment Gateway Fee'),
            'currency_code' => Yii::t('app', 'Currency code'),
            'received_callback' => Yii::t('app', 'Received Callback'),
            'is_sandbox' => Yii::t('app', 'Is Sandbox'),
            'payment_created_at' => Yii::t('app', 'Payment Created At'),
            'payment_updated_at' => Yii::t('app', 'Payment Updated At'),
        ];
    }

    /**
     * @param $insert
     * @param $changedAttributes
     * @return void
     * @throws \yii\base\InvalidConfigException
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        if(isset($changedAttributes['payment_current_status']) &&
            $this->payment_current_status == 'CAPTURED') {

            $this->invoice->invoice_status = 1;
            $this->invoice->save();

            Yii::info("[" . $this->restaurant->name . ": Invoice for " .
                Yii::$app->formatter->asCurrency($this->payment_amount_charged, $this->currency_code, [
                    \NumberFormatter::MAX_SIGNIFICANT_DIGITS => $this->currency->decimal_place]) . '] has been paid', __METHOD__);
        }

        return true;
    }

    /**
     * @param $invoice_uuid
     * @param $paymentMethod
     * @return InvoicePayment|array
     */
    public static function initPayment($invoice_uuid, $payment_method_id) {

        //todo: support multi currency
        //$payment->currency_code = "KWD";
        //$payment->currency_value = 1;

        $store = Yii::$app->accountManager->getManagedAccount ();

        $invoice = RestaurantInvoice::findOne ($invoice_uuid);

        $payment_method = PaymentMethod::findOne($payment_method_id);

        $model = new self;
        $model->restaurant_uuid = $store->restaurant_uuid;
        $model->invoice_uuid = $invoice_uuid;
        $model->payment_mode = $payment_method->payment_method_code;
        $model->payment_amount_charged = $invoice->amount;
        $model->currency_code = $invoice->currency_code;
        $model->payment_current_status = "Initiated";
        $model->is_sandbox = 0;//todo: $store->is_sandbox;

        if (!$model->save ()) {
            return [
                'operation' => 'error',
                'message' => $model->getErrors ()
            ];
        }

        //mark invoice as locked to prevent new order commission getting add

        $model->invoice->invoice_status = RestaurantInvoice::STATUS_LOCKED;

        if(!$model->invoice->save()) {
            return [
                'operation' => 'error',
                'message' => $model->invoice->getErrors ()
            ];
        }

        return $model;
    }

    /**
     * update status from Tap
     * @param $charge_id
     * @param null $response_message
     * @param null $payment
     * @return InvoicePayment|mixed|null
     */
    public static function updateStatusFromTap($charge_id, $response_message = null, $payment = null) {

        if(!$payment)
            $payment = self::findOne(['payment_gateway_transaction_id' => $charge_id]);

        Yii::$app->tapPayments->setApiKeys(
            \Yii::$app->params['liveApiKey'],
            \Yii::$app->params['testApiKey'],
            $payment->is_sandbox
        );

        $response = Yii::$app->tapPayments->retrieveCharge($charge_id);

        $responseContent = json_decode($response->content);

        // If there's an error from TAP, exit and display error
        if (isset($responseContent->errors)) {
            $errorMessage = "[Error from TAP]" . $responseContent->errors[0]->code . " - " . $responseContent->errors[0]->description  . ' - Store Name: ' . $payment->restaurant->name . ' - Order Uuid: ' . $payment->order_uuid;

            \Yii::error($errorMessage, __METHOD__); // Log error faced by user

            \Yii::$app->getSession()->setFlash('error', $errorMessage);

            return $payment;
        }

        $payment->payment_current_status = $responseContent->status; // 'CAPTURED' ?

        // Update payment method used and the order id assigned to it
        if (isset($responseContent->source->payment_method) && $responseContent->source->payment_method)
            $payment->payment_mode = $responseContent->source->payment_method;

        //if (isset($responseContent->reference->payment) && $responseContent->reference->payment)
        //    $payment->payment_gateway_order_id = $responseContent->reference->payment;

        // KNET Gateway Fee Calculation
        if ($payment->payment_mode == \common\components\TapPayments::GATEWAY_KNET) {

            if (($payment->payment_amount_charged * Yii::$app->tapPayments->knetGatewayFee) > Yii::$app->tapPayments->minKnetGatewayFee)
                $payment->payment_gateway_fee = $payment->payment_amount_charged * Yii::$app->tapPayments->knetGatewayFee;
            else
                $payment->payment_gateway_fee = Yii::$app->tapPayments->minKnetGatewayFee;
        }

        // Creditcard Gateway Fee Calculation
        if ($payment->payment_mode == \common\components\TapPayments::GATEWAY_VISA_MASTERCARD) {

            if (($payment->payment_amount_charged * Yii::$app->tapPayments->creditcardGatewayFeePercentage) > Yii::$app->tapPayments->minCreditcardGatewayFee)
                $payment->payment_gateway_fee = $payment->payment_amount_charged * Yii::$app->tapPayments->creditcardGatewayFeePercentage;
            else
                $payment->payment_gateway_fee = Yii::$app->tapPayments->minCreditcardGatewayFee;
        }

        //$payment->response_message = $response_message;

        // Net amount after deducting gateway fee
        $payment->payment_net_amount = $payment->payment_amount_charged - $payment->payment_gateway_fee;

        $payment->received_callback = 1;

        if (!$payment->save()) {// && $payment->payment_current_status == 'CAPTURED'
            Yii::error(print_r($payment->errors, true), __METHOD__);
            return $payment;
        }

        //Send event to Segment

        if ($payment->payment_current_status == 'CAPTURED') {

            //$kwdCurrency = Currency::findOne(['code' => 'KWD']);

            //$rate = 1 / $kwdCurrency->rate;// to USD

            $rate = 1;

            Yii::$app->eventManager->track('Invoice Paid', [
                    //'addon_uuid' => $payment->invoice->addon_uuid,
                    'invoice' => $payment->invoice_uuid,
                    'paymentMethod' => $payment->payment_mode,
                    'charged' => $payment->payment_amount_charged,
                    'value' => ($payment->payment_amount_charged * $rate),
                    'revenue' => $payment->payment_net_amount,
                    'currency' => 'KWD'
                ],
                null,
                $payment->restaurant_uuid
            );
        }

        return $payment;
    }
    
    /**
     * Gets query for [[Currency]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCurrency($modelClass = "\common\models\Currency")
    {
        return $this->hasOne($modelClass::className(), ['code' => 'currency_code']);
    }

    /**
     * Gets query for [[Invoice]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInvoice($modelClass = "\common\models\RestaurantInvoice")
    {
        return $this->hasOne($modelClass::className(), ['invoice_uuid' => 'invoice_uuid']);
    }

    /**
     * Gets query for [[Restaurant]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRestaurant($modelClass = "\common\models\Restaurant")
    {
        return $this->hasOne($modelClass::className(), ['restaurant_uuid' => 'restaurant_uuid']);
    }
}
