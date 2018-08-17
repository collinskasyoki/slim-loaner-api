<?php
use Slim\Http\Request;
use Slim\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MembersController {
  protected $container;
  protected $memberValidation;

  public function __construct (Slim\Container $container) {
    $this->memberValidation = $container->get('memberValidation');
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
    return $response->withJson(['members' => Member::all()->toArray()]);
  }

  /**
  * Get a specific member
  *
  * @param Slim\Http\Response $response
  * @param Slim\Http\Response $response
  * @param array $args
  *
  * @return Slim\Http\Response
  */
  public function getMember(Request $request, Response $response, array $args) { 
      try {
        $member = Member::findOrFail($args['id']);

        // For the times when only essential member info is needed
        // Without Share and Loan Information
        if (array_key_exists('_essential', $request->getQueryParams())){
          return $response->withJson(['member' => $member]);
        }

        $total_in_shares=0;
        $member_shares = $member->shares()->get();
        if($member_shares!=null)
            foreach($member_shares as $eachshare)
                $total_in_shares+=$eachshare->amount;

        $total_in_loans=0;
        $member_loans = $member->loans()->get();
        if($member_loans!=null)
            foreach($member_loans as $eachloan)
                $total_in_loans+=$eachloan->amount;

        $total_in_guarantees=0;
        $guaranteed = $member->guarants()->get();
        if($guaranteed!=null)
            foreach ($guaranteed as $eachguarantee)
                $total_in_guarantees+=$eachguarantee->amount;

        return $response->withJson(['member'=>$member, 'shares'=>$total_in_shares, 'loans'=>$total_in_loans, 'guaranteed'=>$total_in_guarantees]);
      }
      catch (ModelNotFoundException $e) {
        return $response->withJson('Member not found', 404);
      }
  }

  /**
  * Store the specified resource in storage
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

     //Check Validation
     if($this->memberValidation->hasErrors()) {
       return $response->withJson($this->memberValidation->getErrors(), 403);
     }
     else {
       $data = $request->getParsedBody();

       // Check existing conflicts?
       // Not allowed duplicates ['id_no', 'email']
       $similarIdNumber = Member::where('id_no', $data['id_no'])->get()->toArray();
       if(!empty($similarIdNumber))
         return $response->withJson(["The ID Number Already Exists."], 403);

       // Kenyan phone numbers are circulated without the country code
       // and prepended with a zero
       // Append the country code +254 to the phone number
       // The preceding zero should already be scrapped off
       if(strlen($data['phone'])==9)
           $data['phone'] = '+254'.substr($data['phone'], 0);
       if(strlen($data['next_kin_phone'])==9)
           $data['next_kin_phone']='+254'.substr($data['next_kin_phone'], 0);

       // All is well
       // Add data to db
       $member = Member::create($data);
       return $response->withJson($member, 200);
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
    // Heads up incase of invalid JSON
    $input = $request->getParsedBody(); 
      if($input === null) { 
        return $response->withJson( 
          ['error_decoding_json' => 'It seems the JSON provided is invalid'], 
          400, 
          JSON_PRETTY_PRINT 
        ); 
      }
      
    //Check Validation
    if($this->memberValidation->hasErrors()) {
      return $response->withJson($this->memberValidation->getErrors(), 403);
    }
    else {
      try {
        $member = Member::findOrFail($args['id']);
        
        $data = $request->getParsedBody();

        // Kenyan phone numbers are circulated without the country code
        // and prepended with a zero
        // Append the country code +254 to the phone number
        // The preceding zero should already be scrapped off
        if(strlen($data['phone'])==9)
            $data['phone'] = '+254'.substr($data['phone'], 0);
        if(strlen($data['next_kin_phone'])==9)
            $data['next_kin_phone']='+254'.substr($data['next_kin_phone'], 0);

        $member->update($data);
        $member->save();

        return $response->withJson($member, 200);
      }
      catch (ModelNotFoundException $e) {
        return $response->withJson('Member not found', 404);
      }
    }
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
}
