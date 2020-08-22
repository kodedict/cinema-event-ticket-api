<?php

namespace App\Http\Controllers\API;

use App\cinema;
use App\cinema_history;
use App\cinema_order;
use App\cma;
use App\cma_desc;
use App\event;
use App\event_history;
use App\event_order;
use App\movie;
use Illuminate\Http\Request;
use App\Http\Controllers\API\apiFunction as apiFunction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class appapiController extends apiFunction
{
    public $successStatus = 200;
    //
    public function cinema_type(){

        $cma_desc = cma_desc::all();

        $cma = cma::all();

        foreach ($cma as  $cmas){
            $json_cinema = $cmas->get_cinema->cinema_desc;
            $arr_cinema = json_decode($json_cinema,true);
            $cin = $arr_cinema['cinema_name'];

            $json_movies = $cmas->get_movie->movie_desc;
            $arr_movies = json_decode($json_movies,true);
            $mov =$arr_movies['movie_title'];

            $m_time =  $cmas->get_cma_desc->cma_d_t;

            $price = $cmas->get_cma_desc->price;
            $arr_price = json_decode($price, true);

            $adult = $arr_price['adult_price'];
            $child = $arr_price['child_price'];

            $arr[] =['cinema'=>$cin,'movie'=>$mov,'schedule'=>$m_time,'adult'=>$adult,'child'=>$child];
        }




        return $this->sendResponse($arr, 'Assigned Gotten');
    }

    public function movie_list(){
        $movies =movie::all();

        foreach ($movies as $movie){
            $movie_id = $movie['movie_id'];

            $json_movie_desc = json_decode($movie['movie_desc'],true);
            $movie_title= $json_movie_desc['movie_title'];

            $arr_movies[] = ['movie_id'=>$movie_id,'movie_title'=>$movie_title];
        }

        return $this->sendResponse($arr_movies, 'Movie List Gotten');

    }

    public  function movie_single($id){
        $movie = movie::find($id);
        $cinemas = cma::all()->where('movie_id',$id);
        foreach ($cinemas as $cinema){
            $json_cinema = $cinema->get_cinema->cinema_desc;
            $arr_cinema = json_decode($json_cinema,true);
            $cinema_name = $arr_cinema['cinema_name'];
            $cinema_list[] = ['cinema_id'=>$cinema['cinema_id'],'cinema_name'=>$cinema_name] ;

            $price = $cinema['price'];
            $arr_price = json_decode($price,true);
            $cma_d_t[] = [
                'cinema_id'=>$cinema['cinema_id'] ,
                'schedule'=>$cinema['cma_d_t'],
                'adult_price'=>$arr_price['adult_price'],
                'child_price'=>$arr_price['child_price'],
                'cma_id'=>$cinema['cma_id']
            ];




        }


        $cinemas_list = array_values(array_unique($cinema_list,SORT_REGULAR));


        $json_movie_desc = json_decode($movie['movie_desc'],true);
        $movie_title= $json_movie_desc['movie_title'];
        $movie_length = $json_movie_desc['movie_length'];
        $movie_desc = $json_movie_desc['movie_desc'];

        $arr_movie[] = ['movie_title'=>$movie_title,'movie_length'=>$movie_length,'movie_desc'=>$movie_desc];
        $mode[] = ['movie_desc'=>$arr_movie,'cinema'=>$cinemas_list,'cma_desc'=>$cma_d_t];

        return $this->sendResponse($mode, 'Single Movie Gotten');
    }


    public function order_cinema(Request $request){

        $cinema = $request->getContent();
        $cinema_item = json_decode($cinema,true);

        $user = Auth::user();
        $user_id = $user['user_id'];
        $valid_check= 'PENDING';
        $movie_id = $cinema_item['Movie_ID'];
        $book_amount = $cinema_item['Book_Amount'];
        $cinema_id = $cinema_item['Cinema_ID'];
        $children_quantity = $cinema_item['Children_Quantity'];
        $adult_quantity = $cinema_item['Adult_Quantity'];
        $quantity =['adult_quantity'=>$adult_quantity,'children_quantity'=>$children_quantity];
        $quantity_set = json_encode($quantity);
        $event_date = $cinema_item['Event_Date'];
        $cma_id = $cinema_item['CMA_ID'];
        $temp_id = $cinema_item['temp_id'];

        $order_desc = [
            'cinema_id'=>$cinema_id,
            'movie_id'=>$movie_id,
            'book_quantity'=>$quantity,
            'event_date'=>$event_date
        ];
        $order_desc_set = json_encode($order_desc);

        $total_quantity = $children_quantity + $adult_quantity;

        $cma = cma::all()->where('cma_id',$cma_id)->first();
        $book_slot = $cma['book_slot'];
        $book_num = $cma['booked_num'];
        $available_slot = $book_slot - $book_num;
        if($total_quantity <= $available_slot){

            $cma_update = cma::all()->where('cma_id',$cma_id)->first();
            $cma_update->booked_num = $total_quantity + $book_num;
            $cma_update->save();
            $return['message'] ="VALID";
            $return['slot'] ="NIL";

            cinema_order::create([
                'user_id' => $user_id,
                'cinema_id' => $cinema_id,
                'movie_id' => $movie_id,
                'ticket_id' => $temp_id,
                'book_quantity' =>$quantity_set,
                'total_price' => $book_amount,
                'event_date' => $event_date,
                'valid_check' => $valid_check
            ]);

            cinema_history::create([
                'user_id'=>$user_id,
                'ticket_id'=>$temp_id,
                'order_price'=>$book_amount,
                'order_desc'=>$order_desc_set,
                'valid_check'=>$valid_check
            ]);

        }else{
            $return['message'] = "INVALID";
            $return['slot'] = $available_slot;
        }





        return $this->sendResponse($return, 'Cinema Ticket Order Booked');

    }

    public function cinema_payment(Request $request){
        $json_ticket = $request->getContent();
        $ticket_item = json_decode($json_ticket,true);
        $ticket_id = $ticket_item['temp_id'];
        $ticket_ = cinema_order::all()->where('ticket_id',$ticket_id)->first();
        $ticket_amount =$ticket_['total_price'];
        $user = auth()->user();
        $user_wallet = $user->get_wallet->wallet_balance;

        $ticket_cinema = $ticket_['cinema_id'];
        $json_cinema = cinema::all()->where('cinema_id',$ticket_cinema)->first();
        $arr_cinema = json_decode($json_cinema['cinema_desc'],true);
        $cinema_name = str_replace(' ','-',$arr_cinema['cinema_name']);

        $ticket_history =cinema_history::all()->where('ticket_id',$ticket_id)->first();



        if($ticket_ and $ticket_history){

            $new_wallet = $user_wallet - $ticket_amount;
            $new_ticket_id = $cinema_name.rand(1,9).rand(1,9).rand(1,9).str_random(2);

            $user->get_wallet->wallet_balance = $new_wallet;
            $user->get_wallet->save();

            $ticket_->valid_check = "VALID";
            $ticket_->ticket_id = $new_ticket_id;
            $ticket_->save();

            $ticket_history->valid_check = "VALID";
            $ticket_history->ticket_id = $new_ticket_id;
            $ticket_history->save();

            return $this->sendResponse($user,'VALID');
        }
    }

    public function cinema_history(){

        $user = Auth::user();
        $user_id = $user['user_id'];


        $cinema_history = cinema_history::all()->where('user_id',$user_id)->where('valid_check','VALID')->all();


        if($cinema_history){

            foreach ($cinema_history as $cinema_historys){
                $json_order_desc = $cinema_historys['order_desc'];
                $arr_order_desc = json_decode($json_order_desc,true);

                $movie_id = $arr_order_desc['movie_id'];
                $json_movie = movie::where('movie_id',$movie_id)->first();
                $m = $json_movie['movie_desc'];

                $arr_movie = json_decode($m,true);
                $movie_title =$arr_movie['movie_title'];

                $cinema_id = $arr_order_desc['cinema_id'];
                $json_cinema = cinema::where('cinema_id',$cinema_id)->first();
                $c = $json_cinema['cinema_desc'];

                $arr_cinema = json_decode($c,true);
                $cinema_name = $arr_cinema['cinema_name'];

                $json_quantity = $arr_order_desc['book_quantity'];
                $adult_quantity = $json_quantity['adult_quantity'];
                $children_quantity = $json_quantity['children_quantity'];


                $cinema_type_history [] = [
                    'movie_title'=>$movie_title,
                    'cinema_title'=>$cinema_name,
                    'event_date'=>$arr_order_desc['event_date'],
                    'order_price'=>$cinema_historys['order_price'],
                    'adult_quantity'=>$adult_quantity,
                    'children_quantity'=>$children_quantity,
                    'booked_on'=>$cinema_historys['created_at']
                ];



            }

            return $this->sendResponse($cinema_type_history, 'Cinema History Gotten');
        }
        else{
            $null =null;
            return $this->sendResponse($null, 'NIL');
        }


    }


    public function cinema_order(){
        $user = Auth::user();
        $user_id = $user['user_id'];
        $cinema_order = cinema_order::all()->where('user_id',$user_id)->all();
        if($cinema_order){
            foreach($cinema_order as $cinema_orders){
                $json_movie  = $cinema_orders->get_movie_co->movie_desc;
                $json_cinema = $cinema_orders->get_cinema_co->cinema_desc;

                $arr_movie = json_decode($json_movie,true);
                $arr_cinema = json_decode($json_cinema,true);

                $movie_title = $arr_movie['movie_title'];
                $cinema_title = $arr_cinema['cinema_name'];

                $json_book_quantity = $cinema_orders['book_quantity'];
                $arr_book_quantity = json_decode($json_book_quantity,true);

                $cinema_order_list [] = [
                    'cinema_name'=>$cinema_title,
                    'movie_title'=>$movie_title,
                    'event_date'=>$cinema_orders['event_date'],
                    'total_price'=>$cinema_orders['total_price'],
                    'adult_quantity'=>$arr_book_quantity['adult_quantity'],
                    'children_quantity'=>$arr_book_quantity['children_quantity'],
                    'valid_check'=>$cinema_orders['valid_check']
                ];
            }

            return $this->sendResponse($cinema_order_list, 'SUCCESS');
        }else{
            $null =null;
            return $this->sendResponse($null, 'NIL');
        }

    }


    public function event_list(){

        $event = event::all();

        if($event) {

            foreach ($event as $events) {

                $event_id = $events['event_id'];
                $event_desc = json_decode($events['event_desc'], true);
                $event_name = $event_desc['event_name'];
                $event_list [] = [
                    'event_id' => $event_id,
                    'event_name' => $event_name
                ];
            }
            $message = "VALID";
        }else{
            $event_list = "NIL";
            $message = "INVALID";
        }
        return $this->sendResponse($event_list, $message);
    }

    public  function  event_single($id){

        $event = event::find($id);

        $event_desc_json = json_decode($event['event_desc'],true);
        $event_name = $event_desc_json['event_name'];
        $event_desc = $event_desc_json['event_desc'];

        $event_location = $event_desc_json['event_location'];

        $event_date_json = json_decode($event['event_date'],true);
        $event_date = $event_date_json['event_date'];
        $event_time = $event_date_json['event_time'];

        $slot_detail_json = json_decode($event['slot_detail'],true);
        $count = count($slot_detail_json['slot_name']);
        $var = 0;
        while($var<$count){

            $slot_detail []= [
                'slot_name' => $slot_detail_json['slot_name'][$var],
                'slot_price' => $slot_detail_json['slot_price'][$var],
                'slot_num' => $var
            ] ;

            $var++;
        }

        $event_detail [] = [
            'event_name' => $event_name,
            'event_desc' => $event_desc,
            'event_location' => $event_location,
            'event_date' => $event_date,
            'event_time' => $event_time,
            'slot_detail' => $slot_detail
        ];



        return $this->sendResponse($event_detail, 'Single Event Gotten');

    }

    public function event_order(Request $request){

        $event = $request->getContent();
        $event_item = json_decode($event,true);
        $user = Auth::user();
        $user_id = $user['user_id'];
        $valid_check = 'PENDING';
        $event_id = $event_item['event_id'];
        $book_id = $event_item['temp_book_id'];
        $slot_type = $event_item['slot_type'];
        $slot_type_num = $event_item['slot_type_num'];
        $slot_quantity = $event_item['slot_quantity'];
        $total_price = $event_item['total_amount'];
        $event_detail = event::all()->where('event_id',$event_id)->first();

        $event_date =json_decode($event_detail['event_date'],true);
        $date = $event_date['event_date']."  ".$event_date['event_time'];

        $book_slot_json = json_decode($event_detail['slot_detail'],true);
        $book_slot = $book_slot_json['slot_number'][$slot_type_num];
        $book_num = $event_detail['booked_num'];
        $available_slot = $book_slot - $book_num;
        $order_desc = [
            'event_id'=>$event_id,
            'slot_type'=>$slot_type,
            'book_quantity'=>$slot_quantity,
            'event_date'=>$date
        ];
        $order_desc_set = json_encode($order_desc);

        if($slot_quantity<=$available_slot){
            $return['message'] = "VALID";
            $return['slot'] = "NIL";

            $event_detail->booked_num = $slot_quantity + $book_num;
            $event_detail->save();

            event_order::create([
                'user_id' => $user_id,
                'event_id' => $event_id,
                'book_id' => $book_id,
                'slot_type' => $slot_type,
                'slot_quantity' => $slot_quantity,
                'total_price' => $total_price,
                'event_date' => $date,
                'valid_check' => $valid_check
            ]);
            event_history::create([
                'user_id' =>$user_id,
                'book_id' => $book_id,
                'order_price' => $total_price,
                'order_desc' => $order_desc_set,
                'valid_check' => $valid_check
            ]);


        }else{
            $return['message'] = "INVALID";
            $return['slot'] = $available_slot;
        }

        return $this->sendResponse($return, 'Event Ticket Pending');

    }


    public function event_payment(Request $request){
        $json_ticket = $request->getContent();
        $ticket_item = json_decode($json_ticket,true);
        $ticket_id = $ticket_item['temp_id'];
        $ticket_ = event_order::all()->where('book_id',$ticket_id)->first();
        $ticket_amount =$ticket_['total_price'];
        $user = auth()->user();
        $user_wallet = $user->get_wallet->wallet_balance;

        $ticket_history =event_history::all()->where('book_id',$ticket_id)->first();

        if($ticket_ and $ticket_history){

            $new_wallet = $user_wallet - $ticket_amount;
            $new_ticket_id = "Ticket".rand(1,9).rand(1,9).rand(1,9).str_random(2);

            $user->get_wallet->wallet_balance = $new_wallet;
            $user->get_wallet->save();

            $ticket_->valid_check = "VALID";
            $ticket_->book_id = $new_ticket_id;
            $ticket_->save();

            $ticket_history->valid_check = "VALID";
            $ticket_history->book_id = $new_ticket_id;
            $ticket_history->save();

            return $this->sendResponse($user,'VALID');
        }
        else{
            return $this->sendResponse($ticket_history,'INVALID');
        }


    }

    public function order_event(){
        $user = Auth::user();
        $user_id = $user['user_id'];
        $order_event = event_order::all()->where('user_id',$user_id)->all();
        if($order_event){
            foreach($order_event as $order_events){
                $json_event  = $order_events->get_event->event_desc;

                $arr_event = json_decode($json_event,true);

                $event_name = $arr_event['event_name'];

                $order_event_list [] = [
                    'event_name'=>$event_name,
                    'event_date'=>$order_events['event_date'],
                    'total_price'=>$order_events['total_price'],
                    'slot_type'=>$order_events['slot_type'],
                    'slot_quantity'=>$order_events['slot_quantity'],
                    'valid_check'=>$order_events['valid_check']
                ];
            }

            return $this->sendResponse($order_event_list, 'SUCCESS');
        }else{
            $null =null;
            return $this->sendResponse($null, 'NIL');
        }

    }

    public function event_history(){

        $user = Auth::user();
        $user_id = $user['user_id'];


        $event_history = event_history::all()->where('user_id',$user_id)->where('valid_check','VALID')->all();


        if($event_history){

            foreach ($event_history as $event_historys){
                $json_order_desc = $event_historys['order_desc'];
                $arr_order_desc = json_decode($json_order_desc,true);

                $event_id = $arr_order_desc['event_id'];
                $json_event = event::where('event_id',$event_id)->first();
                $e = $json_event['event_desc'];

                $arr_event = json_decode($e,true);
                $event_name =$arr_event['event_name'];



                $event_type_history [] = [
                    'event_name'=>$event_name,
                    'slot_type'=>$arr_order_desc['slot_type'],
                    'event_date'=>$arr_order_desc['event_date'],
                    'order_price'=>$event_historys['order_price'],
                    'slot_quantity'=>$arr_order_desc['book_quantity'],
                    'booked_on'=>$event_historys['created_at']
                ];



            }

            return $this->sendResponse($event_type_history, 'Event History Gotten');
        }
        else{
            $null =null;
            return $this->sendResponse($null, 'NIL');
        }


    }


}
