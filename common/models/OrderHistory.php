<?php

namespace common\models;

use Yii;
use yii\behaviors\AttributeBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * This is the model class for table "order_history".
 *
 * @property string $order_history_uuid
 * @property string $order_uuid
 * @property int $order_status
 * @property int|null $notify
 * @property string|null $comment
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Order $orderUu
 */
class OrderHistory extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'order_history';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            //'order_history_uuid',
            [['order_uuid', 'order_status'], 'required'],
            [['order_status', 'notify'], 'integer'],
            [['comment'], 'string'],
            [['created_at', 'updated_at'], 'safe'],
            [['order_history_uuid'], 'string', 'max' => 60],
            [['order_uuid'], 'string', 'max' => 40],
            [['order_history_uuid'], 'unique'],
            [['order_uuid'], 'exist', 'skipOnError' => true, 'targetClass' => Order::className(), 'targetAttribute' => ['order_uuid' => 'order_uuid']],
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
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => 'order_history_uuid',
                ],
                'value' => function () {
                    if (!$this->order_history_uuid) {
                        // Get a unique uuid from payment table
                        $this->order_history_uuid = Yii::$app->db->createCommand('SELECT uuid()')->queryScalar();
                    }

                    return $this->order_history_uuid;
                }
            ],
            [
                'class' => TimestampBehavior::className(),
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
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
            'order_history_uuid' => Yii::t('app', 'Order History Uuid'),
            'order_uuid' => Yii::t('app', 'Order Uuid'),
            'order_status' => Yii::t('app', 'Order Status'),
            'notify' => Yii::t('app', 'Notify'),
            'comment' => Yii::t('app', 'Comment'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
        ];
    }

    /**
     * Gets query for [[Order]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getOrder($model = 'common\models\Order')
    {
        return $this->hasOne($model::className(), ['order_uuid' => 'order_uuid']);
    }

    /**
     * @param $insert
     * @param $changedAttributes
     * @return void
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        
        if ($this->notify) {
            $this->notify();
        }
    }

    /**
     * @param $order_uuid
     * @param $status
     * @param $note
     * @return bool
     */
    public static function addOrderHistory($order_uuid, $status, $note) {
        $order = Order::find()
            ->andWhere(['order_uuid' => $order_uuid])
            ->one();

        if (!$order) {
            Yii::error('Order not found while adding history: ' . $order_uuid, __METHOD__);
            return false;
        }

        $order->scenario = "updateStatus";
        $order->order_status = $status;
        if (!$order->save()) {
            Yii::error(print_r($order->errors, true), __METHOD__);
            return false;
        }

        $model = new OrderHistory();
        $model->order_uuid = $order_uuid;
        $model->order_status = $status;
        $model->comment = $note;
        if (!$model->save()) {
            Yii::error(print_r($model->errors, true), __METHOD__);
            return false;
        }

        return true;
    }

    /**
     * todo: add mail template and test
     * @return void
     */
    public function notify() {
        
        $replyTo = [];
        if ($this->order->restaurant->restaurant_email) {
            $replyTo = [
                $this->order->restaurant->restaurant_email => $this->order->restaurant->name
            ];
        } else if ($this->order->restaurant->owner_email) {
            $replyTo = [
                $this->order->restaurant->owner_email => $this->order->restaurant->name
            ];
        }

        //mailer : override transport if store smtp settings available

        $host = Setting::getConfig($this->order->restaurant_uuid, 'mail', 'host');
        $username = Setting::getConfig($this->order->restaurant_uuid, 'mail', 'username');
        $password = Setting::getConfig($this->order->restaurant_uuid, 'mail', 'password');
        $port = Setting::getConfig($this->order->restaurant_uuid, 'mail', 'port');
        $encryption = Setting::getConfig($this->order->restaurant_uuid, 'mail', 'encryption');

        if($host && $username && $password && $port && $encryption)
        {
            $fromEmail = empty($this->order->restaurant->restaurant_email)?
                \Yii::$app->params['noReplyEmail']: $this->order->restaurant->restaurant_email;

            \Yii::$app->mailer->setTransport([
                'scheme' => 'smtp',
                'host' => $host,
                'username' => $username,
                'password' => $password,
                'port' => (int) $port,
                'encryption' => $encryption
            ]);
        }
        else
        {
            $fromEmail = \Yii::$app->params['noReplyEmail'];
        }

        $subject  = 'Status updated to '. $this->order->getOrderStatusInEnglish()
            .' for Order #' . $this->order_uuid . ' from ' . $this->order->restaurant->name;

        $ml = new MailLog();
        $ml->to = $this->order->customer_email;
        $ml->from = $fromEmail;
        $ml->subject = $subject;
        $ml->save();

        $mailer = \Yii::$app->mailer->compose([
            'html' => 'order-status-html',
        ], [
            "model" => $this
        ])
            ->setFrom([$fromEmail => $this->order->restaurant->name])
            //->setFrom($fromEmail)//[$fromEmail => $this->order->restaurant->name]
            ->setTo($this->order->customer_email)
            //->setReplyTo(\Yii::$app->params['supportEmail'])
            ->setSubject($subject);
        //->setReplyTo($replyTo)

        if(\Yii::$app->params['elasticMailIpPool'])
            $mailer->setHeader ("poolName", \Yii::$app->params['elasticMailIpPool']);

        try {
            $mailer->send();
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            // Handle email transport-specific exceptions
            Yii::error( "Failed to send email: " . $e->getMessage());
        } catch (\Exception $e) {
            // Handle any other exceptions
            Yii::error( "An error occurred: " . $e->getMessage());
        }
    }
}
