<?php

use yii\helpers\Url;
use yii\helpers\Html;
use yii\grid\GridView;
use common\models\BusinessLocation;

/* @var $this yii\web\View */
/* @var $searchModel frontend\models\BusinessLocationSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Business Locations';
$this->params['restaurant_uuid'] = $store->restaurant_uuid;


$js = "
$(function () {
  $('.summary').insertAfter('.top');
});
";
$this->registerJs($js);

?>

<!-- Data list view starts -->
  <section id="data-list-view" class="data-list-view-header">

    <?= Html::a($businessLocations ? 'Add another business location' : 'Add Business location', ['create', 'storeUuid' => $store->restaurant_uuid], ['class' => 'btn btn-primary']); ?>

      <?php

        foreach ($businessLocations as $key => $businessLocation) {

          $numberOfCoutnriesStoreDeliveringTo = 0;



          $deliveryZones = $store->getDeliveryZones()->with('country')->asArray()->all();

          $shipping_countries = [];

          foreach ($deliveryZones as $key => $deliveryZone) {
            if(!array_search($deliveryZone['country']['country_id'], array_column($shipping_countries, 'country_id')))

            $isExist = false;

            foreach ($shipping_countries as  $shipping_country) {
              if($deliveryZone['country']['country_id'] == $shipping_country['country_id'])
                $isExist = true;
            }


            if(!$isExist)
              $shipping_countries[] = $deliveryZone['country'];
          }



           $numberOfCoutnriesStoreDeliveringTo = sizeof($shipping_countries);



      ?>




      <div class="card mt-2">

        <div class="card-header">

          <div>
            <h5><?= Html::encode($businessLocation->country->country_name) ?></h5>
            <h3>

            <?=
                Html::a(Html::encode($businessLocation->business_location_name)  . ' <i class="feather icon-edit"></i>',
                ['update',  'id' => $businessLocation->business_location_id, 'storeUuid' => $store->restaurant_uuid],
                ['class' => '', 'style' => 'margin-bottom : 15px;     margin-right: 20px;']);
            ?>
          </h3>
        </div>




        </div>
        <div class="card-content">

          <div class="card-body">
          <div class="row">

            <div class="col-12 col-sm-12 col-lg-4" style="margin-bottom: 20px;">
              <h5> Delivery </h5>

              <?php   if($businessLocation->getDeliveryZones()->count()  == 0 ){ ?>


                <p>You do not currently deliver from this location</p>
                <?=  Html::a('Set up Delivery Zones', ['delivery-zone/create', 'storeUuid' => $store->restaurant_uuid, 'businessLocationId' => $businessLocation->business_location_id], ['class' => 'btn btn-outline-primary']); ?>


              <?php  } else {

                  $numberOfCoutnriesDeliveringToText = '';

                  $numberOfCoutnriesDeliveringToText =
                  $numberOfCoutnriesStoreDeliveringTo  == 1 ?
                   'Delivering to '. $numberOfCoutnriesStoreDeliveringTo .' country' : 'Delivering to '. $numberOfCoutnriesStoreDeliveringTo .' countries';

                  ?>

                    <p> <?= $numberOfCoutnriesDeliveringToText ?> </p>
                  <?=  Html::a('Make changes', ['delivery-zone/index', 'storeUuid' => $store->restaurant_uuid, 'businessLocationId' => $businessLocation->business_location_id], ['class' => 'btn btn-outline-primary']) ?>

              <?php  }      ?>

            </div>
            <div class="col-12 col-sm-12 col-lg-4" style="margin-bottom: 20px;">
              <h5> Pickup </h5>

              <p>
                <?= !$businessLocation->support_pick_up ? 'You do not currently allow pickup from this location' : 'Your customer can pick up from this location' ?>
              </p>

              <?=
                Html::a($businessLocation->support_pick_up ? 'Disable pickup' : 'Enable pickup',
                [$businessLocation->support_pick_up ? 'disable-pickup' : 'enable-pickup',  'id' => $businessLocation->business_location_id, 'storeUuid' => $store->restaurant_uuid],
                ['class' => $businessLocation->support_pick_up ?  'btn btn-outline-danger' : 'btn btn-outline-primary'])
              ?>


            </div>
            <div class="col-12 col-sm-12 col-lg-4" style="margin-bottom: 20px;">
              <h5> VAT </h5>

              <p>
                <?= $businessLocation->business_location_tax ? $businessLocation->business_location_tax . '% charged on each order' : 'You do not charge VAT for this location'  ?>
              </p>
              <?=
                Html::a($businessLocation->business_location_tax ? 'Remove VAT' : 'Set up VAT',
                [$businessLocation->business_location_tax ? 'remove-tax' : 'configure-tax',  'id' => $businessLocation->business_location_id, 'storeUuid' => $store->restaurant_uuid],
                ['class' => $businessLocation->business_location_tax ?  'btn btn-outline-danger' : 'btn btn-outline-primary'])
              ?>


            </div>





                </div>



          </div>

        </div>
      </div>

    <?php } ?>


  </section>
  <!-- Data list view end -->
