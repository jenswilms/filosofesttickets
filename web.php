<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/test', function() {
	return view('testpayment');
});


Route::get('/', function () {
	$events = DB::table('events')->orderBy('date', 'asc')->limit(2)->get();
    return view('home', ['events' => $events]);
});

Route::get('/events', function() {
	$today = date('Y-m-d');
	$events = DB::table('events')->orderBy('date', 'asc')->get();
	return view('events', ['events' => $events]);
});

Route::get('/events/{page}', 'EventController@show');

Route::get('/about', function() {
	return view('about');
});

Route::get('/privacy', function () {
	return view('privacy');
});

Route::get('/vacancies', function() {
	$vacancies = DB::table('vacancies')->orderBy('id', 'asc')->get();
	return view('vacancies.overview', ['vacancies' => $vacancies]);
});

Route::get('/vacancies/{page}', 'VacancyController@show');

Route::get('/culture', function() {
	return view('vacancies.culture');
});


// Route::get('/.well-known/apple-developer-merchantid-domain-association', function() {
// 	return response()->file(Storage::path('verification/apple-developer-merchantid-domain-association'));
// }); // applepay verification



Route::post('/newsletter', 'MailController@store');

Route::post('/payment/{eventid}', 'OrderController@store');
//stores data in DB & redirects to Stripe

Route::get('/processing', function() {
	return view('afterpayment');
});

Route::post('/stripe/webhook', 'OrderController@handle');

Route::get('/ticket/{ticketcode}', 'TicketController@show');
//checks if ticket exists & is already scanned, then returns view accordingly

Route::post('/mark', 'TicketController@mark');
// marks ticket as scanned



