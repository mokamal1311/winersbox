@extends('layouts.front')
@section('content')
@if($gs->guest_checkout == 0 && !Auth::guard('user')->check()) 
<script>
            window.location.href = "{{asset('user/login')}}";

</script>

@endif

           @include('includes.form-success')

        <div class="clearfix clear-fix"></div>
        <div class="card card-style  mt-3">
            <div class="content mb-2 mt-3">
                <div class="d-flex">
                    <div class="pr-4 w-60">
                        <p class="font-600 color-highlight mb-0 font-13">{{$lang->vt}}</p>
                        <h1>@if($gs->sign == 0)
                            {{$curr->sign}} <span class="total" id="grandtotal"> {{round($totalPrice * $curr->value, 2)}} </span>
                            @else
                            <span class="total" id="grandtotal"> {{round($totalPrice * $curr->value, 2)}} </span> {{$curr->sign}}
                            @endif</h1>
                    </div>
                    <div class="w-100 pt-1">
                        <h6 class="font-14 font-700">@if($lang->rtl == 0) Item   @else عنصر   @endif <span class="float-right color-green-dark tickets"> <?php $count =0; ?>
                             @foreach($products as $product)  
                                <?php $count += $product['qty'];  ?>

                            @endforeach
                                {{$count}}</span> </h6>
                        <div class="divider mb-2 mt-1"></div>
                        <h6 class="font-14 font-700"> {{$lang->new3}} <span class="float-right color-red-dark tickets">                                <?php $count =0; ?>
                             @foreach($products as $product)  
                                <?php $count += $product['qty'];  ?>

                            @endforeach
                                {{$count}}
</span></h6>

                            @php
                                $count2 =0;
                            @endphp
                             @foreach($products as $product) 
                                @php
                                    $count2 =  $count2 + (App\Http\Controllers\FrontendController::awradID($product['item']['id'])->free * $product['qty'] );
                                @endphp 
                                
                            @endforeach
                        @if($count2 > 0)
                        <div class="divider mb-2 mt-1"></div>
                        <h6 class="font-14 font-700"> {{$lang->others}} <span class="float-right color-red-dark ftickets">                                <?php $count =0; ?>
                                {{$count2}}
</span></h6>
                    @endif

                    </div>
                </div>
            </div>
        </div>
            <form action="{{route('cash.submit')}}" method="post" id="payment_form">
            {{csrf_field()}}    
            @if(Auth::guard('user')->check())        
            <div class="form-group float-label active none">
                <input type="hidden" class="form-control" name="name" required="" value="{{Auth::guard('user')->user()->name}}">
                <label class="form-control-label">{{$lang->fname}}</label>
            </div>
            <div class="form-group float-label active none">
                <input type="hidden" class="form-control card-type" name="phone" required="" value="{{Auth::guard('user')->user()->phone}}">
                <label class="form-control-label">{{$lang->doph}}</label>
            </div>
            <div class="form-group float-label active none">
                <input type="hidden" class="form-control" name="email" required="" value="{{Auth::guard('user')->user()->email}}">
                <label class="form-control-label">{{$lang->doeml}} </label>
            </div>
            <div class="form-group float-label active none">
                <input type="hidden" class="form-control card-type" name="address" required="" value="{{Auth::guard('user')->user()->address}}">
                <label class="form-control-label">{{$lang->doad}}</label>
            </div>
            <div class="form-group float-label active none">
                <input type="hidden" class="form-control card-type" name="city" required="" value="{{Auth::guard('user')->user()->city}}">
                <label class="form-control-label">{{$lang->doct}} </label>
            </div>

            <div class="form-group float-label active none">
                <input type="hidden" class="form-control card-type" name="customer_country" required="" value="{{Auth::guard('user')->user()->country}}">
                <label class="form-control-label">{{$lang->ctry}}</label>
            </div>
            <input type="hidden" name="user_id" value="{{Auth::guard('user')->user()->id}}">
            @else
                        <div class="form-group float-label active">
                <input type="text" class="form-control" name="name" required="" value="">
                <label class="form-control-label">{{$lang->fname}}</label>
            </div>
            <div class="form-group float-label active">
                <input type="text" class="form-control card-type" name="phone" required="" value="">
                <label class="form-control-label">{{$lang->doph}}</label>
            </div>
            <div class="form-group float-label active">
                <input type="email" class="form-control" name="email" required="" value="">
                <label class="form-control-label">{{$lang->doeml}} </label>
            </div>
            <div class="form-group float-label active">
                <input type="text" class="form-control card-type" name="address" required="" value="">
                <label class="form-control-label">{{$lang->doad}}</label>
            </div>
            <div class="form-group float-label active">
                <input type="text" class="form-control card-type" name="city" required="" value="">
                <label class="form-control-label">{{$lang->doct}} </label>
            </div>

                    <input type="hidden" name="user_id" value="0">
             @endif
                         <input type="hidden" id="shipping-cost" name="shipping_cost" value="{{round($shipping_cost * $curr->value,2)}}">
                        <input type="hidden" name="dp" value="{{$digital}}">
                        <input type="hidden" name="tax" value="{{$gs->tax}}">
                        <input type="hidden" name="totalQty" value="{{$totalQty}}">
                        <input type="hidden" name="total" id="grandtotal" value="{{round($totalPrice * $curr->value,2)}}">
                        <input type="hidden" name="coupon_code" id="coupon_code" value="">
                        <input type="hidden" name="coupon_discount" id="coupon_discount" value="">
                        <input type="hidden" name="coupon_id" id="coupon_id" value="">

                    <input  type="hidden" class="form-control card-type" name="order_notes" id="order_notes"  rows="4" value="">

        <div class="card card-style  mt-3">
            <div class="content mb-2 mt-3">
                <div class="d-flex">
                    <div class="w-100 pt-1">
                        <h6 class="font-14 font-700">  {{$lang->new33}} <span class="float-right color-green-dark "> <div class="toggle-switch">
  <input type="checkbox" id="chkTest" name="chkTest" value="1" checked>
  <label for="chkTest">
    <span class="toggle-track"></span>
  </label>
</div></span> </h6>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-style" id="cards">
            <div class="content">
                <p class="font-600 color-highlight mb-n1">{{$lang->blogs}}</p>
                <h3>{{$lang->suph}}</h3>
                <p>
                    {{$lang->suf}}.
                </p>
                <div class="fac fac-radio fac-highlight mb-2"><span></span>
                    <input id="newcard" value="0" type="radio" name="cardinput" class="cardinput" checked>
                    <label for="newcard">{{$lang->app}}</label>
                </div>
             @foreach($cards as $card)
                
    <label for="card{{$card->id}}"  class="d-flex mb-2">
          <input id="card{{$card->id}}" type="radio" name="cardinput" class="cardinput vcard" value="{{$card->id}}" data-number="{{$card->card_number}}" data-name="{{$card->card_name}}"  data-year="{{$card->year}}"  data-month="{{$card->month}}" >

<div class="align-self-center">

@php 
$first = substr($card->card_number, 0, 1);
@endphp
@if($first == 5)
<img src="{{asset('assets/front/images/framework/pay-2.png')}} " width="50">
@else
<img src="{{asset('assets/front/images/framework/pay-1.png')}} " width="50">
@endif

</div>
<div class="align-self-center pl-3">
<h5 class="mb-n2">@if($first == 5)
Mastercard
@else
Visa
@endif</h5>
<p class="mt-n1 mb-0 font-10">xxxx xxxx xxxx {{ substr($card->card_number, 12) }}</p>
</div>
<div class="align-self-center ml-auto pl-3">
<h5 class="mb-0">{{$card->month}} / {{ substr($card->year, 2) }}</h5>
</div>
</label>         
                @endforeach
                
                    <div class="d-flex">
                    <div class="w-100 pt-1" id="savecard">
                        <h6 class="font-14 font-700"> <input type="checkbox"  value="1" id="savecardinput" checked name="save_card" > {{$lang->bg}}  </h6>
                    </div>
            
            </div>
        </div>
</div>

        <div class="card card-style">
            <div class="content">
                <p class="font-600 color-highlight mb-n1">{{$lang->sue}}</p>
                <h3>{{$lang->bgs}}</h3>
                <p>
                      {{$lang->suf}}.
                </p>
                <div class="input-style input-style-2"  id="cardname">
                    <span class="input-style-1-active color-highlight">{{$lang->ccs}}</span>
                    <input  type="text" pattern="[a-z,A-Z\s]*$" class="form-control" id="cardnameinput" autocomplete="off"  name="card_name" required="required">
                </div>
                <div class="input-style input-style-2" id="cardnumber">
                    <span class="input-style-1-active color-highlight">{{$lang->pickups}}</span>
                     <input type='text' pattern="[0-9]{16,16}" maxlength='16' class="form-control card-type" autocomplete="off" name="card_number" required="required" inputmode="numeric">
                </div>
                <div class="row mb-0">
                    <div class="col-4" id="month"> 
                        <div class="input-style input-style-2 ">
                <span class="input-style-1-active">{{$lang->pickup}}</span>
                    <em><i class="fa fa-angle-down"></i></em>

                                <select name="month" id="monthinput"  class="form-control"  required="required" >
                                        <option value="" selected>Month</option>
                                        <option value="01">01</option>
                                        <option value="02">02</option>
                                        <option value="03">03</option>
                                        <option value="04">04</option>
                                        <option value="05">05</option>
                                        <option value="06">06</option>
                                        <option value="07">07</option>
                                        <option value="08">08</option>
                                        <option value="09">09</option>
                                        <option value="10">10</option>
                                        <option value="11">11</option>
                                        <option value="12">12</option>
                                    </select>
                        </div>
                    </div>
                    <div class="col-4"  id="year">
                        <div class="input-style input-style-2 ">
                                            <span class="input-style-1-active">{{$lang->ships}}</span>
                    <em><i class="fa fa-angle-down"></i></em>

                              <select id="yearinput" name="year"  class="form-control"  required="required" >
                                                <option value="" selected>Year</option>

                                  @php  $date = date("Y"); 
                                    for($i=$date;$i<=2035;$i++)
                                    {
                                               echo '<option value="'.$i.'">'.$i.'</option>';

                                    }
                                  
                                  @endphp      
                                    </select>
                        </div>
                    
                    </div>
                                    <input type="hidden" value="{{round($totalPrice * $curr->value,2)}}"  name="amount">

                    <div class="col-4">
                        <div class="input-style input-style-2">
                            <span class="input-style-1-active color-highlight">CVC </span>
                                <input type='text' pattern="[0-9]{3,3}" maxlength='3' class="form-control" autocomplete="off" required="required" name="cvv"  inputmode="numeric">
                        </div>
                    </div>
                </div>
            </div>

        </div>                
                                        <div id="checkout" class="text-center col-12 mx-auto">
                                <button type="submit" id="checkout" class="btn-block btn  btn-full gradient-blue font-13  font-600 mt-3 rounded-sm mb-4">{{$lang->new11}}</button>

                </div>
        
</form>



@endsection

@section('scripts')
<script type="deferjs">
function check() {
  document.getElementById("myCheck").checked = true;
}

function uncheck() {
  document.getElementById("myCheck").checked = false;
}
</script>


<script type="deferjs">
    $(document).on('click', '.deletecard', function () {
    var cardid = $(this).data('id');
    var result = confirm("Are you Sure to delete this Credit card ?");
        if (result) {
    $("label[for=card"+cardid+"]").remove();
       $.ajax({
                    type: "POST",
                    url:"{{URL::to('/json/deletecard')}}",
                    data:{"id": cardid , "_token": "{{ csrf_token() }}" },
                    success:function(data){}
              });
        }


});

$('input[type=radio][name=cardinput]').on('change', function() {


    if($(this).val()== 0)
    {
        $("#cardname input").val("");
        $("#cardnumber input").val("");
        $("#month select").val("");
        $("#year select").val("");
    
        $("#cardname").show();
        $("#cardnumber").show();
        $("#month").show();
        $("#year").show();
          $("#savecard").show();
       $('#savecardinput').prop('checked', true);

    }
    else
    {


        $("#cardname input").val($(this).data("name"));
        $("#cardnumber input").val($(this).data("number"));
        $('#month option[value='+$(this).data("month")+']').attr('selected','selected');
        $('#year option[value='+$(this).data("year")+']').attr('selected','selected');

        $("#cardname").hide();
        $("#cardnumber").hide();
        $("#month").hide();
        $("#year").hide();
        $("#savecard").hide();
$('#savecardinput').prop('checked', false);

        
    }
  
});


    $("#payment_form").submit(function () {
        $("#checkout").html("يتم التحميل .....");
        });

</script>

<script type="deferjs">
    @if(count($cards) > 0)
    $('#savecardinput').prop('checked', false);

        $(".vcard").first().click();

    @endif

    function meThods(val) {
        var action3 = "{{route('cash.submit')}}";
        if  (val.value == "Cash") {
            $("#payment_form").attr("action", action3);
            $("#stripes").hide();
            $("#gateway").hide();
        }
        else {
            $("#payment_form").attr("action", action6);
            var id = val.value;
            $(".gtext").hide();
            $("#gateway").show();
            $("#stripes").hide();
            $.ajax({
                type: "GET",
                url: "{{URL::to('/json/transhow')}}",
                data: {id: id},
                success: function (data) {
                    $("#gshow").html(data);
                    $("#gshow").show();
                }
            });

        }
    }

    $(document).on('click', '#coupon-click1', function () {
        $('.coupon-code form').slideToggle();
    });
    $(document).on('click', '#coupon-click2', function () {
        $('.coupon-code form').slideToggle();
    });
</script>

@if(isset($checked))
<script type="deferjs">
    $(window).on('load', function () {
        $('#checkoutModal').modal('show');
    });
    
</script>
<script>
    const cardTypeInput = document.querySelector('.card-type');
    cardTypeInput.addEventListener('input', () => {
        // Card type detection logic here
        // Update the class of the cardTypeInput element accordingly
    });
    
  document.getElementById("checkoutButton").addEventListener("click", function() {
  console.log("Button clicked");
});
</script>

@endif
@endsection