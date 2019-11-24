<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    public function create($sourceid) {
		
		$order = DB::table('orders')->where('stripeid', $sourceid)->first();

        function generateCode() {
	        			//generate ticket code
			do
			{

		        $ticketcode = str_random(20);
				$get_ticket = DB::table('tickets')->where('ticketcode', '=', $ticketcode)->first();
	        }

	        while(!empty($get_ticket->ticket));

	        return $ticketcode;

        }

        // creates folder for tickets
        Storage::disk('local')->makeDirectory('/storage/tickets/' . $sourceid);

		for ($i = 0; $i < $order->amount; $i++) {

			$ticketcode = generateCode();

	        $ticket = [
	            'name' => $order->name,
	            'surname' => $order->surname,
	            'email' => $order->email,
	            'ticketcode' => $ticketcode,
	            'stripeid' => $order->stripeid,
	            'eventid' => $order->eventid,
	            'isinside' => 0,  
	        ];

	        Ticket::create($ticket);

	        //tickets are stored in database. Next: create pdfs

	        $this->generatepdf($ticket, $i);

		}

    }

    public function show($ticketcode) {

    	// $ticketcode = $_GET['id'];
    	
    	// check if ticket exist
    	$ticket = DB::table('tickets')->where('ticketcode', $ticketcode)->first();

    	// success
    	if( !empty($ticket) && $ticket->isinside == 0 ) {
    		return view('tickets.success')->with('id', $ticket->ticketcode);

    	}

    	// ticket is already checked
    	elseif ( !empty($ticket) && $ticket->isinside == 1) {
    		return view('tickets.failure')->with('marked', 1);
    	}

    	// ticket does not exist
    	else {
    		return view('tickets.failure')->with('marked', 0);
    	}
    }

    public function mark(Request $request) {

    	// needs validation
    	$ticketcode = $request->input('id');

    	DB::table('tickets')
    		->where('ticketcode', $ticketcode)
    		->update(['isinside' => 1]);

    	return "Welcome";

    }

    public function generatepdf($ticket, $id) {

    	$event = DB::table('events')->where('id', $ticket['eventid'])->first();

		//really sorry for this
		$html = '
		<!DOCTYPE html>
		<html lang="en">

		<head>
			<style>

			body { 
				padding: 20px;
				background: white;
				font-family: "Open Sans", "roboto", sans-serif; 
			}

			ul {
				list-style: none;
				margin: 0;
				padding: 0;
			}

			li {
				margin: 0;
				padding: 0;
			}

			.section {
				width: 90%;
			}


			#header p {
				font-size: 3em;
			}

			#main {
				height: 40%;
			}
			
			#footer {
				position: relative;
			}

			#footer p {
			  position: absolute;
			  left: 0;
			  bottom: 30px;
			  height: 40px;
			  margin: 0;
			  padding: 0;
			  font-size: 0.7em;
			}

			#footer p span {
			  text-transform: uppercase;
			  font-size: 1.5em !important;
			}

		</style>
		</head>

		<body>
			<div class="section" id="header">
				<p>'. $event->title . '</p>
			</div>

			<div class="section" id="main">
				<ul>
					<li>Date: ' . \Carbon\Carbon::parse($event->date)->format('F d, Y'). '</li>
					<li>Time: ' . $event->time . '</li>
					<li>Location: ' . $event->location . '</li>
					<li><br></li>
					<li>Ticket for ' . $ticket['name'] . " " . $ticket['surname'] . '</li>
				</ul>

					<div id="qr">
				<img src="data:image/png;base64, ' . base64_encode(\QrCode::format('png')->size(200)->generate(config('app.url') . '/ticket/' . $ticket['ticketcode'])) . ' ">

					
						
					</div>



			</div>
			<div id="footer" class="section">
				
			    <p><span>Filosofest</span><br>
			    Meeting like-minded people through meaningful conversations</p>

			</div>


		</body>
		</html>';

    	$pdf = \App::make('dompdf.wrapper');
		$pdf->loadHTML($html);
		$pdf->save(storage_path('app/storage/tickets/' . $ticket['stripeid'] . '/' . $id . '.pdf'));

	}


}
