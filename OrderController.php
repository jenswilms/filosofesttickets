<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Order;
use App\Mail\OrderMail;
use Mail;
use App\Email;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{

    // public function show() {
    //     $amount = DB::table('tickets')->count();
    //     if($amount < 80) {
    //         return view('home');
    //     } else {
    //         return view('soldout');
    //     }
    // }

    // still needs updating on maximum amount of tickets


    public function store(Request $request, $eventid) {

        $validatedData = $request->validate([
            'quantity' => 'required|numeric|digits_between:1,9',
            'name' => 'required|alpha_spaces_hyphens',
            'surname' => 'required|alpha_spaces_hyphens',
            'email' => 'required|email',
            'check' => 'nullable',
            'paymentmethod' => 'required|alpha',
            'bank' => 'required|alpha_spaces_underscores',
        ]);

        if($request->input('check') == null){
            $check = 0;
        } 

        else { // store email in newsletter db
            $check = 1;

            $emaildata = [
                'name' => trim($request->input('name')),
                'email' => trim($request->input('email')),
            ];

            Email::create($emaildata);

        }

        $order = [
                'name' => trim($request->input('name')),
                'surname' => trim($request->input('surname')),
                'email' => trim($request->input('email')),
                'check' => $check,
                'paymentmethod' => $request->input('paymentmethod'),
                'bank' => $request->input('bank'),
                'amount' => $request->input('quantity'),
                'stripeid' => 0,
                'eventid' => $eventid,
                'paid' => false,    
            ];


    	$price = DB::table('events')->where('id', $eventid)->value('price');

        if($price == 0 || $price == "free") {
            //make stripe id

            do
            {

                $stripeid = str_random(10);
                $get_stripeid = DB::table('orders')->where('stripeid', '=', $stripeid)->first();
            }

            while(!empty($get_stripeid->stripeid));

            $order['paid'] = 1;
            $order['stripeid'] = $stripeid;

            Order::create($order);

            app('App\Http\Controllers\OrderController')->success($stripeid);

            return redirect( config('app.url') . "/processing" );
        }

        else {
            // nonfree ticket

            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
            
            // referentie nummer genereren - nog te doen
            $descriptor = "Ticket(s) for " . $order['name']; 

            if ( $order['paymentmethod'] == 'ideal' ) {
                //ideal
        
                $result = \Stripe\Source::create(array(
                    "type" => "ideal",
                    "amount" => $order['amount'] * $price * 100,
                    "currency" => "eur",
                    "owner" => array(
                        "email" => $order['email'],
                        "name" => $order['name'] . " " . $order['surname']
                    ), 
                    "redirect" => array(
                        "return_url" => config('app.url') . "/processing"
                    ),
                    "ideal" => array(
                        "bank" => $order['bank']
                    ),
                    "statement_descriptor" => $descriptor
                ));

                //gegevens opslaan in database
                $order['stripeid'] = $result['id'];

            }

            else { //creditcard or applepay

                $session = \Stripe\Checkout\Session::create([
                      'customer_email' => $order['email'],
                      'payment_method_types' => ['card'],
                      'line_items' => [[
                        'name' => $descriptor,
                        'description' => 'Ticket(s) for one of our Filosofest events!', // add customization
                        'amount' => $price * 100,
                        'currency' => 'eur',
                        'quantity' => $order['amount'],
                      ]],
                      'success_url' => config('app.url') . "/processing?success=true",
                      'cancel_url' => config('app.url') . "/processing?success=false",
                    ]);

                $session_id = $session->id;
                $order['stripeid'] = $session_id;

            }
  
            Order::create($order);

            if ( $order['paymentmethod'] == 'ideal' ) {

                return redirect( $result['redirect']['url'] );
            }

            else {
                
                return view('redirect')->with('id', $session_id);
            }

        } 

    }




    public function handle(Request $request) {

        $payload = request()->all();
        $type = $payload['type'];
        

        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));


        switch ($type) {

            case 'source.chargeable':

                $idempotency_key = Uuid::uuid4();

                // create charge
                $charge = \Stripe\Charge::create(array(
                    "amount" => $payload['data']['object']['amount'],
                    "currency" => "eur",
                    "source" => $payload['data']['object']['id'],
                    "description" => "Ticket(s) for Filosofest"
                ), array(
                    "idempotency_key" => $idempotency_key,
                ));

                break;


            case 'charge.succeeded' :

                $sourceid = $payload['data']['object']['source']['id'];

                app('App\Http\Controllers\OrderController')->success($sourceid);

            break;

            case 'checkout.session.completed' :

                $sourceid = $payload['data']['object']['id'];
                app('App\Http\Controllers\OrderController')->success($sourceid);

            break;

            case 'charge.failed':

                $sourceid = $payload['data']['object']['source']['id'];

                $order = DB::table('orders')
                    ->where('stripeid', $sourceid)    
                    ->first();

                // send errors
                Mail::to($order->email)->send(new OrderCancellation($order));

            break;

            case 'source.failed' || 'source.canceled':

                $sourceid = $payload['data']['object']['id'];

                $order = DB::table('orders')
                    ->where('stripeid', $sourceid)    
                    ->first();

                // send errors
                Mail::to($order->email)->send(new OrderCancellation($order));

            break;
            
            
        }

    }

    public function success($sourceid) {

                $order = DB::table('orders')
                    ->where('stripeid', $sourceid)    
                    ->first();

                $event = DB::table('events')
                    ->where('id', $order->eventid)
                    ->first();

                // update paid in db
                DB::table('orders')
                    ->where('stripeid', $sourceid)
                    ->update(['paid' => 1]);

                // store tickets in db-> get code from TicketController@create
                app('App\Http\Controllers\TicketController')->create($sourceid);

                // send email with pdfs to customer
                $tickets = Storage::disk('local')->files('storage/tickets/' . $sourceid);

                Mail::to($order->email)->send( new OrderMail($order, $tickets, $event) );
                
                // delete directory sourceid
                Storage::disk('local')->deleteDirectory('/storage/tickets/' . $sourceid);
    }


}
