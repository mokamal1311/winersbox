<?php

namespace App\Http\Controllers;

use App\AdminUserConversation;
use App\AdminUserMessage;
use App\Category;
use App\Classes\GeniusMailer;
use App\Conversation;
use App\Currency;
use App\FavoriteSeller;
use App\Generalsetting;
use App\Http\Requests\StoreValidationRequest;
use App\Http\Requests\UpdateValidationRequest;
use App\Language;
use App\Message;
use App\Notification;
use App\Order;
use App\Product;
use App\Subscription;
use App\User;
use App\Cards;
use App\UserNotification;
use App\UserSubscription;
use App\VendorOrder;
use App\Wishlist;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use \Curl\Curl;
use \stdClass;

class UserController extends Controller {

    public function __construct() {
        $this->middleware('auth:user');
    }

    public function index() {
        
        
        
        $user          = Auth::guard('user')->user();
        $complete      = $user->orders()->where('status', '=', 'completed')->get()->count();
        $process       = $user->orders()->where('status', '=', 'processing')->get()->count();
        $wishes        = $user->wishlists;
        $currency_sign = Currency::where('is_default', '=', 1)->first();
        $convs = Conversation::where('sent_user', '=', $user->id)->orWhere('recieved_user', '=', $user->id)->get();
        $orders = Order::where('user_id', '=', $user->id)->orderBy('id', 'desc')->get();

        $tickets = \App\Tickets::where("user_id", $user->id)->orderBy('id', 'DESC')->get();        
        $ftickets = \App\Ftickets::where("user_id", $user->id)->orderBy('id', 'DESC')->get();        
        
        return view('user.dashboard', compact('user', 'complete', 'process', 'wishes','orders', 'currency_sign', 'convs'))->with('tickets', $tickets)->with('ftickets', $ftickets);
    }

    public function profile() {
        $user = Auth::guard('user')->user();
        return view('user.profile', compact('user'));
    }

    public function orders() {
        $user   = Auth::guard('user')->user();
        $orders = Order::where('user_id', '=', $user->id)->orderBy('id', 'desc')->get();
        return view('user.orders', compact('user', 'orders'));
    }

    public function messages() {
        $user = Auth::guard('user')->user();

        $convs = Conversation::where('sent_user', '=', $user->id)->orWhere('recieved_user', '=', $user->id)->get();


        $tickets = \App\Tickets::where("user_id", $user->id)->orderBy('id', 'DESC')->get();



        return view('user.messages', compact('user', 'convs'))->with('tickets', $tickets);
    }
        public function cards() {
        $user = Auth::guard('user')->user();

         $checkcard = Cards::where("user_id", Auth::guard('user')->user()->id)->orderBy('id', 'DESC')->get();


        return view('user.cards')->with('cards', $checkcard);
    }


    public function message($id) {
        $user = Auth::guard('user')->user();
        $conv = Conversation::findOrfail($id);
        return view('user.message', compact('user', 'conv'));
    }

    public function messagedelete($id) {
        $conv = Conversation::findOrfail($id);
        if ($conv->messages->count() > 0) {
            foreach ($conv->messages as $key) {
                $key->delete();
            }
        }
        if ($conv->notifications->count() > 0) {
            foreach ($conv->notifications as $key) {
                $key->delete();
            }
        }
        $conv->delete();
        return redirect()->back()->with('success', 'Message Deleted Successfully');
    }

    public function postmessage(Request $request) {
        $msg                           = new Message();
        $input                         = $request->all();
        $msg->fill($input)->save();
        $notification                  = new UserNotification;
        $notification->user_id         = $request->reciever;
        $notification->conversation_id = $request->conversation_id;
        $notification->save();
        Session::flash('success', 'Message Sent!');
        return redirect()->back();
    }

    public function emailsub(Request $request) {
        $user = Auth::guard('user')->user();
        $gs   = Generalsetting::findOrFail(1);
        if ($gs->is_smtp == 1) {
            $data = [
                'to'      => $request->to,
                'subject' => $request->subject,
                'body'    => $request->message,
            ];

            $mailer = new GeniusMailer();
            $mailer->sendCustomMail($data);
        } else {
            $data    = 0;
            $headers = "From: " . $gs->from_name . "<" . $gs->from_email . ">";
            $mail    = mail($request->to, $request->subject, $request->message, $headers);
            if ($mail) {
                $data = 1;
            }
        }

        return response()->json($data);
    }

    public function order($id) {
        $user  = Auth::guard('user')->user();
        $order = Order::findOrfail($id);

        $cart = unserialize(utf8_decode($order->cart));



        return view('user.order', compact('user', 'order', 'cart'));
    }

    public function orderdownload($slug, $id) {
        $user  = Auth::guard('user')->user();
        $order = Order::where('order_number', '=', $slug)->first();
        $prod  = Product::findOrFail($id);
        if (!isset($order) || $prod->type == 0 || $order->user_id != $user->id) {
            return redirect()->back();
        }
        return response()->download(public_path('assets/files/' . $prod->file));
    }

    public function orderprint($id) {
        $user  = Auth::guard('user')->user();
        $order = Order::findOrfail($id);
        $cart  = unserialize((utf8_decode($order->cart)));
        return view('user.print', compact('user', 'order', 'cart'));
    }

    public function vendororders() {
        $user   = Auth::guard('user')->user();
        $orders = VendorOrder::where('user_id', '=', $user->id)->orderBy('id', 'desc')->get()->groupBy('order_number');

        return view('user.order.index', compact('user', 'orders'));
    }

    public function vendorlicense(Request $request, $slug) {
        $order                                         = Order::where('order_number', '=', $slug)->first();
        $cart                                          = unserialize((utf8_decode($order->cart)));
        $cart->items[$request->license_key]['license'] = $request->license;
        $order->cart                                   = utf8_encode(bzcompress(serialize($cart), 9));
        $order->update();
        return redirect()->route('vendor-order-show', $order->order_number)->with('success', 'Successfully Changed The License Key.');
    }

    public function vendororder($slug) {
        $user  = Auth::guard('user')->user();
        $order = Order::where('order_number', '=', $slug)->first();
        $cart  = unserialize((utf8_decode($order->cart)));
        return view('user.order.details', compact('user', 'order', 'cart'));
    }

    public function invoice($slug) {
        $user  = Auth::guard('user')->user();
        $order = Order::where('order_number', '=', $slug)->first();
        $cart  = unserialize((utf8_decode($order->cart)));
        return view('user.order.invoice', compact('user', 'order', 'cart'));
    }

    public function printpage($slug) {
        $user  = Auth::guard('user')->user();
        $order = Order::where('order_number', '=', $slug)->first();
        $cart  = unserialize((utf8_decode($order->cart)));
        return view('user.order.print', compact('user', 'order', 'cart'));
    }

    public function status($slug, $status) {
        $mainorder = VendorOrder::where('order_number', '=', $slug)->first();
        if ($mainorder->status == "completed") {
            return redirect()->back()->with('success', 'This Order is Already Completed');
        } else {

            $user  = Auth::guard('user')->user();
            $order = VendorOrder::where('order_number', '=', $slug)->where('user_id', '=', $user->id)->update(['status' => $status]);
            return redirect()->route('vendor-order-index')->with('success', 'Order Status Updated Successfully');
        }
    }

    public function resetform() {
        $user = Auth::guard('user')->user();
        return view('user.reset', compact('user'));
    }

    public function shop() {
        $user = Auth::guard('user')->user();
        return view('user.shop-description', compact('user'));
    }

    public function shopup(Request $request) {
        $input = $request->all();
        $user  = Auth::guard('user')->user();
        $user->update($input);
        Session::flash('success', 'Successfully updated the data');
        return redirect()->back();
    }

    public function ship() {
        $user = Auth::guard('user')->user();
        return view('user.ship', compact('user'));
    }

    public function affilate_code() {
        $user = Auth::guard('user')->user();
        return view('user.affilate_code', compact('user'));
    }

    public function shipup(Request $request) {
        $input = $request->all();
        $user  = Auth::guard('user')->user();
        $user->update($input);
        Session::flash('success', 'Successfully updated the data');
        return redirect()->back();
    }

    public function reset(Request $request) {
        $input = $request->all();
        $user  = Auth::guard('user')->user();
        if ($user->is_provider == 1) {
            return redirect()->back();
        }
        if ($request->cpass) {
            if (Hash::check($request->cpass, $user->password)) {
                if ($request->newpass == $request->renewpass) {
                    $input['password'] = Hash::make($request->newpass);
                } else {
                    Session::flash('unsuccess', 'Confirm password does not match.');
                    return redirect()->back();
                }
            } else {
                Session::flash('unsuccess', 'Current password Does not match.');
                return redirect()->back();
            }
        }
        $user->update($input);
        Session::flash('success', 'Successfully updated your password');
        return redirect()->back();
    }

    public function package() {
        $user    = Auth::guard('user')->user();
        $subs    = Subscription::all();
        $package = $user->subscribes()->where('status', 1)->orderBy('id', 'desc')->first();
        return view('user.package', compact('user', 'subs', 'package'));
    }

    public function vendorrequest($id) {
        $subs    = Subscription::findOrFail($id);
        $gs      = Generalsetting::findOrfail(1);
        $user    = Auth::guard('user')->user();
        $package = $user->subscribes()->where('status', 1)->orderBy('id', 'desc')->first();
        if ($gs->reg_vendor != 1) {
            return redirect()->back();
        }
        return view('user.vendor-request', compact('user', 'subs', 'package'));
    }

    public function vendorrequestsub(StoreValidationRequest $request) {
        $this->validate($request, [
            'shop_name' => 'unique:users',
                ], [
            'shop_name.unique' => 'This shop name has already been taken.'
        ]);
        $user                  = Auth::guard('user')->user();
        $package               = $user->subscribes()->where('status', 1)->orderBy('id', 'desc')->first();
        $subs                  = Subscription::findOrFail($request->subs_id);
        $settings              = Generalsetting::findOrFail(1);
        $today                 = Carbon::now()->format('Y-m-d');
        $input                 = $request->all();
        $user->is_vendor       = 2;
        $user->date            = date('Y-m-d', strtotime($today . ' + ' . $subs->days . ' days'));
        $user->mail_sent       = 1;
        $user->update($input);
        $sub                   = new UserSubscription;
        $sub->user_id          = $user->id;
        $sub->subscription_id  = $subs->id;
        $sub->title            = $subs->title;
        $sub->currency         = $subs->currency;
        $sub->currency_code    = $subs->currency_code;
        $sub->price            = $subs->price;
        $sub->days             = $subs->days;
        $sub->allowed_products = $subs->allowed_products;
        $sub->details          = $subs->details;
        $sub->method           = 'Free';
        $sub->status           = 1;
        $sub->save();
        if ($settings->is_smtp == 1) {
            $data   = [
                'to'      => $user->email,
                'type'    => "vendor_accept",
                'cname'   => $user->name,
                'oamount' => "",
                'aname'   => "",
                'aemail'  => "",
            ];
            $mailer = new GeniusMailer();
            $mailer->sendAutoMail($data);
        } else {
            $headers = "From: " . $settings->from_name . "<" . $settings->from_email . ">";
            mail($user->email, 'Your Vendor Account Activated', 'Your Vendor Account Activated Successfully. Please Login to your account and build your own shop.', $headers);
        }

        return redirect()->route('user-dashboard')->with('success', 'Vendor Account Activated Successfully');
    }

    public function profileupdate(UpdateValidationRequest $request) {
        $input = $request->all();
        $user  = Auth::guard('user')->user();
        if ($file  = $request->file('photo')) {
            $name = time() . $file->getClientOriginalName();
            $file->move('assets/images', $name);
            if ($user->photo != null) {
                if (file_exists(public_path() . '/assets/images/' . $user->photo)) {
                    unlink(public_path() . '/assets/images/' . $user->photo);
                }
            }
            $input['photo'] = $name;
        }
        $user->update($input);
        $language = Language::find(1);
        Session::flash('success', $language->success);
        return redirect()->route('user-profile');
    }

    public function wishlists() {
        $user   = Auth::guard('user')->user();
        $wishes = Wishlist::where('user_id', '=', $user->id)->get();
        return view('user.wishlist', compact('user', 'wishes'));
    }

    public function favorites() {
        $user      = Auth::guard('user')->user();
        $favorites = FavoriteSeller::where('user_id', '=', $user->id)->get();
        return view('user.favorite', compact('user', 'favorites'));
    }

    public function delete($id) {
        $gs   = Generalsetting::findOrfail(1);
        $wish = Wishlist::findOrFail($id);
        $wish->delete();
        return redirect()->route('user-wishlist')->with('success', $gs->wish_remove);
    }

    public function favdelete($id) {
        $gs   = Generalsetting::findOrfail(1);
        $wish = FavoriteSeller::findOrFail($id);
        $wish->delete();
        return redirect()->route('user-favorites')->with('success', 'Successfully Removed The Seller.');
    }

    public function wishlist(Request $request) {
        $sort = '';
        if (!empty($request->min) || !empty($request->max)) {
            $min       = $request->min;
            $max       = $request->max;
            $user      = Auth::guard('user')->user();
            $wishes    = Wishlist::where('user_id', '=', $user->id)->pluck('product_id');
            $wproducts = Product::whereIn('id', $wishes)->whereBetween('cprice', [$min, $max])->orderBy('id', 'desc')->paginate(9);
            return view('front.wishlist', compact('user', 'wproducts', 'sort', 'min', 'max'));
        }
        $user      = Auth::guard('user')->user();
        $wishes    = Wishlist::where('user_id', '=', $user->id)->pluck('product_id');
        $wproducts = Product::whereIn('id', $wishes)->orderBy('id', 'desc')->paginate(9);
        return view('front.wishlist', compact('user', 'wproducts', 'sort'));
    }

    public function wishlistsort($sorted) {
        $sort   = $sorted;
        $user   = Auth::guard('user')->user();
        $wishes = Wishlist::where('user_id', '=', $user->id)->pluck('product_id');
        if ($sort == "new") {
            $wproducts = Product::whereIn('id', $wishes)->orderBy('id', 'desc')->paginate(9);
        } else if ($sort == "old") {
            $wproducts = Product::whereIn('id', $wishes)->paginate(9);
        } else if ($sort == "low") {
            $wproducts = Product::whereIn('id', $wishes)->orderBy('cprice', 'asc')->paginate(9);
        } else if ($sort == "high") {
            $wproducts = Product::whereIn('id', $wishes)->orderBy('cprice', 'desc')->paginate(9);
        }
        return view('front.wishlist', compact('user', 'wproducts', 'sort'));
    }

    public function social() {
        $socialdata = Auth::guard('user')->user();
        return view('user.social', compact('socialdata'));
    }

    public function socialupdate(Request $request) {
        $socialdata = Auth::guard('user')->user();
        $input      = $request->all();
        if ($request->f_check == "") {
            $input['f_check'] = 0;
        }
        if ($request->t_check == "") {
            $input['t_check'] = 0;
        }

        if ($request->g_check == "") {
            $input['g_check'] = 0;
        }

        if ($request->l_check == "") {
            $input['l_check'] = 0;
        }

        $socialdata->update($input);
        Session::flash('success', 'Social links updated successfully.');
        return redirect()->route('user-social-index');
    }

//Send email to user
    public function usercontact(Request $request) {
        $data   = 1;
        $user   = User::findOrFail($request->user_id);
        $vendor = User::where('email', '=', $request->email)->first();
        if (empty($vendor)) {
            $data = 0;
            return response()->json($data);
        }

        $subject = $request->subject;
        $to      = $vendor->email;
        $name    = $request->name;
        $from    = $request->email;
        $msg     = "Name: " . $name . "\nEmail: " . $from . "\nMessage: " . $request->message;
        $gs      = Generalsetting::findOrfail(1);
        if ($gs->is_smtp == 1) {
            $data = [
                'to'      => $vendor->email,
                'subject' => $request->subject,
                'body'    => $msg,
            ];

            $mailer = new GeniusMailer();
            $mailer->sendCustomMail($data);
        } else {
            $headers = "From: " . $gs->from_name . "<" . $gs->from_email . ">";
            mail($to, $subject, $msg, $headers);
        }

        $conv = Conversation::where('sent_user', '=', $user->id)->where('subject', '=', $subject)->first();
        if (isset($conv)) {
            $msg                  = new Message();
            $msg->conversation_id = $conv->id;
            $msg->message         = $request->message;
            $msg->sent_user       = $user->id;
            $msg->save();
            return response()->json($data);
        } else {
            $message                       = new Conversation();
            $message->subject              = $subject;
            $message->sent_user            = $request->user_id;
            $message->recieved_user        = $vendor->id;
            $message->message              = $request->message;
            $message->save();
            $notification                  = new UserNotification;
            $notification->user_id         = $vendor->id;
            $notification->conversation_id = $message->id;
            $notification->save();
            $msg                           = new Message();
            $msg->conversation_id          = $message->id;
            $msg->message                  = $request->message;
            $msg->sent_user                = $request->user_id;
            ;
            $msg->save();
            return response()->json($data);
        }
    }

    public function adminmessages() {
        $user  = Auth::guard('user')->user();
        $convs = AdminUserConversation::where('user_id', '=', $user->id)->get();
        return view('user.message.index', compact('convs'));
    }

    public function adminmessage($id) {
        $conv = AdminUserConversation::findOrfail($id);
        return view('user.message.create', compact('conv'));
    }

    public function adminmessagedelete($id) {
        $conv = AdminUserConversation::findOrfail($id);
        if ($conv->messages->count() > 0) {
            foreach ($conv->messages as $key) {
                $key->delete();
            }
        }
        if ($conv->notifications->count() > 0) {
            foreach ($conv->notifications as $key) {
                $key->delete();
            }
        }
        $conv->delete();
        return redirect()->back()->with('success', 'Message Deleted Successfully');
    }

    public function adminpostmessage(Request $request) {
        $msg                           = new AdminUserMessage();
        $input                         = $request->all();
        $msg->fill($input)->save();
        $notification                  = new Notification;
        $notification->conversation_id = $msg->conversation->id;
        $notification->save();
        Session::flash('success', 'Message Sent!');
        return redirect()->back();
    }

    public function adminusercontact(Request $request) {
        $data    = 1;
        $user    = Auth::guard('user')->user();
        $gs      = Generalsetting::findOrFail(1);
        $subject = $request->subject;
        $to      = $gs->email;
        $from    = $user->email;
        $msg     = "Email: " . $from . "\nMessage: " . $request->message;
        if ($gs->is_smtp == 1) {
            $data = [
                'to'      => $to,
                'subject' => $subject,
                'body'    => $msg,
            ];

            $mailer = new GeniusMailer();
            $mailer->sendCustomMail($data);
        } else {
            $headers = "From: " . $gs->from_name . "<" . $gs->from_email . ">";
            mail($to, $subject, $msg, $headers);
        }

        $conv = AdminUserConversation::where('user_id', '=', $user->id)->where('subject', '=', $subject)->first();
        if (isset($conv)) {
            $msg                  = new AdminUserMessage();
            $msg->conversation_id = $conv->id;
            $msg->message         = $request->message;
            $msg->user_id         = $user->id;
            $msg->save();
            return response()->json($data);
        } else {
            $message                       = new AdminUserConversation();
            $message->subject              = $subject;
            $message->user_id              = $user->id;
            $message->message              = $request->message;
            $message->save();
            $notification                  = new Notification;
            $notification->conversation_id = $message->id;
            $notification->save();
            $msg                           = new AdminUserMessage();
            $msg->conversation_id          = $message->id;
            $msg->message                  = $request->message;
            $msg->user_id                  = $user->id;
            $msg->save();
            return response()->json($data);
        }
    }

    public function verify(Request $request) {

        if (Auth::user()->status == 2) {
            return redirect(url("user/dashboard"));
        }
        $curl = new Curl();

        $message = "Your account activation code is : " . Auth::user()->pin;
        if (session("language") == "1") {
            $message = "Your account activation code is : " . Auth::user()->pin;
        }
        if (session("language") == "8") {
            $message = "كود التفعيل الخاص بك هو : " . Auth::user()->pin;
        }

        $number = Auth::user()->phone;
        $curl->get('https://api-server14.com/api/send.aspx?apikey=KdfNNUdc1wkxqUySOIkYd2ybi&language=2&sender=wsm.ae&mobile=' . $number . '&message=' . $message);

        return view("front-old.verify")->with("number", $number);
    }

    public function verify_code(Request $request) {


        if (Auth::user()->pin == $request->input("code")) {


            User::where('id', Auth::user()->id)->update(array(
                "status" => 2
            ));


            return redirect(url("user/dashboard"));
        }
        $request->session()->flash("error", "الكود الدي ادخلت غير صحيح");
        return redirect(url("user/verify"));
    }

    public function verify_resend(Request $request) {
        $curl = new Curl();

        $message = "Your account activation code is : " . Auth::user()->pin;
        if (session("language") == "1") {
            $message = "Your account activation code is : " . Auth::user()->pin;
        }
        if (session("language") == "8") {
            $message = "كود التفعيل الخاص بك هو : " . Auth::user()->pin;
        }
        $curl->get('http://api-server3.com/api/send.aspx?username=wsm&password=lucky32box57&language=2&sender=wsm.ae&mobile=' . Auth::user()->phone . '&message=' . $message);
        return redirect(url("user/verify"));
    }

    public function token() {

        $apikey       = "YTVlNTE3MzgtZDRlMS00MzY2LWIwZTQtYzE4NmY3NzBkNGRhOjhhNDhkYzM4LTcyYzEtNDNlYi1hMDgzLTJlNGExOTMwMTBmMw==";  // enter your API key here
        $ch           = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://identity.ngenius-payments.com/auth/realms/NetworkInternational/protocol/openid-connect/token");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Basic " . $apikey,
            "Content-Type: application/x-www-form-urlencoded"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('grant_type' => 'client_credentials')));
        $output       = json_decode(curl_exec($ch));
        $access_token = $output->access_token;

        return $access_token;
    }
    public function paymentid($id) {
        $order_id = $id;
        $payment_url = Session::get('urla');
         $in_acspares = "";
          $in_acsmd = "";
        $outlet = "354a20fc-99eb-4e08-ae1e-b55ee70fb7f9";
        $token  = $this->token();
        
        if (isset($_POST['PaRes'])) { $in_acspares = $_POST['PaRes']; }
        if (isset($_POST['MD'])) { $in_acsmd = $_POST['MD']; }
          $postData = new StdClass();
          $postData->PaRes = $in_acspares;

          $json = json_encode($postData);

          $ch = curl_init();

          curl_setopt($ch, CURLOPT_URL, $payment_url);
          curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$token, "Content-Type: application/vnd.ni-payment.v2+json", "Accept: application/vnd.ni-payment.v2+json"));
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);        
          curl_setopt($ch, CURLOPT_POST, 1);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

          $output = json_decode(curl_exec($ch));


                if(isset($output->state) && !empty($output->state)){
                $state = $output->state;
                
                curl_close ($ch);
                      $order = Order::where("id", $order_id)->first();

                if ($state == "CAPTURED" || $state == "AUTHORISED") {
                                  
                                  


           $gs = Generalsetting::findOrFail(1);


            $msg     = "Hello! " . Auth::user()->name
                    . "<p></p>"
                    . "Your invoice has been paid successfully"
                    . "<p></p>"
                    . "order number :" . $order->order_number
                    . "<p></p>"
                    . "total Qty :" . $order->totalQty
                    . "<p></p>"
                    . "pay amount :" . $order->pay_amount . " AED";
            $headers = "From: " . $gs->from_name . "<" . $gs->from_email . ">\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            mail(Auth::user()->email, "Your invoice has been paid successfully", $msg, $headers);

            $cart = unserialize(utf8_decode($order->cart));


            foreach ($cart->items as $key => $c) {

                $qty     = ($c["qty"]);
                $product = $key;

                $productBYID = \App\Awards::where('product', $product)->first();

                for ($x = 1; $x <= $qty; $x++) {
                    \App\Tickets::create(array(
                        "product"  => $product,
                        "prize"    => \App\Awards::where('product', $product)->first()->id,
                        "tickets"  => $this->serial() . Carbon::parse($productBYID->campaign_date)->format('Ym') . '-' . \App\Awards::where('product', $product)->first()->campaign_number . "-" . Auth::user()->id . "-" . Carbon::now()->format("Hi"),
                        "user_id"  => $order->user_id,
                        "order_id" => $order_id,
                    ));
                    if(\App\Awards::where('product', $product)->first()->free != 0 && \App\Awards::where('product', $product)->first()->free != '')
                    {
                        for ($xx = 1; $xx <= \App\Awards::where('product', $product)->first()->free; $xx++) {

                        \App\Ftickets::create(array(
                            "product"  => $product,
                            "prize"    => \App\Awards::where('product', $product)->first()->id,
                            "tickets"  => $this->serial() . Carbon::parse($productBYID->campaign_date)->format('Ym') . '-' . \App\Awards::where('product', $product)->first()->campaign_number . "-" . Auth::user()->id . "-" . Carbon::now()->format("Hi"),
                            "user_id"  => $order->user_id,
                            "order_id" => $order_id,
                        ));                   
                        }
                    }
                }
            }

                        Order::where("id", $order_id)->update(array(
                            "active"=> 1,"status" => "processing"
                        ));

               
        return redirect(url("user/paymentsuccess"));

                }else
                {
                                                                       Order::where("id", $order_id)->update(array(
                            "active"=> 2
                        ));

        return redirect(url("user/paymentfailed/" . $order_id));


                }
                }else
                {
                                                                        Order::where("id", $order_id)->update(array(
                            "active"=> 2
                        ));
        return redirect(url("user/paymentfailed/" . $order_id));


   
                }
          

      
    }
    public function paid($id,Request $request) {



        $order = Order::where('id', $id)->where("user_id", Auth::user()->id)->first();

        $postData               = new StdClass();
        $postData->action       = "SALE";
        $postData->emailAddress = Auth::user()->email;

        $postData->merchantAttributes              = new StdClass();
        $postData->merchantAttributes->redirectUrl = "https://wsm.ae/demo/user/completed/" . $id;
        $postData->merchantAttributes->cancelUrl   = "https://wsm.ae/demo/payment/return/" . $id;
        $postData->merchantAttributes->cancelText  = "Cancel and return to the site";
        $postData->merchantAttributes->skipConfirmationPage  = true;
        $postData->merchantAttributes->skip3DS  = true;
        
        $postData->amount                          = new StdClass();
        $postData->amount->currencyCode            = "AED";
        $postData->amount->value                   = $order->pay_amount * 100;
        $postData->billingAddress                          = new StdClass();
        $postData->billingAddress->firstName            = $order->customer_name;
        $postData->billingAddress->lastName                   = $order->customer_name;
        $postData->billingAddress->address1                   = $order->customer_address;
        $postData->billingAddress->city                   = $order->customer_city;
        $postData->billingAddress->countryCode                   = $order->customer_country;
        $postData->shippingAddress                           = new StdClass();
        $postData->shippingAddress->firstName            = $order->customer_name;
        $postData->shippingAddress->lastName                   = $order->customer_name;
        $postData->shippingAddress->address1                   = $order->customer_address;
        $postData->shippingAddress->city                   = $order->customer_city;
        $postData->shippingAddress->countryCode                   = $order->customer_country;


        $outlet = "354a20fc-99eb-4e08-ae1e-b55ee70fb7f9";
        $token  = $this->token();

        $json = json_encode($postData);
        $ch   = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api-gateway.ngenius-payments.com/transactions/outlets/" . $outlet . "/orders");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $token,
            "Content-Type: application/vnd.ni-payment.v2+json",
            "Accept: application/vnd.ni-payment.v2+json"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

        $output = json_decode(curl_exec($ch));



        $order_reference   = $output->reference;
        $order_paypage_url = $output->_links->payment->href;
        curl_close($ch);

        session(['reference' => $order_reference]);

        return redirect($order_paypage_url);
    }
    public function paymentsuccess() {
        
        return view('payment_success');

    }
    public function paymentfailed($id)
    {
        return view('payment_failed')->with('id',$id);

    }
    public function proccesspayment(Request $request)
    {
        return view('proccess_payment');

    }





    public function check_payment(Request $request) {
                $order_id = $request->input("order_id");
                $mcount = strlen($request->input("month"));
                $month = $request->input("month");
                $expiry = $request->input("year")."-".$month;
                $postData  = new StdClass();

                $postData->order  = new StdClass();
                $postData->order->action = "SALE";
                $postData->emailAddress                          = "payer@wsm.ae";
                $postData->billingAddress                          = new StdClass();
                $postData->billingAddress->firstName	                          = "test";
                $postData->billingAddress->lastName                          =  "test";
                $postData->billingAddress->address1                          =  "test address";
                $postData->billingAddress->city                          =  "Dubi";
                $postData->billingAddress->countryCode                          =  "UAE";
                $postData->shippingAddress                          = new StdClass();
                $postData->shippingAddress->firstName	                          = "test";
                $postData->shippingAddress->lastName                          =  "test";
                $postData->shippingAddress->address1                          =  "test address";
                $postData->shippingAddress->city                          =  "Dubi";
                $postData->shippingAddress->countryCode                          =  "UAE";
                $postData->order->amount            =  new StdClass();
                $postData->order->amount->currencyCode            = "AED";
                $postData->order->amount->value                   = $request->input("amount") * 100;
                $postData->payment  = new StdClass();
                $postData->payment->pan = $request->input("card_number");
                $postData->payment->expiry = $expiry;
                $postData->payment->cvv = $request->input("cvv");
                $postData->payment->cardholderName = $request->input("card_name");

                $json = json_encode($postData);
                $outlet = "354a20fc-99eb-4e08-ae1e-b55ee70fb7f9";
                $token  = $this->token();

                $ch = curl_init();
        
                curl_setopt($ch, CURLOPT_URL, "https://api-gateway.ngenius-payments.com/transactions/outlets/".$outlet."/payment/card");
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$token, "Content-Type: application/vnd.ni-payment.v2+json", "Accept: application/vnd.ni-payment.v2+json"));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);        
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");

                $output = json_decode(curl_exec($ch));
                if(isset($output->state) && !empty($output->state)){
                $state = $output->state;
                
                curl_close ($ch);
                $current_date = date("Y-m-d");

                    if( $request->has('save_card') ){
                        if($request->input("save_card")==1)
                        {
               $checkcard = Cards::where("user_id", Auth::guard('user')->user()->id)->where("card_number",$request->input("card_number"))->orderBy('id', 'DESC')->get()->count();
                if($checkcard == 0){

                $cards = new Cards;
                $cards['card_name'] = $request->input("card_name");
                $cards['card_number'] = $request->input("card_number");
                $cards['month'] = $request->input("month");
                $cards['year'] = $request->input("year");
                $cards['user_id'] = Auth::guard('user')->user()->id;
                $cards['date'] = $current_date;
                $cards->save();
                }
                        }
                    }


 
                if ($state == "AWAIT_3DS") {
                  $cnp3ds_url = $output->_links->{'cnp:3ds'}->href;
                  $acsurl = $output->{'3ds'}->acsUrl;
                  $acspareq = $output->{'3ds'}->acsPaReq;
                  $acsmd = $output->{'3ds'}->acsMd;
                  Session::put('urla', $cnp3ds_url);
                  $order = Order::where("id", $order_id)->first();
                  $order = Order::where("id", $order_id)->first();

                        Order::where("id", $order_id)->update(array(
                            "active"=> 0
                        ));
      
                        return redirect()->route('proccess-payment')->with('acsu', $acsurl)->with('cnp3ds_url', $cnp3ds_url)->with('acspareq', $acspareq)->with('acsmd', $acsmd)->with('order_id', $order_id);


                }elseif ($state == "CAPTURED") {
                                  
                                  
                      $order = Order::where("id", $order_id)->first();

                        Order::where("id", $order_id)->update(array(
                            "status" => "processing","active" => 0
                        ));

           $gs = Generalsetting::findOrFail(1);


            $msg     = "Hello! " . Auth::user()->name
                    . "<p></p>"
                    . "Your invoice has been paid successfully"
                    . "<p></p>"
                    . "order number :" . $order->order_number
                    . "<p></p>"
                    . "total Qty :" . $order->totalQty
                    . "<p></p>"
                    . "pay amount :" . $order->pay_amount . " AED";
            $headers = "From: " . $gs->from_name . "<" . $gs->from_email . ">\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            mail(Auth::user()->email, "Your invoice has been paid successfully", $msg, $headers);

            $cart = unserialize(utf8_decode($order->cart));


            foreach ($cart->items as $key => $c) {

                $qty     = ($c["qty"]);
                $product = $key;

                $productBYID = \App\Awards::where('product', $product)->first();

                for ($x = 1; $x <= $qty; $x++) {
                    \App\Tickets::create(array(
                        "product"  => $product,
                        "prize"    => \App\Awards::where('product', $product)->first()->id,
                        "tickets"  => $this->serial() . Carbon::parse($productBYID->campaign_date)->format('Ym') . '-' . \App\Awards::where('product', $product)->first()->campaign_number . "-" . Auth::user()->id . "-" . Carbon::now()->format("Hi"),
                        "user_id"  => $order->user_id,
                        "order_id" => $order_id,
                    ));
                                        if(\App\Awards::where('product', $product)->first()->free != 0 && \App\Awards::where('product', $product)->first()->free != '')
                    {
                        for ($xx = 1; $xx <= \App\Awards::where('product', $product)->first()->free; $xx++) {

                        \App\Ftickets::create(array(
                            "product"  => $product,
                        "prize"    => \App\Awards::where('product', $product)->first()->id,
                            "tickets"  => $this->serial() . Carbon::parse($productBYID->campaign_date)->format('Ym') . '-' . \App\Awards::where('product', $product)->first()->campaign_number . "-" . Auth::user()->id . "-" . Carbon::now()->format("Hi"),
                            "user_id"  => $order->user_id,
                            "order_id" => $order_id,
                        ));                   
                        }
                    }
                }
            }

                            return redirect(url("user/paymentsuccess"));
                }else
                {
                    
                    return redirect()->back()->with("error", 'Error Happened while Procceding your payment');

                }
                }else
                {
                                     return redirect()->back()->with("error", 'Error Happened while Procceding your payment');
   
                }


    }
    public function completed($id, Request $request) {

        $outlet = "354a20fc-99eb-4e08-ae1e-b55ee70fb7f9";
        $token  = $this->token();
        $ch     = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api-gateway.ngenius-payments.com/transactions/outlets/" . $outlet . "/orders/" . $request->input("ref") . "");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $token,
            "Content-Type: application/vnd.ni-payment.v2+json",
            "Accept: application/vnd.ni-payment.v2+json"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


        $input = json_decode(curl_exec($ch), true);



        curl_close($ch);





        $order = Order::where("id", $id)->first();


        if ($input["_embedded"]["payment"][0]["state"] == "CAPTURED") {


            Order::where("id", $id)->update(array(
                "status" => "processing"
            ));



            $gs = Generalsetting::findOrFail(1);


            $msg     = "Hello! " . Auth::user()->name
                    . "<p></p>"
                    . "Your invoice has been paid successfully"
                    . "<p></p>"
                    . "order number :" . $order->order_number
                    . "<p></p>"
                    . "total Qty :" . $order->totalQty
                    . "<p></p>"
                    . "pay amount :" . $order->pay_amount . " AED";
            $headers = "From: " . $gs->from_name . "<" . $gs->from_email . ">\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            mail(Auth::user()->email, "Your invoice has been paid successfully", $msg, $headers);

            $cart = unserialize(utf8_decode($order->cart));


            foreach ($cart->items as $key => $c) {

                $qty     = ($c["qty"]);
                $product = $key;

                $productBYID = \App\Awards::where('product', $product)->first();

                for ($x = 1; $x <= $qty; $x++) {
                    \App\Tickets::create(array(
                        "product"  => $product,
                        "prize"    => \App\Awards::where('product', $product)->first()->id,
                        "tickets"  => $this->serial() . Carbon::parse($productBYID->campaign_date)->format('Ym') . '-' . \App\Awards::where('product', $product)->first()->campaign_number . "-" . Auth::user()->id . "-" . Carbon::now()->format("Hi"),
                        "user_id"  => $order->user_id,
                        "order_id" => $id,
                    ));
                }
            }
        }

        if ($input["_embedded"]["payment"][0]["state"] == "FAILED") {
            $request->session()->flash("error", "An error occurred during the payment process");
        }






        return redirect(url("payment/return/" . $id));
    }

    public function serial() {

        $tokens = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        $serial = '';

        for ($i = 0; $i < 2; $i++) {
            for ($j = 0; $j < 4; $j++) {
                $serial .= $tokens[rand(0, 35)];
            }

            if ($i < 4) {
                $serial .= '-';
            }
        }

        return $serial;
    }

}
