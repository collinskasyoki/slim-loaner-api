<?php
use Slim\Http\Request;
use Slim\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LoansController {
  protected $container;
  protected $loansValidator;

  public function __construct (Slim\Container $container) {
    // $this->container = $container;

    $this->loansValidator = $container->get('loansValidator');
    {
      $this->foo = $foo;
    }
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
    return $response->withJson(['loans' => Loan::orderBy('date_given', 'DESC')->get()]);
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
                  $guarantors_retention = ($theguarantor->shares-$theguarantor->shares_held)*($settings->retention_fee/100);
                  $each_guarant->retention_fee = $guarantors_retention;
                  $each_guarant->save();
                  $theguarantor->shares_held += $each_guarant->amount + $guarantors_retention;
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

  }

  public function pay_loan(){

  }
}
