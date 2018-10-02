<?php
use Slim\Http\Request;
use Slim\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LoansController {
  protected $container;
  protected $loansValidator;

  public function __construct (Slim\Container $container) {
    $this->container = $container;

    $this->loansValidator = $container->get('loansValidator');
  }

  /**
  * Get a listing of the resource.
  *
  * @param Slim\Http\Request $request
  * @param Slim\Http\Response $response
  * @param array $args
  *
  * @return Slim\Http\Response
  */
  public function index (Request $request, Response $response, array $args) {
    $loans = Loan::orderBy('date_given', 'DESC')->get();
    foreach ($loans as $loan) {
      $loan->member_info = $loan->member()->first();
    }
    return $response->withJson(['loans' => $loans]);
  }

  /**
  * Verify wheter the member is eligible for a loan
  *
  * @param int
  *
  * @return boolean
  */
  private function verifyMemberEligibility($id) {
    try {
      $member = Member::findOrFail($id);

      // $loans = $member->loans()->get();
      $shares = $member->shares()->get()->toArray();

      if(
        !$member->is_active || 
        !$member->is_member || 
        $member->is_defector || 
        empty($shares)
      ){
        return false;
      }

      return true;
    } catch(ModelNotFoundException $e) {
      return false;
    }
  }

  /**
  * Get a loan payments history
  *
  * @param Slim\Http\Request $request
  * @param Slim\Http\Response $response
  * @param array $args
  *
  * @return Slim\Http\Response
  */
  public function showLoanPayments(Request $request, Response $response, array $args) {
    try {
      $loan = Loan::findOrFail($args['id']);
      $member = $loan->member()->first();

      $payments = $loan->payments()->orderBy('received_date', 'desc')->get();

      return $response->withJson(
        ['loan' => $loan,
        'payments' => $payments,
        'member' => $member]
      );
    }
    catch (ModelNotFoundException $e){
      return $response->withJson('Loan not found', 404);
    }
  }

  /**
  * Get a loan guarantor's list
  *
  * @param Slim\Http\Request $request
  * @param Slim\Http\Response $response
  * @param array $args
  *
  * @return Slim\Http\Response
  */
  public function showLoanGuarantors(Request $request, Response $response, array $args) {
    try {
      $loan = Loan::findOrFail($args['id']);

      $guarants = $loan->guarants()->get();
      $info = [];

      foreach( $guarants as $guarant) {
        $info[$guarant->member_id] = [
          'member_info' => $guarant->member()->first()->toArray(),
          'guarant' => $guarant->toArray(),
        ];
      }

        $info['loan'] = $loan;
        $info['loan']['owner'] = $loan->member()->first()->toArray();

        return $response->withJson($info);
    }
    catch(ModelNotFoundException $e) {
      return $response->withJson(['Loan Not Found', 404]);
    }
  }

  /**
  * Store a Loan in the db
  *
  * @param Slim\Http\Request $request
  * @param Slim\Http\Response $response
  * @param array $args
  *
  * @return Slim\Http\Response
  */
  public function store (Request $request, Response $response, array $args) {
    // Heads up incase of invalid JSON
    $input = $request->getParsedBody(); 
    if($input === null) { 
      return $response->withJson( 
        ['error_decoding_json' => 'It seems the JSON provided is invalid'], 
        400, 
        JSON_PRETTY_PRINT 
      ); 
    }

    // Check validation
    if($this->loansValidator->hasErrors()) {
      return $response->withJson($this->loansValidator->getErrors(), 403);
    }
    else {

        $settings = Setting::all()->first();
        try{
          $member = Member::findOrFail($input['member_id']);

          $dategiven = Carbon\Carbon::parse($input['date_given']);
          $input['date_given'] = $dategiven;

          $date = new Carbon\Carbon();
          $date = $date->parse($input['date_given']);
          $input['date_given'] = $date->toDateString();

          $guarantors = $input['guarantors'];

          $input['date_due'] = $date->addMonths(1)->toDateString();
          $interest = (($settings->loan_interest)/100) * $input['amount'];
          $input['amount_payable'] = $interest + $input['amount'];
          $input['approved'] = true;
          $input['paid_full'] = false;
          $extremedate = $dategiven->addMonths($settings->loan_duration);
          $input['extreme_due'] = $extremedate->toDateString();
          $input['installment'] = $input['amount_payable']/($settings->loan_duration);

          $loan = Loan::create($input);
          //add retention fee in held up
          $retention_fee = ($settings->retention_fee/100) * ($member->shares-$member->shares_held);
          $member->shares_held += $retention_fee;
          $member->save();
          $loan->retention_fee = $retention_fee;
          $loan->save();
          $loan->id_no = $member->id_no;

          //notify
          /*
          if($settings->notifications){
              $message_loanee = "Loan processed. Your loan balance of Ksh ".$loan->amount_payable." is due on ".$loan->date_due." and a fee of KSh  ".$interest." applied.";
              NotifySend::create(['messageto'=>$member->phone, 'messagefrom'=>$settings->notification_number, 'message'=>$message_loanee, 'member_id'=>$member->id]);
          }
          */

          $all_guarantors = [];
          //add guarantors
          foreach($input['guarantors_amounts'] as $key=>$guarantor){
              $theguarantor = Member::find($key);

              $input_guarant = [
                  'member_id' => $key,
                  'loan_id' => $loan->id,
                  'loan_owner_id' => $input['member_id'],
                  'amount' => $guarantor,
                  'to_release' => $guarantor,
                  'retention_fee' => 0,
              ];

              $each_guarant = Guarant::create($input_guarant);
              $all_guarantors[] = $each_guarant;

              if($key==$input['member_id']) {
                $theguarantor->shares_held += $each_guarant->amount;
              }
              else{
                  // $guarantors_retention = ($theguarantor->shares-$theguarantor->shares_held)*($settings->retention_fee/100);
                  // $each_guarant->retention_fee = $guarantors_retention;
                  // $each_guarant->save();
                  $theguarantor->shares_held += $each_guarant->amount; // + $guarantors_retention;
              }
              
              $theguarantor->save();

/*
              if($settings->notifications){
                  $message_guarantor = "Dear member, you have guaranteed ".$member->name." KSh ".$each_guarant->amount." on their loan.";
                  NotifySend::create(['messageto'=>$theguarantor->phone, 'messagefrom'=>$settings->notification_number, 'message'=>$message_guarantor, 'member_id'=>$theguarantor->id]);
              }
*/
          }

          return $response->withJson(['loan'=>$loan, 'guarantees'=>$all_guarantors, 'member'=>$member->toArray()], 200);


        } catch(ModelNotFoundException $e) {
          return $response->withJson(['The member you are trying to give a loan cannot be found', 404]);
        }  
    }
  }

  /**
  * Update specified resource in storage
  *
  * @param Slim\Http\Request $request
  * @param Slim\Http\Response $response
  * @param array $args
  *
  * @return Slim\Http\Response
  */
  public function update (Request $request, Response $response, array $args) {

  }

  /**
  * Remove specified resource from storage.
  *
  * @param Slim\Http\Request $request
  * @param Slim\Http\Response $response
  * @param array $args
  *
  * @return Slim\Http\Response
  */
  public function destroy (Request $request, Response $response, array $args) {
    try{
      $loan = Loan::findOrFail($args['id']);
      $guarants = $loan->guarants()->get();
      $payments = $loan->payments()->get();

      $amounts_to_release = [];

      //delete payments
      if(!empty($payments)) {
        foreach($payments as $payment) {
          $payment->delete();
        }
      }

      //delete guarants
      if(!empty($guarants)) {
        foreach ($guarants as $guarant) {
          $amounts_to_release[$guarant->member_id] += $guarant->to_release;
          $guarant->delete();
        }
      }

      //delete loan
      if(!empty($loan)) {
        $amounts_to_release[$loan->member_id] += $loan->retention_fee;
        $loan->delete();
      }

      //Restore withheld shares
      foreach( $amounts_to_release as $key => $amount ) {
        $member = Member::find($key);
        $member->shares_held -= $amount;

        if($member->shares_held < 0) {
          $members->shares_held = 0;
        }

        $member->save();
      }

      return $response->withJson('Success', 200);

    }
    catch(ModelNotFoundException $e) {
      return $response->withJson('The loan cannot be found', 404);
    }
  }

  public function pay_loan(Request $request, Response $response, array $args) {
    // Heads up incase of invalid JSON
    $input = $request->getParsedBody(); 
    if($input === null) { 
      return $response->withJson( 
        ['error_decoding_json' => 'It seems the JSON provided is invalid'], 
        400, 
        JSON_PRETTY_PRINT 
      ); 
    }

    // Check validation
    if($this->container->payValidator->hasErrors()) {
      return $response->withJson($this->loansValidator->getErrors(), 403);
    }
    else {
      try {
        $loan = Loan::findOrFail($args['id']);

        if($loan->paid_full) {
          return $response->withJson('Loan Fully Paid', 200);
        }

        $loanee = $loan->member()->first();

        $date = Carbon\Carbon::parse($input['date_given']);
        $input['received_date'] = $date->toDateString();

        unset($input['date_given']);
        $amount_rem = $loan->amount_payable -= $input['amount'];

        //Send notification
        //

        $payment = Payment::create($input);
        $loan->update(['amount_payable' => $amount_rem]);

        if($amount_rem <= 0) {
          $tempamount = $loan->amount_payable;
                      $loan->update([
                        'paid_full'=>true,
                        'amount_payable'=>0]);
                      $loan->save();
                      $loan->amount_payable = $tempamount;

                      //$message = "Dear Member. Your loan of Ksh ".$loan->amount." has been fully repaid.";
        }
//

        $releases = [];

        $theguarants = $loan->guarants()->get();
        $totalalienguarants = 0;

        foreach($theguarants as $guarantindex=>$eachguarant){
            $releases[$eachguarant->member_id]['amount'] = 0;

            if($eachguarant->member_id!=$loan->member_id)
                $totalalienguarants += $eachguarant->amount;
        }

        if($totalalienguarants>0){
            foreach($theguarants as $guarantindex=>$eachguarant) {
                if($eachguarant->member_id==$loan->member_id) {
                  $releases[$loan->member_id]['guarant_id'] = $eachguarant->id;
                    continue;
                 }

                $theguarantor = $eachguarant->member()->first();
                //his/her percentage
                $guarant_percentage = $eachguarant->amount/$totalalienguarants;
                $release_amount = $guarant_percentage * $payment->amount;

                if($release_amount > $eachguarant->to_release){
                    $releases[$theguarantor->id]['amount'] += $eachguarant->to_release;
                    $releases[$theguarantor->id]['guarant_id'] = $eachguarant->id;
                    $releases[$loan->member_id]['amount'] += ($release_amount-$eachguarant->to_release);
                    $releases[$theguarantor->id]['release_retention'] = 1;
                }else{
                    $releases[$theguarantor->id]['amount'] += $guarant_percentage*$payment->amount;
                    $releases[$theguarantor->id]['guarant_id'] = $eachguarant->id;
                }
            }

        } else {
            $releases[$loan->member_id]['guarant_id'] = $theguarants->first()->id;
            $releases[$loan->member_id]['amount'] = $payment->amount;
        }

            if($loan->paid_full){
                $thepayments = $loan->payments()->get();
                $allpayments = 0;
                foreach($thepayments as $eachpayment){
                    $allpayments += $eachpayment->amount;
                }


                $loanee->shares_held -= ($loan->retention_fee - (($allpayments-$loan->amount)-$loan->amount_payable));
                $loanee->save();
            }

            foreach($releases as $m_id=>$eachrelease){
                $owner = new Member;
                $owner = Member::find($m_id);

                $owner->shares_held -= $eachrelease['amount'];
                //if($owner->shares_held<0)$owner->shares_held=0;
                $owner->save();

                $theguarant = New Guarant;
                $theguarant = Guarant::find($eachrelease['guarant_id']);
                $theguarant->to_release -= $eachrelease['amount'];
            

                if($theguarant->to_release < 0)$theguarant->to_release=0;
                $theguarant->save();
                if($theguarant->to_release==0){
                    $owner->shares_held -= $theguarant->retention_fee;
                    $theguarant->retention_fee=0;
                    $theguarant->save();
                    $owner->save();
                }
            }

            /*
            //after payments and releases
            //check if amount paid is lower limit
            $payments = $loan->payments()->get();

            $today = \Carbon\Carbon::now();
            $today_string = $today->toDateString();
            $loan_taken_date = \Carbon\Carbon::parse($loan->date_given);
            $loan_due_date = \Carbon\Carbon::parse($loan->date_due);
            $extremedue = \Carbon\Carbon::parse($loan->extreme_due);

            //check this month's payments
            $this_month_payments = [];
            foreach($payments as $each_payment){
                $today_exploded = explode('-', $today_string);
                $payment_date_exploded = explode('-', $payment->received_date);

                if($today_exploded[1]==$payment_date_exploded[1])
                    $this_month_payments[] = $each_payment;
            }
            */

            //add this month's payments
            //$this_month_sum = 0;
            //foreach($this_month_payments as $each_payment)
            //    $this_month_sum += $each_payment->amount;
            
            //amount supposed to pay this month
            //$installment = $loan->amount_payable / ($today->diffInMonths($extremedue));

            //if paid as supposed to
            //recalculate amount to pay next
            //add months
            //if($this_month_sum>=$installment){
                
            //}

        /*
        if($this->settings->notifications){
            \App\NotifySend::create(['messageto'=>$loanee->phone, 'messagefrom'=>$this->settings->notification_number, 'message'=>$message, 'member_id'=>$loanee->id]);
        }
        */
            return $response->withJson(
              ['loan'=>$loan, 
               'payment'=>$payment
              ],
              200);

            //

      }
      catch (ModelNotFoundException $e) {
        return $response->withJson('The loan cannot be found', 404);
      }
    }
  }
}
